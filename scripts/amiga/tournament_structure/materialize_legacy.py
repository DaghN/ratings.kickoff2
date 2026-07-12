"""Materialize tournament stages and fixtures from imported legacy games.

Policy: docs/amiga-tournament-structure-policy.md (T8–T9, T13).
Legacy path: games are ground truth → one fixture per game → assign fixture to stage.
Fixture = one match, one result. KO tie = one stage; multi-leg = multiple fixtures in that stage.
"""

from __future__ import annotations

import argparse
import json
import logging
import re
import sys
from dataclasses import dataclass, field
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config, require_amiga_ground_database
from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_phases import ScopeType, parse_phase
from scripts.amiga.tournament_structure.specs import STAGE_TYPE_KNOCKOUT, STAGE_TYPE_ROUND_ROBIN

log = logging.getLogger(__name__)

MATERIALIZE_SOURCE = "scripts.amiga.tournament_structure.materialize_legacy"

AUTO_RR = "auto_rr"
NEEDS_STRUCTURE_REVIEW = "needs_structure_review"

# Tournaments that pass coarse RR math but fail audit — tier C until curated.
STRUCTURE_REVIEW_TOURNAMENT_IDS: frozenset[int] = frozenset({
})

_GENERATED_BY_PREFIXES = (
    "scripts.amiga.tournament_builder",
    "site.public_html.amiga.ops.fixtures",
)


@dataclass(frozen=True, slots=True)
class StageBucket:
    stage_key: str
    name: str
    stage_type: str
    sequence_no: int = 0


class StructureReviewRequired(ValueError):
    """Tournament cannot be auto-materialized — needs manual StructureSpec or triage."""


@dataclass
class MaterializeResult:
    tournament_id: int
    tournament_name: str
    stages_created: int = 0
    fixtures_created: int = 0
    games_linked: int = 0
    dry_run: bool = False
    stage_summary: list[dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "tournament_id": self.tournament_id,
            "tournament_name": self.tournament_name,
            "stages_created": self.stages_created,
            "fixtures_created": self.fixtures_created,
            "games_linked": self.games_linked,
            "dry_run": self.dry_run,
            "stage_summary": self.stage_summary,
        }


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    require_amiga_ground_database(cfg, operation="materialize")
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _slug_key(text: str, *, fallback: str = "stage") -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", str(text).lower()).strip("-")
    return slug or fallback


def _full_round_robin_game_count(player_count: int) -> int:
    if player_count < 2:
        return 0
    return player_count * (player_count - 1) // 2


def _distinct_player_ids(games: list[dict[str, Any]]) -> set[int]:
    players: set[int] = set()
    for game in games:
        players.add(int(game["player_a_id"]))
        players.add(int(game["player_b_id"]))
    return players


def _player_game_counts(games: list[dict[str, Any]]) -> dict[int, int]:
    counts: dict[int, int] = {}
    for game in games:
        for pid in (int(game["player_a_id"]), int(game["player_b_id"])):
            counts[pid] = counts.get(pid, 0) + 1
    return counts


def _missing_single_rr_pairings(games: list[dict[str, Any]]) -> set[tuple[int, int]]:
    """Pairings absent from one full round-robin leg over distinct players."""
    players = sorted(_distinct_player_ids(games))
    played: set[tuple[int, int]] = set()
    for game in games:
        a, b = int(game["player_a_id"]), int(game["player_b_id"])
        played.add((min(a, b), max(a, b)))
    expected: set[tuple[int, int]] = set()
    for i, a in enumerate(players):
        for b in players[i + 1 :]:
            expected.add((a, b))
    return expected - played


def _force_ok_incomplete_null_rr(games: list[dict[str, Any]]) -> bool:
    """Human --force guard for near-complete NULL-phase RR (policy T11)."""
    counts = _player_game_counts(games)
    spread = max(counts.values()) - min(counts.values())
    if spread <= 1:
        return True
    if spread != 2:
        return False
    min_count = min(counts.values())
    min_players = [pid for pid, count in counts.items() if count == min_count]
    if len(min_players) != 1:
        return False
    victim = min_players[0]
    missing = _missing_single_rr_pairings(games)
    return bool(missing) and all(victim in pair for pair in missing)


