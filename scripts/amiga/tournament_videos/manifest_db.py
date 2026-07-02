"""DB anchor sync + validation for tournament video catalog (TV-2b).

Stable editorial keys live in review.csv (youtube_id, tournament name, player names,
score, stage). Numeric FKs (tournament_id, player_*_id, game_ids) are DB caches and must
be refreshed after every full L3 witness reimport.

@see docs/amiga-tournament-videos-policy.md §10
"""

from __future__ import annotations

import csv
import json
from dataclasses import dataclass, field
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_names import identity_key, normalize_display_name
from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, MANIFEST_JSON, REVIEW_CSV


def parse_game_ids(raw: str | None) -> list[int]:
    out: list[int] = []
    for part in (raw or "").split(","):
        part = part.strip()
        if part.isdigit():
            out.append(int(part))
    return out


def connect_db() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


@dataclass
class DbSnapshot:
    players_by_id: dict[int, str] = field(default_factory=dict)
    players_by_name_key: dict[str, list[int]] = field(default_factory=dict)
    tournaments_by_id: dict[int, str] = field(default_factory=dict)
    tournaments_by_name: dict[str, int] = field(default_factory=dict)
    games_by_id: dict[int, dict[str, Any]] = field(default_factory=dict)

    @classmethod
    def load(cls, conn: pymysql.connections.Connection) -> DbSnapshot:
        snap = cls()
        with conn.cursor() as cur:
            cur.execute("SELECT id, name FROM amiga_players WHERE name IS NOT NULL AND name <> ''")
            for row in cur.fetchall():
                pid = int(row["id"])
                name = normalize_display_name(str(row["name"]))
                snap.players_by_id[pid] = name
                snap.players_by_name_key.setdefault(identity_key(name), []).append(pid)

            cur.execute("SELECT id, name FROM tournaments")
            for row in cur.fetchall():
                tid = int(row["id"])
                name = normalize_display_name(str(row["name"]))
                snap.tournaments_by_id[tid] = name
                snap.tournaments_by_name[name] = tid

            cur.execute(
                "SELECT id, tournament_id, player_a_id, player_b_id, source_scores_id, "
                "goals_a, goals_b, phase "
                "FROM amiga_games"
            )
            for row in cur.fetchall():
                gid = int(row["id"])
                snap.games_by_id[gid] = {
                    "id": gid,
                    "tournament_id": int(row["tournament_id"]),
                    "player_a_id": int(row["player_a_id"]),
                    "player_b_id": int(row["player_b_id"]),
                    "source_scores_id": int(row["source_scores_id"]) if row["source_scores_id"] else None,
                    "goals_a": int(row["goals_a"]),
                    "goals_b": int(row["goals_b"]),
                    "phase": str(row["phase"] or ""),
                }
        return snap


def lookup_player_id(name: str, snap: DbSnapshot) -> int | None:
    name = normalize_display_name(name)
    if not name:
        return None
    ids = snap.players_by_name_key.get(identity_key(name), [])
    if len(ids) == 1:
        return ids[0]
    return None


def lookup_tournament_id(label: str, snap: DbSnapshot) -> int | None:
    label = normalize_display_name(label)
    if not label:
        return None
    return snap.tournaments_by_name.get(label)


def player_pair_matches(man_pa: int, man_pb: int, db_pa: int, db_pb: int) -> bool:
    return (man_pa, man_pb) == (db_pa, db_pb) or (man_pa, man_pb) == (db_pb, db_pa)


