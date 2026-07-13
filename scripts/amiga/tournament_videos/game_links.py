"""Editorial match facts and deterministic game-id remap for tournament videos (GL-1+).

@see docs/amiga-tournament-videos-game-links-policy.md
"""

from __future__ import annotations

import csv
import re
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path
from typing import Any

from scripts.amiga.player_names import identity_key, normalize_display_name
from scripts.amiga.tournament_videos.constants import REVIEW_CSV
from scripts.amiga.tournament_videos.game_match import (
    GameRow,
    _parse_score,
    _phase_hint,
    _phase_matches,
    load_tournament_games,
    match_game,
)
from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    lookup_player_id,
    lookup_tournament_id,
    parse_game_ids,
    player_pair_matches,
)

DUAL_LEG_MIN_SEC = 20 * 60
KNOCKOUT_STAGES = frozenset({"quarter", "semi", "final", "bronze", "silver"})
VIDEO_GAME_LINKS_CSV = REVIEW_CSV.parent / "video_game_links.csv"

SIDEcar_COLUMNS = [
    "youtube_id",
    "link_ordinal",
    "tournament_label",
    "player_a",
    "player_b",
    "score",
    "stage",
    "leg",
    "start_sec",
    "verified",
]


@dataclass(frozen=True)
class MatchFactLink:
    youtube_id: str
    ordinal: int
    tournament_label: str
    player_a: str
    player_b: str
    score: str
    stage: str
    leg: int | None
    start_sec: int | None = None


@dataclass(frozen=True)
class LinkAuditIssue:
    youtube_id: str
    severity: str  # error | warn
    code: str
    message: str


def _int_leg(raw: str | None) -> int | None:
    val = (raw or "").strip()
    if val.isdigit():
        n = int(val)
        return n if n in (1, 2) else None
    return None


def _parse_start_sec(raw: str | None) -> int | None:
    """YouTube embed offset in seconds.

    Forum stream indexes use wall-clock **H:MM** into the broadcast (e.g. ``1:26``).
    Sidecar ``start_sec`` accepts ``H:MM`` / ``HH:MM`` or an integer seconds value.
    """
    val = (raw or "").strip()
    if not val:
        return None
    if ":" in val:
        parts = val.split(":", 1)
        if len(parts) != 2 or not parts[0].isdigit() or not parts[1].isdigit():
            return None
        hours = int(parts[0])
        minutes = int(parts[1])
        if minutes < 0 or minutes >= 60:
            return None
        return hours * 3600 + minutes * 60
    if val.isdigit():
        return int(val)
    return None


def _int_start_sec(raw: str | None) -> int | None:
    return _parse_start_sec(raw)


def is_stream_map_row(row: dict[str, str]) -> bool:
    return (row.get("game_link_mode") or "").strip().lower() == "stream_map"


def row_has_sidecar_links(youtube_id: str) -> bool:
    return bool(sidecar_links_for_video(youtube_id))


def row_needs_game_link_audit(row: dict[str, str]) -> bool:
    kind = (row.get("kind") or "").strip()
    yt = row.get("youtube_id", "")
    if kind == "match":
        return True
    return is_stream_map_row(row) or row_has_sidecar_links(yt)


def is_dual_leg_video_row(row: dict[str, str]) -> bool:
    mode = (row.get("game_link_mode") or "").strip().lower()
    if mode == "multi":
        return True
    notes = (row.get("notes") or "").lower()
    return "dual-leg video" in notes


def is_game_link_locked(row: dict[str, str]) -> bool:
    if (row.get("verified") or "").strip().upper() == "Y":
        return True
    mode = (row.get("game_link_mode") or "").strip().lower()
    if mode in {"single", "multi", "stream_map"}:
        return True
    if is_dual_leg_video_row(row):
        return True
    from scripts.amiga.tournament_videos.apply_review import ROW_PATCHES

    patch = ROW_PATCHES.get(row.get("youtube_id", ""), {})
    return bool((patch.get("game_id_guess") or "").strip())


def heuristic_resolve_allowed(row: dict[str, str]) -> bool:
    if (row.get("kind") or "").strip() != "match":
        return False
    if is_game_link_locked(row):
        return False
    return not (row.get("game_id_guess") or "").strip()