def round_robin_legs(games: list[dict[str, Any]]) -> int | None:
    """Return k when NULL-phase games form k complete RR legs with equal per-player games."""
    player_count = len(_distinct_player_ids(games))
    if player_count < 2:
        return None
    single_leg = _full_round_robin_game_count(player_count)
    if single_leg <= 0:
        return None
    actual = len(games)
    if actual % single_leg != 0:
        return None
    legs = actual // single_leg
    if legs < 1:
        return None
    counts = _player_game_counts(games)
    if len(counts) != player_count:
        return None
    per_player_values = set(counts.values())
    if len(per_player_values) != 1:
        return None
    expected_per_player = (player_count - 1) * legs
    if next(iter(per_player_values)) != expected_per_player:
        return None
    return legs


def classify_null_phase_tournament(games: list[dict[str, Any]]) -> str:
    """Tier A (auto_rr) vs tier C (needs_structure_review) for all-NULL-phase events."""
    if round_robin_legs(games) is not None:
        return AUTO_RR
    return NEEDS_STRUCTURE_REVIEW


def null_phase_round_robin_bucket() -> StageBucket:
    """Single RR scope for tier-A NULL-phase marathons."""
    return StageBucket(
        stage_key="overall",
        name="Overall",
        stage_type=STAGE_TYPE_ROUND_ROBIN,
    )


def _knockout_tie_bucket(game: dict[str, Any], *, phase_label: str) -> StageBucket:
    """One knockout module per two-player tie (policy T3)."""
    player_a_id = int(game["player_a_id"])
    player_b_id = int(game["player_b_id"])
    lo, hi = min(player_a_id, player_b_id), max(player_a_id, player_b_id)
    label = phase_label.strip() or "Knockout"
    stage_key = f"ko-{_slug_key(label, fallback='knockout')}-{lo}-{hi}"
    return StageBucket(
        stage_key=stage_key,
        name=label,
        stage_type=STAGE_TYPE_KNOCKOUT,
    )


def stage_bucket_for_game(
    game: dict[str, Any],
    *,
    all_null_phase: bool,
) -> StageBucket:
    if all_null_phase:
        raise RuntimeError("use null_phase_round_robin_bucket() for tier-A NULL-phase tournaments")

    scope = parse_phase(game.get("phase"))
    if scope.scope_type == ScopeType.KNOCKOUT:
        label = scope.scope_key or "Knockout"
        return _knockout_tie_bucket(game, phase_label=label)

    if scope.scope_key == "":
        return StageBucket(
            stage_key="overall",
            name="Overall",
            stage_type=STAGE_TYPE_ROUND_ROBIN,
        )

    label = scope.scope_key
    return StageBucket(
        stage_key=_slug_key(label),
        name=label,
        stage_type=STAGE_TYPE_ROUND_ROBIN,
    )


def _bucket_key(bucket: StageBucket) -> tuple[str, str]:
    return bucket.stage_key, bucket.stage_type