def sync_csv_row(row: dict[str, str], snap: DbSnapshot) -> list[str]:
    """Refresh DB-cache columns on one review.csv row. Returns human-readable changes."""
    changes: list[str] = []
    yt = row.get("youtube_id", "")

    label = (row.get("tournament_guess_label") or "").strip()
    tid_cached = int((row.get("guessed_tournament_id") or "0").strip() or "0")

    if tid_cached and tid_cached in snap.tournaments_by_id:
        canonical_label = snap.tournaments_by_id[tid_cached]
        if label != canonical_label:
            row["tournament_guess_label"] = canonical_label
            changes.append(
                f"{yt}: tournament_guess_label {label!r} -> {canonical_label!r} (from id {tid_cached})"
            )
            label = canonical_label

    if label:
        tid_from_name = lookup_tournament_id(label, snap)
        if tid_from_name is not None:
            old_tid = (row.get("guessed_tournament_id") or "").strip()
            if old_tid != str(tid_from_name):
                row["guessed_tournament_id"] = str(tid_from_name)
                changes.append(f"{yt}: tournament_id {old_tid or '?'} -> {tid_from_name} ({label})")

    for side in ("a", "b"):
        guess = (row.get(f"player_{side}_guess") or "").strip()
        if not guess:
            continue
        pid = lookup_player_id(guess, snap)
        if pid is None:
            continue
        old = (row.get(f"player_{side}_id_guess") or "").strip()
        if old != str(pid):
            row[f"player_{side}_id_guess"] = str(pid)
            changes.append(f"{yt}: player_{side}_id {old or '?'} -> {pid} ({guess})")

    if (row.get("kind") or "").strip() != "match":
        return changes

    gids = parse_game_ids(row.get("game_id_guess"))
    if not gids:
        return changes

    stale = [g for g in gids if g not in snap.games_by_id]
    if stale:
        row["game_id_guess"] = ""
        changes.append(f"{yt}: cleared stale game_id(s) {stale}")
        return changes

    if len(gids) > 1:
        return changes

    first = snap.games_by_id[gids[0]]
    db_pa = int(first["player_a_id"])
    db_pb = int(first["player_b_id"])
    for side, db_pid in (("a", db_pa), ("b", db_pb)):
        old = (row.get(f"player_{side}_id_guess") or "").strip()
        if old != str(db_pid):
            row[f"player_{side}_id_guess"] = str(db_pid)
            name = snap.players_by_id.get(db_pid, "")
            if name:
                row[f"player_{side}_guess"] = name
            changes.append(f"{yt}: player_{side}_id from game -> {db_pid}")

    tid = int(row.get("guessed_tournament_id") or 0)
    if tid and int(first["tournament_id"]) != tid:
        correct = int(first["tournament_id"])
        row["guessed_tournament_id"] = str(correct)
        tname = snap.tournaments_by_id.get(correct, "")
        if tname:
            row["tournament_guess_label"] = tname
        changes.append(f"{yt}: tournament_id from game -> {correct}")

    return changes


def load_review_rows() -> list[dict[str, str]]:
    with REVIEW_CSV.open(encoding="utf-8", newline="") as fh:
        return list(csv.DictReader(fh))


def load_manifest_videos() -> list[dict[str, Any]]:
    data = json.loads(MANIFEST_JSON.read_text(encoding="utf-8"))
    return list(data.get("videos") or [])