@lru_cache(maxsize=1)
def _load_sidecar_index() -> dict[str, list[MatchFactLink]]:
    path = VIDEO_GAME_LINKS_CSV
    if not path.is_file():
        return {}
    out: dict[str, list[MatchFactLink]] = {}
    with path.open(encoding="utf-8", newline="") as fh:
        for raw in csv.DictReader(fh):
            yt = (raw.get("youtube_id") or "").strip()
            if not yt:
                continue
            ordinal = int((raw.get("link_ordinal") or "1").strip() or "1")
            out.setdefault(yt, []).append(
                MatchFactLink(
                    youtube_id=yt,
                    ordinal=ordinal,
                    tournament_label=normalize_display_name(raw.get("tournament_label") or ""),
                    player_a=normalize_display_name(raw.get("player_a") or ""),
                    player_b=normalize_display_name(raw.get("player_b") or ""),
                    score=(raw.get("score") or "").strip(),
                    stage=(raw.get("stage") or "").strip().lower(),
                    leg=_int_leg(raw.get("leg")),
                    start_sec=_int_start_sec(raw.get("start_sec")),
                )
            )
    for yt in out:
        out[yt].sort(key=lambda link: link.ordinal)
    return out


def sidecar_links_for_video(youtube_id: str) -> list[MatchFactLink]:
    return list(_load_sidecar_index().get(youtube_id, []))


def _single_fact_from_row(row: dict[str, str], ordinal: int = 1) -> MatchFactLink:
    return MatchFactLink(
        youtube_id=row.get("youtube_id", ""),
        ordinal=ordinal,
        tournament_label=normalize_display_name(row.get("tournament_guess_label") or ""),
        player_a=normalize_display_name(row.get("player_a_guess") or ""),
        player_b=normalize_display_name(row.get("player_b_guess") or ""),
        score=(row.get("score") or "").strip(),
        stage=(row.get("stage") or "").strip().lower(),
        leg=_int_leg(row.get("leg")),
    )


def _games_for_pair_stage(
    tournament_id: int,
    player_a_id: int,
    player_b_id: int,
    stage: str,
    cache: dict[int, list[GameRow]] | None = None,
) -> list[GameRow]:
    if cache is None:
        cache = {}
    if tournament_id not in cache:
        cache[tournament_id] = load_tournament_games(tournament_id)
    phase = _phase_hint(stage)
    out: list[GameRow] = []
    for g in cache[tournament_id]:
        if {g.player_a_id, g.player_b_id} != {player_a_id, player_b_id}:
            continue
        if phase and not _phase_matches(g.phase, phase):
            continue
        out.append(g)
    return sorted(out, key=lambda g: g.game_id)


def dual_leg_fact_links(row: dict[str, str], snap: DbSnapshot) -> list[MatchFactLink]:
    label = normalize_display_name(row.get("tournament_guess_label") or "")
    tid = lookup_tournament_id(label, snap)
    if tid is None:
        tid_raw = (row.get("guessed_tournament_id") or "").strip()
        tid = int(tid_raw) if tid_raw.isdigit() else None
    pa_name = normalize_display_name(row.get("player_a_guess") or "")
    pb_name = normalize_display_name(row.get("player_b_guess") or "")
    pa = lookup_player_id(pa_name, snap)
    pb = lookup_player_id(pb_name, snap)
    if not tid or not pa or not pb:
        return [_single_fact_from_row(row)]
    stage = (row.get("stage") or "").strip().lower()
    games = _games_for_pair_stage(tid, pa, pb, stage)
    if len(games) != 2:
        return [_single_fact_from_row(row)]
    links: list[MatchFactLink] = []
    for idx, g in enumerate(games, start=1):
        links.append(
            MatchFactLink(
                youtube_id=row.get("youtube_id", ""),
                ordinal=idx,
                tournament_label=label,
                player_a=snap.players_by_id.get(g.player_a_id, pa_name),
                player_b=snap.players_by_id.get(g.player_b_id, pb_name),
                score=(row.get("score") or "").strip(),
                stage=stage,
                leg=idx,
            )
        )
    return links