def _load_tournament(conn: pymysql.connections.Connection, tournament_id: int) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, name, source_id, format_overrides
            FROM tournaments
            WHERE id = %s
            """,
            (tournament_id,),
        )
        row = cur.fetchone()
    if row is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    return row


def _load_games(conn: pymysql.connections.Connection, tournament_id: int) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, tournament_id, player_a_id, player_b_id, goals_a, goals_b,
                   phase, fixture_id, source_scores_id
            FROM amiga_games
            WHERE tournament_id = %s
            ORDER BY source_scores_id ASC, id ASC
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def _parse_overrides(raw: Any) -> dict[str, Any]:
    if raw is None or raw == "":
        return {}
    if isinstance(raw, dict):
        return raw
    return json.loads(str(raw))


def _is_generated_tournament(overrides: dict[str, Any]) -> bool:
    generated_by = str(overrides.get("generated_by", ""))
    if any(generated_by.startswith(prefix) for prefix in _GENERATED_BY_PREFIXES):
        return True
    if overrides.get("structure_spec"):
        return True
    return False


def _count_existing_stages(conn: pymysql.connections.Connection, tournament_id: int) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s",
            (tournament_id,),
        )
        return int(cur.fetchone()["n"])


def _clear_tournament_structure(conn: pymysql.connections.Connection, tournament_id: int) -> None:
    with conn.cursor() as cur:
        cur.execute("DELETE FROM tournament_stages WHERE tournament_id = %s", (tournament_id,))


def dematerialize_legacy_fixtures(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> MaterializeResult:
    """Remove legacy-materialized stages/fixtures; null ``fixture_id`` via FK cascade."""
    tournament = _load_tournament(conn, tournament_id)
    overrides = _parse_overrides(tournament.get("format_overrides"))
    if _is_generated_tournament(overrides):
        raise ValueError(
            f"tournament_id={tournament_id} looks generated/curated — "
            "refusing legacy dematerialize"
        )

    stage_count = _count_existing_stages(conn, tournament_id)
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s AND fixture_id IS NOT NULL",
            (tournament_id,),
        )
        linked = int(cur.fetchone()["n"])

    if stage_count == 0 and linked == 0:
        raise ValueError(f"tournament_id={tournament_id} has no legacy structure to remove")

    _clear_tournament_structure(conn, tournament_id)
    return MaterializeResult(
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
        stages_created=-stage_count,
        fixtures_created=0,
        games_linked=-linked,
        dry_run=dry_run,
    )


def _import_create_fixture(
    conn: pymysql.connections.Connection,
    *,
    stage_id: int,
    fixture_key: str,
    player_a_id: int,
    player_b_id: int,
    leg_no: int,
    phase_label: str | None,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tournament_fixtures
                (stage_id, fixture_key, player_a_id, player_b_id, leg_no, status, phase_label)
            VALUES (%s, %s, %s, %s, %s, 'played', %s)
            ON DUPLICATE KEY UPDATE
                player_a_id = VALUES(player_a_id),
                player_b_id = VALUES(player_b_id),
                leg_no = VALUES(leg_no),
                status = VALUES(status),
                phase_label = VALUES(phase_label)
            """,
            (stage_id, fixture_key, player_a_id, player_b_id, leg_no, phase_label),
        )
        cur.execute(
            "SELECT id FROM tournament_fixtures WHERE stage_id = %s AND fixture_key = %s",
            (stage_id, fixture_key),
        )
        return int(cur.fetchone()["id"])