def validate_catalog(
    snap: DbSnapshot,
    *,
    csv_rows: list[dict[str, str]] | None = None,
    manifest_videos: list[dict[str, Any]] | None = None,
    max_errors: int = 40,
) -> tuple[list[str], int]:
    """Read-only oracle. Returns (sample_errors, total_error_count)."""
    csv_rows = csv_rows if csv_rows is not None else load_review_rows()
    manifest_videos = manifest_videos if manifest_videos is not None else load_manifest_videos()
    csv_by_yt = {r["youtube_id"]: r for r in csv_rows if r.get("youtube_id")}

    errors: list[str] = []
    total_errors = 0

    def add(msg: str) -> None:
        nonlocal total_errors
        total_errors += 1
        if len(errors) < max_errors:
            errors.append(msg)

    ids = [v.get("youtube_id") for v in manifest_videos]
    if len(ids) != len(set(ids)):
        add("duplicate youtube_id in manifest")

    groups: dict[str, list[str]] = {}
    for v in manifest_videos:
        rg = v.get("relation_group")
        if rg:
            groups.setdefault(str(rg), []).append(v.get("relation") or "")
    for rg, rels in groups.items():
        canon = [r for r in rels if r == "canonical"]
        if len(canon) > 1:
            add(f"relation_group {rg!r} has multiple canonical rows")

    for v in manifest_videos:
        yt = str(v.get("youtube_id") or "")
        if not yt:
            add("manifest row missing youtube_id")
            continue

        csv_row = csv_by_yt.get(yt)
        if csv_row is None:
            add(f"{yt}: manifest row missing from review.csv")
            continue

        tid = v.get("tournament_id")
        if not tid:
            add(f"{yt}: manifest missing tournament_id")
            continue
        tid = int(tid)
        if tid not in snap.tournaments_by_id:
            add(f"{yt}: unknown tournament_id {tid}")
            continue

        label = (csv_row.get("tournament_guess_label") or "").strip()
        db_name = snap.tournaments_by_id.get(tid, "")
        if label and db_name and identity_key(label) != identity_key(db_name):
            add(
                f"{yt}: tournament_guess_label {label!r} != DB name for id {tid} ({db_name!r})"
            )
        elif label:
            tid_from_name = lookup_tournament_id(label, snap)
            if tid_from_name is None:
                add(f"{yt}: tournament_guess_label {label!r} not in DB")
            elif tid_from_name != tid:
                add(
                    f"{yt}: tournament_id {tid} ({db_name!r}) "
                    f"!= label {label!r} -> id {tid_from_name}"
                )
        elif not label and db_name:
            add(f"{yt}: CSV missing tournament_guess_label (DB has {db_name!r})")

        csv_tid = (csv_row.get("guessed_tournament_id") or "").strip()
        if csv_tid and int(csv_tid) != tid:
            add(f"{yt}: manifest tournament_id {tid} != CSV guessed_tournament_id {csv_tid}")

        if (v.get("kind") or "") != "match":
            gids = v.get("game_ids") or []
            if not gids:
                continue
            for gid in gids:
                gid = int(gid)
                if gid not in snap.games_by_id:
                    add(f"{yt}: game_id {gid} not in amiga_games")
                    continue
                game = snap.games_by_id[gid]
                if int(game["tournament_id"]) != tid:
                    add(
                        f"{yt}: game {gid} tournament_id {game['tournament_id']} "
                        f"!= manifest {tid}"
                    )
            starts = v.get("game_start_sec")
            if starts is not None and len(starts) != len(gids):
                add(f"{yt}: game_start_sec length {len(starts)} != game_ids {len(gids)}")
            from scripts.amiga.tournament_videos.game_links import audit_row_links

            for issue in audit_row_links(csv_row, snap, manifest_game_ids=[int(x) for x in gids]):
                if issue.severity == "error":
                    add(f"{yt}: {issue.code}: {issue.message}")
            continue

        man_pa = v.get("player_a_id")
        man_pb = v.get("player_b_id")
        for pid, field in ((man_pa, "player_a_id"), (man_pb, "player_b_id")):
            if pid is None:
                continue
            pid = int(pid)
            if pid not in snap.players_by_id:
                add(f"{yt}: manifest {field}={pid} not in amiga_players")

        for side in ("a", "b"):
            guess = (csv_row.get(f"player_{side}_guess") or "").strip()
            id_guess = (csv_row.get(f"player_{side}_id_guess") or "").strip()
            if guess and id_guess:
                pid = int(id_guess)
                if pid in snap.players_by_id:
                    if identity_key(snap.players_by_id[pid]) != identity_key(guess):
                        add(
                            f"{yt}: CSV player_{side}_id_guess={pid} "
                            f"({snap.players_by_id[pid]!r}) != guess {guess!r}"
                        )
            man_id = v.get(f"player_{side}_id")
            csv_id = (csv_row.get(f"player_{side}_id_guess") or "").strip()
            if man_id is not None and csv_id and int(man_id) != int(csv_id):
                add(f"{yt}: manifest player_{side}_id {man_id} != CSV {csv_id}")

        gids = v.get("game_ids") or []
        if not gids:
            if man_pa and man_pb:
                add(f"{yt}: match row has player ids but no game_ids")
            continue

        for gid in gids:
            gid = int(gid)
            if gid not in snap.games_by_id:
                add(f"{yt}: game_id {gid} not in amiga_games")
                continue
            game = snap.games_by_id[gid]
            if int(game["tournament_id"]) != tid:
                add(
                    f"{yt}: game {gid} tournament_id {game['tournament_id']} "
                    f"!= manifest {tid}"
                )

        if man_pa is None or man_pb is None:
            add(f"{yt}: match row with game_ids missing player_a_id or player_b_id")
            continue

        man_pa, man_pb = int(man_pa), int(man_pb)
        first_gid = int(gids[0])
        if first_gid in snap.games_by_id:
            g = snap.games_by_id[first_gid]
            if not player_pair_matches(man_pa, man_pb, int(g["player_a_id"]), int(g["player_b_id"])):
                add(
                    f"{yt}: manifest players ({man_pa}, {man_pb}) != "
                    f"game {first_gid} players ({g['player_a_id']}, {g['player_b_id']})"
                )

        if len(gids) > 1:
            tids = {snap.games_by_id[int(g)]["tournament_id"] for g in gids if int(g) in snap.games_by_id}
            if len(tids) > 1:
                add(f"{yt}: game_ids span multiple tournament_id values: {sorted(tids)}")

        starts = v.get("game_start_sec")
        if starts is not None and len(starts) != len(gids):
            add(f"{yt}: game_start_sec length {len(starts)} != game_ids {len(gids)}")

        from scripts.amiga.tournament_videos.game_links import audit_row_links

        for issue in audit_row_links(csv_row, snap, manifest_game_ids=[int(x) for x in gids]):
            if issue.severity == "error":
                add(f"{yt}: {issue.code}: {issue.message}")

    if total_errors > max_errors:
        errors.append(f"... and {total_errors - max_errors} more (showing first {max_errors})")
    return errors, total_errors