def editorial_links_for_row(row: dict[str, str], snap: DbSnapshot) -> list[MatchFactLink]:
    yt = row.get("youtube_id", "")
    sidecar = sidecar_links_for_video(yt)
    if sidecar:
        return sidecar
    mode = (row.get("game_link_mode") or "").strip().lower()
    if mode == "stream_map":
        return []
    if is_dual_leg_video_row(row) and not _int_leg(row.get("leg")):
        return dual_leg_fact_links(row, snap)
    return [_single_fact_from_row(row)]


def _score_matches_game(link: MatchFactLink, game: GameRow) -> bool:
    parsed = _parse_score(link.score)
    if parsed is None:
        return True
    ga, gb = parsed
    pa_id = None
    pb_id = None
    # Names resolved at verify time via snap on game row orientation
    if game.player_a_id and game.player_b_id:
        if (game.goals_a, game.goals_b) == (ga, gb):
            return True
        if (game.goals_a, game.goals_b) == (gb, ga):
            return True
    return False


def resolve_fact_link(
    link: MatchFactLink,
    snap: DbSnapshot,
    cache: dict[int, list[GameRow]] | None = None,
) -> tuple[int | None, str | None]:
    tid = lookup_tournament_id(link.tournament_label, snap)
    if tid is None:
        return None, f"tournament not found: {link.tournament_label!r}"
    pa = lookup_player_id(link.player_a, snap)
    pb = lookup_player_id(link.player_b, snap)
    if not pa or not pb:
        return None, f"player not found: {link.player_a!r} vs {link.player_b!r}"

    if link.score:
        if cache is None:
            cache = {}
        if tid not in cache:
            cache[tid] = load_tournament_games(tid)
        gid, note = match_game(
            cache[tid],
            player_a_id=pa,
            player_b_id=pb,
            score=link.score,
            stage=link.stage,
            leg=link.leg,
        )
        if gid is None:
            return None, note or "no score match"
        return gid, None

    games = _games_for_pair_stage(tid, pa, pb, link.stage, cache)
    if not games:
        return None, "no pair at event for stage"
    if link.leg in (1, 2) and len(games) >= link.leg:
        return games[link.leg - 1].game_id, None
    if len(games) == 1:
        return games[0].game_id, None
    return None, f"ambiguous ({len(games)} games, no score)"


def resolve_editorial_game_ids(
    row: dict[str, str],
    snap: DbSnapshot,
    cache: dict[int, list[GameRow]] | None = None,
) -> tuple[list[int], list[str]]:
    links = editorial_links_for_row(row, snap)
    if not links:
        if is_stream_map_row(row):
            return [], ["stream_map sidecar empty"]
        return [], ["no editorial links"]
    ids: list[int] = []
    notes: list[str] = []
    for link in links:
        gid, err = resolve_fact_link(link, snap, cache)
        if gid is None:
            notes.append(err or "unresolved link")
            return [], notes
        ids.append(gid)
    return ids, notes


def manifest_game_start_sec(youtube_id: str, game_ids: list[int]) -> list[int | None] | None:
    """Parallel start_sec values from sidecar (ordinal order), when any are set."""
    links = sidecar_links_for_video(youtube_id)
    if not links or not game_ids:
        return None
    ordered = sorted(links, key=lambda link: link.ordinal)
    if len(ordered) != len(game_ids):
        return None
    starts = [link.start_sec for link in ordered]
    if not any(s is not None for s in starts):
        return None
    return starts