def materialize_legacy_fixtures(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
    replace: bool = False,
    force: bool = False,
) -> MaterializeResult:
    """Create stages + one fixture per legacy game; link ``amiga_games.fixture_id``."""
    tournament = _load_tournament(conn, tournament_id)
    overrides = _parse_overrides(tournament.get("format_overrides"))
    if _is_generated_tournament(overrides):
        raise ValueError(
            f"tournament_id={tournament_id} looks generated/curated — "
            "refusing legacy materialize (use structure spec path)"
        )

    games = _load_games(conn, tournament_id)
    if not games:
        raise ValueError(f"tournament_id={tournament_id} has no games")

    from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
        all_structure_review_tournament_ids,
        is_parser_fix_deferred,
    )

    if tournament_id in all_structure_review_tournament_ids():
        raise StructureReviewRequired(
            f"tournament_id={tournament_id} ({tournament['name']!r}) is flagged for "
            "manual structure review (structure review register). "
            "Add a StructureSpec or remove the flag after triage."
        )

    if is_parser_fix_deferred(tournament_id):
        raise StructureReviewRequired(
            f"tournament_id={tournament_id} ({tournament['name']!r}) is in "
            "NON_WC_PARSER_FIX_FIRST_IDS — slice **6a** (parser fix) before materialize. "
            "Not part of slice 6 bulk. Fix tournament_phases.py, re-run curate_tier_b_non_wc, "
            "then materialize in slice 6a only."
        )

    existing_stages = _count_existing_stages(conn, tournament_id)
    if existing_stages and not replace:
        raise ValueError(
            f"tournament_id={tournament_id} already has {existing_stages} stage(s) — "
            "pass replace=True to rebuild"
        )
    if existing_stages and replace:
        _clear_tournament_structure(conn, tournament_id)

    all_null_phase = all(not g.get("phase") or not str(g.get("phase")).strip() for g in games)

    if all_null_phase:
        if tournament_id in STRUCTURE_REVIEW_TOURNAMENT_IDS:
            raise StructureReviewRequired(
                f"tournament_id={tournament_id} ({tournament['name']!r}) is flagged for "
                "manual structure review (STRUCTURE_REVIEW_TOURNAMENT_IDS). "
                "Add a StructureSpec or remove the flag after triage."
            )
        tier = classify_null_phase_tournament(games)
        if tier != AUTO_RR:
            legs = round_robin_legs(games)
            if not force:
                raise StructureReviewRequired(
                    f"tournament_id={tournament_id} ({tournament['name']!r}) has NULL phases and "
                    f"is not a complete multi-leg round-robin schedule "
                    f"(legs={legs!r}) — needs_structure_review (policy T11). "
                    "Add a StructureSpec or classify manually; do not auto-infer knockout."
                )
            if not _force_ok_incomplete_null_rr(games):
                counts = _player_game_counts(games)
                spread = max(counts.values()) - min(counts.values())
                raise StructureReviewRequired(
                    f"tournament_id={tournament_id} ({tournament['name']!r}) force refused: "
                    f"per-player game spread={spread} — not a near-complete RR withdrawal "
                    "(±1 game or single early exit with spread=2)."
                )
            log.warning(
                "materialize_legacy force: tournament_id=%s incomplete NULL-phase RR "
                "(legs=%r, players=%s, games=%s)",
                tournament_id,
                legs,
                len(_distinct_player_ids(games)),
                len(games),
            )

    bucket_map: dict[tuple[str, str], StageBucket] = {}
    games_by_bucket: dict[tuple[str, str], list[dict[str, Any]]] = {}
    if all_null_phase:
        bucket = null_phase_round_robin_bucket()
        key = _bucket_key(bucket)
        bucket_map[key] = bucket
        games_by_bucket[key] = list(games)
    else:
        for game in games:
            bucket = stage_bucket_for_game(game, all_null_phase=False)
            key = _bucket_key(bucket)
            bucket_map.setdefault(key, bucket)
            games_by_bucket.setdefault(key, []).append(game)

    ordered_buckets = sorted(
        bucket_map.values(),
        key=lambda b: (0 if b.stage_type == STAGE_TYPE_ROUND_ROBIN else 1, b.stage_key),
    )
    stage_id_by_key: dict[str, int] = {}
    result = MaterializeResult(
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
        dry_run=dry_run,
    )

    for seq, bucket in enumerate(ordered_buckets, start=1):
        stage_id = create_stage(
            conn,
            tournament_id=tournament_id,
            stage_key=bucket.stage_key,
            name=bucket.name,
            stage_type=bucket.stage_type,
            sequence_no=seq,
            config={
                "materialized_by": MATERIALIZE_SOURCE,
                "legacy_import": True,
                "phase_provenance": "null" if all_null_phase else "labeled",
            },
        )
        stage_id_by_key[bucket.stage_key] = stage_id
        result.stages_created += 1
        result.stage_summary.append(
            {
                "stage_key": bucket.stage_key,
                "name": bucket.name,
                "stage_type": bucket.stage_type,
                "game_count": len(games_by_bucket[_bucket_key(bucket)]),
            }
        )

    fixture_seq = 0
    for bucket in ordered_buckets:
        stage_id = stage_id_by_key[bucket.stage_key]
        for game in games_by_bucket[_bucket_key(bucket)]:
            fixture_seq += 1
            player_a_id = int(game["player_a_id"])
            player_b_id = int(game["player_b_id"])
            phase_label = game.get("phase")
            if phase_label is not None:
                phase_label = str(phase_label).strip() or None
            # KO standings scope prefers stage display name (Final, Semi Finals, …).
            # Witness g.phase may not normalize (e.g. "Finals" plural) — leave phase_label NULL.
            if bucket.stage_type == STAGE_TYPE_KNOCKOUT:
                phase_label = None
            fixture_key = f"legacy-g{int(game['id'])}"
            fixture_id = _import_create_fixture(
                conn,
                stage_id=stage_id,
                fixture_key=fixture_key,
                player_a_id=player_a_id,
                player_b_id=player_b_id,
                leg_no=1,
                phase_label=phase_label,
            )
            result.fixtures_created += 1
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE amiga_games SET fixture_id = %s WHERE id = %s",
                    (fixture_id, int(game["id"])),
                )
            result.games_linked += 1

    log.info(
        "materialize_legacy_fixtures tournament_id=%s stages=%s fixtures=%s games=%s dry_run=%s",
        tournament_id,
        result.stages_created,
        result.fixtures_created,
        result.games_linked,
        dry_run,
    )
    return result