def sync_review_csv_from_db(
    rows: list[dict[str, str]],
    snap: DbSnapshot,
    *,
    resolve_matches: bool = True,
) -> tuple[list[str], list[str]]:
    """
    Refresh DB-cache columns on all CSV rows.

    Returns (all_changes, resolve_escalations).
    """
    from scripts.amiga.tournament_videos.apply_review import apply_row_game_id_locks
    from scripts.amiga.tournament_videos.game_links import (
        heuristic_resolve_allowed,
        is_game_link_locked,
        remap_row_game_ids,
        sync_row_sidecar_game_ids,
        validate_sidecar_schema,
    )
    from scripts.amiga.tournament_videos.resolve_games import (
        load_wc_by_year,
        resolve_row,
    )

    all_changes: list[str] = []
    escalations: list[str] = []
    game_cache: dict[int, list] = {}

    for msg in validate_sidecar_schema(rows):
        escalations.append(f"sidecar schema: {msg}")

    for row in rows:
        all_changes.extend(sync_csv_row(row, snap))

    conn = connect_db()
    cur = conn.cursor()
    wc_by_year = load_wc_by_year(cur)

    for row in rows:
        if (row.get("kind") or "").strip() != "match":
            continue
        yt = row.get("youtube_id", "")
        old_gids = parse_game_ids(row.get("game_id_guess"))

        if is_game_link_locked(row):
            new_ids, notes = remap_row_game_ids(row, snap, game_cache)
            if not new_ids:
                escalations.append(f"{yt}: remap failed — {'; '.join(notes)}")
                continue
            if len(new_ids) < len(old_gids):
                escalations.append(
                    f"{yt}: refused shrink {old_gids} -> {new_ids} ({'; '.join(notes)})"
                )
                continue
            new_gids = ",".join(str(i) for i in new_ids)
            if (row.get("game_id_guess") or "").strip() != new_gids:
                row["game_id_guess"] = new_gids
                all_changes.append(f"{yt}: game_id_guess remapped -> {new_gids}")
            all_changes.extend(sync_csv_row(row, snap))
            continue

        if not resolve_matches:
            continue

        if heuristic_resolve_allowed(row):
            ids, note = resolve_row(row, cur, wc_by_year, game_cache)
            if ids:
                new_gids = ",".join(str(i) for i in ids)
                if (row.get("game_id_guess") or "").strip() != new_gids:
                    row["game_id_guess"] = new_gids
                    all_changes.append(f"{yt}: game_id_guess (heuristic) -> {new_gids}")
                if note:
                    prev = (row.get("notes") or "").strip()
                    if note not in prev:
                        row["notes"] = "; ".join(x for x in (prev, note) if x)
                all_changes.extend(sync_csv_row(row, snap))
            elif (row.get("game_id_guess") or "").strip() == "":
                escalations.append(f"{yt}: unresolved match (no game_id)")
        elif old_gids:
            new_ids, notes = remap_row_game_ids(row, snap, game_cache)
            if new_ids:
                new_gids = ",".join(str(i) for i in new_ids)
                if (row.get("game_id_guess") or "").strip() != new_gids:
                    row["game_id_guess"] = new_gids
                    all_changes.append(f"{yt}: game_id_guess remapped -> {new_gids}")
                all_changes.extend(sync_csv_row(row, snap))
            elif notes:
                escalations.append(f"{yt}: remap failed — {'; '.join(notes)}")

    for row in rows:
        sidecar_changes, sidecar_esc = sync_row_sidecar_game_ids(row, snap, game_cache)
        all_changes.extend(sidecar_changes)
        escalations.extend(sidecar_esc)
        if sidecar_changes:
            all_changes.extend(sync_csv_row(row, snap))

    conn.close()
    apply_row_game_id_locks(rows)
    return all_changes, escalations


def write_review_csv(rows: list[dict[str, str]]) -> None:
    if not rows:
        raise ValueError("refusing to write empty review.csv — load failed or catalog wiped")
    with REVIEW_CSV.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)