def sync_row_sidecar_game_ids(
    row: dict[str, str],
    snap: DbSnapshot,
    cache: dict[int, list[GameRow]] | None = None,
) -> tuple[list[str], list[str]]:
    """Refresh game_id_guess from video_game_links.csv when sidecar or stream_map applies."""
    yt = row.get("youtube_id", "")
    changes: list[str] = []
    escalations: list[str] = []
    has_sidecar = row_has_sidecar_links(yt)
    if not has_sidecar and not is_stream_map_row(row):
        return changes, escalations

    if is_stream_map_row(row) and not has_sidecar:
        if is_game_link_locked(row):
            escalations.append(f"{yt}: stream_map but sidecar empty")
        return changes, escalations

    old_gids = parse_game_ids(row.get("game_id_guess"))
    new_ids, notes = resolve_editorial_game_ids(row, snap, cache)
    locked = is_game_link_locked(row) or is_stream_map_row(row)

    if locked:
        if not new_ids:
            escalations.append(f"{yt}: sidecar remap failed — {'; '.join(notes)}")
            return changes, escalations
        if old_gids and len(new_ids) < len(old_gids):
            escalations.append(
                f"{yt}: refused sidecar shrink {old_gids} -> {new_ids} ({'; '.join(notes)})"
            )
            return changes, escalations
    elif not new_ids:
        return changes, escalations

    new_gids = ",".join(str(i) for i in new_ids)
    if (row.get("game_id_guess") or "").strip() != new_gids:
        row["game_id_guess"] = new_gids
        changes.append(f"{yt}: game_id_guess from sidecar -> {new_gids}")
    return changes, escalations


def validate_sidecar_schema(csv_rows: list[dict[str, str]] | None = None) -> list[str]:
    """Read-only checks on video_game_links.csv before sync/build."""
    path = VIDEO_GAME_LINKS_CSV
    if not path.is_file():
        return []
    known_yt = None
    if csv_rows is not None:
        known_yt = {r.get("youtube_id", "") for r in csv_rows if r.get("youtube_id")}
    issues: list[str] = []
    seen_ord: dict[str, set[int]] = {}
    with path.open(encoding="utf-8", newline="") as fh:
        for raw in csv.DictReader(fh):
            yt = (raw.get("youtube_id") or "").strip()
            if not yt:
                issues.append("sidecar row missing youtube_id")
                continue
            if known_yt is not None and yt not in known_yt:
                issues.append(f"{yt}: sidecar youtube_id not in review.csv")
            ordinal = int((raw.get("link_ordinal") or "1").strip() or "1")
            seen_ord.setdefault(yt, set())
            if ordinal in seen_ord[yt]:
                issues.append(f"{yt}: duplicate sidecar link_ordinal {ordinal}")
            seen_ord[yt].add(ordinal)
    return issues


def remap_row_game_ids(
    row: dict[str, str],
    snap: DbSnapshot,
    cache: dict[int, list[GameRow]] | None = None,
) -> tuple[list[int], list[str]]:
    """Remap cached game ids from editorial facts (locked / verified rows)."""
    return resolve_editorial_game_ids(row, snap, cache)


def game_row_from_snap(snap: DbSnapshot, game_id: int) -> GameRow | None:
    raw = snap.games_by_id.get(game_id)
    if not raw:
        return None
    return GameRow(
        int(raw["id"]),
        int(raw["player_a_id"]),
        int(raw["player_b_id"]),
        int(raw.get("goals_a", 0)),
        int(raw.get("goals_b", 0)),
        str(raw.get("phase") or ""),
    )