def _main_preview_pure_knockout(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Preview pure knockout handler grouping")
    parser.add_argument("--tournament-id", type=int, required=True)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)
    from scripts.amiga.tournament_structure.pure_knockout import preview_cli

    return preview_cli(args.tournament_id, as_json=args.json)


def _main_materialize_pure_knockout(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Materialize pure knockout handler")
    parser.add_argument("--tournament-id", type=int, required=True)
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--replace", action="store_true")
    parser.add_argument("--force", action="store_true", help="Apply despite preflight warnings (dev)")
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)

    conn = _connect()
    try:
        from scripts.amiga.tournament_structure.pure_knockout import materialize_pure_knockout

        result = materialize_pure_knockout(
            conn,
            args.tournament_id,
            dry_run=args.dry_run,
            replace=args.replace,
            force=args.force,
        )
        if args.dry_run:
            conn.rollback()
            print("DRY RUN: rolled back")
        else:
            conn.commit()
    except (ValueError, StructureReviewRequired) as exc:
        conn.rollback()
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    finally:
        conn.close()

    if args.json:
        print(json.dumps(result.to_dict(), indent=2))
    else:
        print(
            f"{'DRY RUN ' if result.dry_run else ''}"
            f"pure_knockout {result.tournament_name!r} (id={result.tournament_id}): "
            f"{result.stages_created} stage(s), {result.fixtures_created} fixture(s), "
            f"{result.games_linked} game(s) linked"
        )
    return 0


def _main_generate_disposition_register(argv: list[str]) -> int:
    from pathlib import Path

    parser = argparse.ArgumentParser(description="Generate disposition_register.json bootstrap")
    parser.add_argument("--out", type=str, default=None)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)

    from scripts.amiga.tournament_structure.disposition_register import (
        REGISTER_PATH,
        generate_register,
    )

    out = Path(args.out) if args.out else REGISTER_PATH
    conn = _connect()
    try:
        reg = generate_register(conn)
        reg.save(out)
        by_handler: dict[str, int] = {}
        for row in reg.rows.values():
            by_handler[row.handler] = by_handler.get(row.handler, 0) + 1
        summary = {"path": str(out), "count": len(reg.rows), "by_handler": by_handler}
    finally:
        conn.close()

    if args.json:
        print(json.dumps(summary, indent=2))
    else:
        print(f"Wrote {out} ({summary['count']} tournaments)")
        for h, n in sorted(by_handler.items()):
            print(f"  {h}: {n}")
    return 0


def _main_verify_disposition_register(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description="Verify disposition register coverage")
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)

    from scripts.amiga.tournament_structure.disposition_register import (
        DispositionRegister,
        verify_register,
    )

    conn = _connect()
    try:
        reg = DispositionRegister.load()
        report = verify_register(conn, reg)
    finally:
        conn.close()

    if args.json:
        print(json.dumps(report, indent=2))
    else:
        print(
            f"catalog={report['catalog_count']} register={report['register_count']} "
            f"ok={report['ok']}"
        )
        if report["missing_ids"]:
            mids = report["missing_ids"]
            print(f"MISSING: {mids[:20]}{'…' if len(mids) > 20 else ''}")
        for h, n in sorted(report.get("by_handler", {}).items()):
            print(f"  {h}: {n}")
    return 0 if report["ok"] else 1


def _main_audit_review_register(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(
        description="Find materialized tournaments still in structure-review block lists",
    )
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv)

    from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
        audit_stale_structure_review_register,
    )

    conn = _connect()
    try:
        report = audit_stale_structure_review_register(conn)
    finally:
        conn.close()

    if args.json:
        print(json.dumps(report, indent=2))
    else:
        print(f"stale_count={report['stale_count']} ok={report['ok']}")
        for row in report["review_frozenset_materialized"]:
            print(
                f"  frozenset+materialized: {row['tournament_id']} {row['name']} "
                f"({row['stages']} stages) — {row['action']}"
            )
        for row in report["pending_review_materialized"]:
            print(
                f"  pending_review+materialized: {row['tournament_id']} {row['name']} "
                f"({row['stages']} stages) — {row['action']}"
            )
    return 0 if report["ok"] else 1


def main(argv: list[str] | None = None) -> int:
    if argv and argv[0] == "verify-legacy":
        from scripts.amiga.tournament_structure.verify_legacy import main_verify_legacy

        return main_verify_legacy(argv[1:])
    if argv and argv[0] == "audit-inventory":
        from scripts.amiga.tournament_structure.verify_legacy import main_audit_inventory

        return main_audit_inventory(argv[1:])
    if argv and argv[0] == "materialize-tier-a":
        from scripts.amiga.tournament_structure.bulk_tier_a import main as bulk_tier_a_main

        return bulk_tier_a_main(argv[1:])
    if argv and argv[0] == "materialize-tier-b-non-wc":
        from scripts.amiga.tournament_structure.bulk_tier_b_non_wc import main as bulk_tier_b_main

        return bulk_tier_b_main(argv[1:])
    if argv and argv[0] == "preview-pure-knockout":
        return _main_preview_pure_knockout(argv[1:])
    if argv and argv[0] == "materialize-pure-knockout":
        return _main_materialize_pure_knockout(argv[1:])
    if argv and argv[0] == "generate-disposition-register":
        return _main_generate_disposition_register(argv[1:])
    if argv and argv[0] == "verify-disposition-register":
        return _main_verify_disposition_register(argv[1:])
    if argv and argv[0] == "audit-review-register":
        return _main_audit_review_register(argv[1:])

    parser = argparse.ArgumentParser(description="Legacy tournament structure materialize")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_mat = sub.add_parser(
        "materialize",
        help="Create stages + fixtures from legacy games (one fixture per game)",
    )
    p_mat.add_argument("--tournament-id", type=int, required=True)
    p_mat.add_argument("--dry-run", action="store_true")
    p_mat.add_argument(
        "--replace",
        action="store_true",
        help="Delete existing stages/fixtures for this tournament first",
    )
    p_mat.add_argument(
        "--force",
        action="store_true",
        help="Apply despite T11 incomplete near-complete NULL-phase RR (human-approved)",
    )
    p_mat.add_argument("--json", action="store_true")

    p_dem = sub.add_parser(
        "dematerialize",
        help="Remove legacy-materialized stages/fixtures for a tournament",
    )
    p_dem.add_argument("--tournament-id", type=int, required=True)
    p_dem.add_argument("--dry-run", action="store_true")
    p_dem.add_argument("--json", action="store_true")

    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")

    conn = _connect()
    try:
        if args.cmd == "materialize":
            result = materialize_legacy_fixtures(
                conn,
                args.tournament_id,
                dry_run=args.dry_run,
                replace=args.replace,
                force=args.force,
            )
        elif args.cmd == "dematerialize":
            result = dematerialize_legacy_fixtures(
                conn,
                args.tournament_id,
                dry_run=args.dry_run,
            )
        else:
            return 1

        if args.dry_run:
            conn.rollback()
            print("DRY RUN: rolled back")
        else:
            conn.commit()
    except (ValueError, StructureReviewRequired) as exc:
        conn.rollback()
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    finally:
        conn.close()

    if args.json:
        print(json.dumps(result.to_dict(), indent=2))
    elif args.cmd == "materialize":
        print(
            f"{'DRY RUN ' if result.dry_run else ''}"
            f"materialized {result.tournament_name!r} (id={result.tournament_id}): "
            f"{result.stages_created} stage(s), {result.fixtures_created} fixture(s), "
            f"{result.games_linked} game(s) linked"
        )
        for row in result.stage_summary:
            print(
                f"  - {row['stage_key']} ({row['stage_type']}): {row['game_count']} game(s)"
            )
    else:
        print(
            f"{'DRY RUN ' if result.dry_run else ''}"
            f"dematerialized {result.tournament_name!r} (id={result.tournament_id}): "
            f"removed {abs(result.stages_created)} stage(s), unlinked {abs(result.games_linked)} game(s)"
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