def audit_row_links(
    row: dict[str, str],
    snap: DbSnapshot,
    *,
    manifest_game_ids: list[int] | None = None,
    cache: dict[int, list[GameRow]] | None = None,
) -> list[LinkAuditIssue]:
    yt = row.get("youtube_id", "")
    issues: list[LinkAuditIssue] = []
    kind = (row.get("kind") or "").strip()
    if not row_needs_game_link_audit(row):
        return issues

    cached = manifest_game_ids if manifest_game_ids is not None else parse_game_ids(row.get("game_id_guess"))
    if not cached:
        if kind == "match" and (row.get("player_a_guess") or row.get("player_b_guess")):
            issues.append(
                LinkAuditIssue(yt, "error", "missing_game_ids", "match row has players but no game_ids")
            )
            return issues
        if is_stream_map_row(row) and is_game_link_locked(row):
            issues.append(
                LinkAuditIssue(yt, "error", "stream_map_empty", "stream_map row has no resolved game_ids")
            )
            return issues
        if row_has_sidecar_links(yt) and is_game_link_locked(row):
            issues.append(
                LinkAuditIssue(yt, "error", "sidecar_unresolved", "sidecar links did not resolve to game_ids")
            )
            return issues
        if not cached:
            return issues

    links = editorial_links_for_row(row, snap)
    expected, resolve_notes = resolve_editorial_game_ids(row, snap, cache)
    if not expected and cached:
        for gid in cached:
            if gid not in snap.games_by_id:
                issues.append(LinkAuditIssue(yt, "error", "stale_id", f"game_id {gid} not in amiga_games"))
        return issues

    if resolve_notes and not expected:
        for note in resolve_notes:
            issues.append(LinkAuditIssue(yt, "error", "unresolved", note))
        return issues

    if expected and cached != expected:
        issues.append(
            LinkAuditIssue(
                yt,
                "error",
                "id_mismatch",
                f"cached {cached} != fact-resolved {expected}",
            )
        )

    sidecar = row_has_sidecar_links(yt)
    if is_dual_leg_video_row(row) and len(expected) < 2 and not sidecar:
        issues.append(
            LinkAuditIssue(
                yt,
                "error",
                "dual_leg_count",
                f"dual-leg row resolved to {len(expected)} game(s), expected 2",
            )
        )

    if sidecar and expected and len(expected) != len(sidecar_links_for_video(yt)):
        issues.append(
            LinkAuditIssue(
                yt,
                "error",
                "sidecar_count",
                f"resolved {len(expected)} game(s) vs {len(sidecar_links_for_video(yt))} sidecar row(s)",
            )
        )

    if len(cached) > 1 and expected and len(expected) != len(cached):
        issues.append(
            LinkAuditIssue(
                yt,
                "error",
                "multi_count",
                f"cached {len(cached)} ids vs resolved {len(expected)}",
            )
        )

    for link in links:
        if not link.score:
            continue
        # resolve this link only
        gid, err = resolve_fact_link(link, snap, cache)
        if gid is None:
            issues.append(LinkAuditIssue(yt, "error", "score_link", err or "unresolved"))
            continue
        game = game_row_from_snap(snap, gid)
        if game and not _score_matches_game(link, game):
            issues.append(
                LinkAuditIssue(
                    yt,
                    "error",
                    "score_mismatch",
                    f"link ord={link.ordinal} score {link.score!r} != game {gid} ({game.goals_a}-{game.goals_b})",
                )
            )

    for gid in cached:
        if gid not in snap.games_by_id:
            issues.append(LinkAuditIssue(yt, "error", "stale_id", f"game_id {gid} not in amiga_games"))
            continue
        g = snap.games_by_id[gid]
        tid = int((row.get("guessed_tournament_id") or "0").strip() or "0")
        if tid and int(g["tournament_id"]) != tid:
            issues.append(
                LinkAuditIssue(
                    yt,
                    "error",
                    "tournament_mismatch",
                    f"game {gid} tournament_id {g['tournament_id']} != row {tid}",
                )
            )

    if not links and cached:
        # Legacy single-id rows without full facts: player pair check on first id
        pa_raw = (row.get("player_a_id_guess") or "").strip()
        pb_raw = (row.get("player_b_id_guess") or "").strip()
        if pa_raw.isdigit() and pb_raw.isdigit() and cached[0] in snap.games_by_id:
            g = snap.games_by_id[cached[0]]
            if not player_pair_matches(int(pa_raw), int(pb_raw), int(g["player_a_id"]), int(g["player_b_id"])):
                issues.append(LinkAuditIssue(yt, "error", "pair_mismatch", "manifest players != cached game pair"))

    return issues


def audit_catalog(
    snap: DbSnapshot,
    csv_rows: list[dict[str, str]],
    manifest_videos: list[dict[str, Any]] | None = None,
) -> list[LinkAuditIssue]:
    manifest_by_yt = {}
    if manifest_videos:
        manifest_by_yt = {str(v.get("youtube_id")): v for v in manifest_videos if v.get("youtube_id")}
    cache: dict[int, list[GameRow]] = {}
    issues: list[LinkAuditIssue] = []
    for row in csv_rows:
        if not row_needs_game_link_audit(row):
            continue
        yt = row.get("youtube_id", "")
        manifest_ids = None
        if yt in manifest_by_yt:
            manifest_ids = [int(x) for x in (manifest_by_yt[yt].get("game_ids") or [])]
        issues.extend(audit_row_links(row, snap, manifest_game_ids=manifest_ids, cache=cache))
    return issues