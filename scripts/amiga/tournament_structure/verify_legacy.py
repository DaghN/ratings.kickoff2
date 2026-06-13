"""Verify legacy tournament structure after materialize (ko2amiga_db).

Policy: docs/amiga-tournament-structure-policy.md §4–5, T9.
"""

from __future__ import annotations

import argparse
import json
import sys
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

import pymysql

from scripts.amiga.tournament_standings import compute_tournament_standings
from scripts.amiga.tournament_structure.materialize_legacy import (
    AUTO_RR,
    NEEDS_STRUCTURE_REVIEW,
    STRUCTURE_REVIEW_TOURNAMENT_IDS,
    _is_generated_tournament,
    _load_games,
    _load_tournament,
    _parse_overrides,
    classify_null_phase_tournament,
    round_robin_legs,
)
from scripts.amiga.tournament_structure.registry import registry_entry_for_catalog

TIER_A = "A"
TIER_B = "B"
TIER_C = "C"
TIER_D = "D"


@dataclass
class VerifyLegacyResult:
    tournament_id: int
    tournament_name: str
    tier: str
    tier_detail: str
    materialized: bool
    game_count: int
    linked_count: int
    stage_count: int
    fixture_count: int
    ok: bool
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    standings_ok: bool | None = None

    def to_dict(self) -> dict[str, Any]:
        return {
            "tournament_id": self.tournament_id,
            "tournament_name": self.tournament_name,
            "tier": self.tier,
            "tier_detail": self.tier_detail,
            "materialized": self.materialized,
            "game_count": self.game_count,
            "linked_count": self.linked_count,
            "stage_count": self.stage_count,
            "fixture_count": self.fixture_count,
            "ok": self.ok,
            "errors": self.errors,
            "warnings": self.warnings,
            "standings_ok": self.standings_ok,
        }


def classify_legacy_tier(
    games: list[dict[str, Any]],
    *,
    tournament_name: str,
    format_overrides: dict[str, Any],
    tournament_id: int | None = None,
) -> tuple[str, str]:
    """Return (tier letter, human detail) for policy §4."""
    entry = registry_entry_for_catalog(tournament_name)
    if entry is not None and entry.status == "active":
        return TIER_D, f"curated StructureSpec ({entry.spec.template_slug})"

    if _is_generated_tournament(format_overrides):
        return TIER_D, "curated format_overrides (structure_spec / generated)"

    if not games:
        return TIER_C, "no games"

    all_null_phase = all(not g.get("phase") or not str(g.get("phase")).strip() for g in games)
    if not all_null_phase:
        return TIER_B, "labeled phases on games"

    if tournament_id is not None and tournament_id in STRUCTURE_REVIEW_TOURNAMENT_IDS:
        return TIER_C, "manual structure review (audit flag)"

    legs = round_robin_legs(games)
    if legs is not None:
        leg_label = "single" if legs == 1 else f"{legs}×"
        return TIER_A, f"NULL phase + {leg_label} round-robin schedule"

    null_tier = classify_null_phase_tournament(games)
    if null_tier == NEEDS_STRUCTURE_REVIEW:
        return TIER_C, "NULL phase + not complete RR (needs_structure_review)"
    return TIER_C, null_tier


def _standings_snapshot_key(row: dict[str, Any]) -> tuple[int, str, str]:
    return (
        int(row["player_id"]),
        str(row["scope_type"]),
        str(row.get("scope_key") or ""),
    )


def _standings_compare(
    stored: list[dict[str, Any]],
    computed: list[dict[str, Any]],
) -> list[str]:
    """Return mismatch messages; empty list means parity."""
    stored_map = {_standings_snapshot_key(r): r for r in stored}
    computed_map = {_standings_snapshot_key(r): r for r in computed}
    errors: list[str] = []

    all_keys = sorted(set(stored_map) | set(computed_map))
    stat_cols = ("position", "games", "wins", "draws", "losses", "goals_for", "goals_against", "points")
    for key in all_keys:
        pid, scope_type, scope_key = key
        s_row = stored_map.get(key)
        c_row = computed_map.get(key)
        if s_row is None:
            errors.append(
                f"standings missing stored row player={pid} {scope_type!r}/{scope_key!r}"
            )
            continue
        if c_row is None:
            errors.append(
                f"standings extra stored row player={pid} {scope_type!r}/{scope_key!r}"
            )
            continue
        for col in stat_cols:
            if int(s_row[col]) != int(c_row[col]):
                errors.append(
                    f"standings mismatch player={pid} {scope_type!r}/{scope_key!r} "
                    f"{col}: stored={s_row[col]} computed={c_row[col]}"
                )
                break
    return errors


def _load_games_for_standings(conn: pymysql.connections.Connection, tournament_id: int) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id,
                   g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id,
                   g.fixture_id,
                   f.phase_label AS fixture_phase_label,
                   s.stage_key, s.name AS stage_name, s.stage_type, s.track_key
            FROM amiga_games g
            LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
            LEFT JOIN tournament_stages s ON s.id = f.stage_id
            WHERE g.tournament_id = %s
            ORDER BY g.source_scores_id ASC, g.id ASC
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def verify_legacy_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    check_standings: bool = False,
) -> VerifyLegacyResult:
    """Audit one tournament's legacy structure integrity."""
    tournament = _load_tournament(conn, tournament_id)
    overrides = _parse_overrides(tournament.get("format_overrides"))
    games = _load_games(conn, tournament_id)
    tier, tier_detail = classify_legacy_tier(
        games,
        tournament_name=str(tournament["name"]),
        format_overrides=overrides,
        tournament_id=tournament_id,
    )

    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s",
            (tournament_id,),
        )
        stage_count = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        fixture_count = int(cur.fetchone()["n"])

    linked_count = sum(1 for g in games if g.get("fixture_id") is not None)
    materialized = stage_count > 0 or linked_count > 0

    result = VerifyLegacyResult(
        tournament_id=tournament_id,
        tournament_name=str(tournament["name"]),
        tier=tier,
        tier_detail=tier_detail,
        materialized=materialized,
        game_count=len(games),
        linked_count=linked_count,
        stage_count=stage_count,
        fixture_count=fixture_count,
        ok=True,
    )

    if not materialized:
        result.warnings.append("not materialized — no fixture/stage integrity checks applied")
        if check_standings and games:
            result.standings_ok = _run_standings_check(conn, tournament_id, result)
        return result

    errors = _verify_materialized_integrity(conn, tournament_id, games, fixture_count)
    result.errors.extend(errors)
    result.ok = len(result.errors) == 0

    if check_standings and games:
        result.standings_ok = _run_standings_check(conn, tournament_id, result)
        if result.standings_ok is False:
            result.ok = False

    return result


def _run_standings_check(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    result: VerifyLegacyResult,
) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT tournament_id, player_id, scope_type, scope_key,
                   position, games, wins, draws, losses,
                   goals_for, goals_against, points
            FROM amiga_tournament_standings
            WHERE tournament_id = %s
            ORDER BY scope_type, scope_key, position
            """,
            (tournament_id,),
        )
        stored = list(cur.fetchall())

    games = _load_games_for_standings(conn, tournament_id)
    computed = compute_tournament_standings(games)
    mismatches = _standings_compare(stored, computed)
    if mismatches:
        result.errors.extend(mismatches[:20])
        if len(mismatches) > 20:
            result.errors.append(f"... and {len(mismatches) - 20} more standings mismatches")
        return False
    return True


def _verify_materialized_integrity(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    games: list[dict[str, Any]],
    fixture_count: int,
) -> list[str]:
    errors: list[str] = []
    game_count = len(games)

    if linked_count := sum(1 for g in games if g.get("fixture_id") is not None):
        if linked_count != game_count:
            errors.append(
                f"{game_count - linked_count} game(s) missing fixture_id "
                f"({linked_count}/{game_count} linked)"
            )
    else:
        errors.append("materialized tournament has stages/fixtures but no linked games")

    if fixture_count != game_count and game_count > 0:
        errors.append(
            f"fixture/game count mismatch: {fixture_count} fixture(s), {game_count} game(s)"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id AS game_id
            FROM amiga_games g
            LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id
            WHERE g.tournament_id = %s
              AND g.fixture_id IS NOT NULL
              AND f.id IS NULL
            ORDER BY g.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(f"game {row['game_id']} has orphan fixture_id (fixture missing)")

        cur.execute(
            """
            SELECT g.id AS game_id, g.tournament_id AS game_tournament_id,
                   s.tournament_id AS fixture_tournament_id
            FROM amiga_games g
            INNER JOIN tournament_fixtures f ON f.id = g.fixture_id
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE g.tournament_id = %s
              AND g.tournament_id <> s.tournament_id
            ORDER BY g.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(
                f"game {row['game_id']} tournament {row['game_tournament_id']} "
                f"!= fixture tournament {row['fixture_tournament_id']}"
            )

        cur.execute(
            """
            SELECT g.id AS game_id
            FROM amiga_games g
            INNER JOIN tournament_fixtures f ON f.id = g.fixture_id
            WHERE g.tournament_id = %s
              AND f.player_a_id IS NOT NULL
              AND f.player_b_id IS NOT NULL
              AND NOT (
                (g.player_a_id = f.player_a_id AND g.player_b_id = f.player_b_id)
                OR (g.player_a_id = f.player_b_id AND g.player_b_id = f.player_a_id)
              )
            ORDER BY g.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(f"game {row['game_id']} players do not match fixture sides")

        cur.execute(
            """
            SELECT g.id AS game_id, f.stage_id
            FROM amiga_games g
            INNER JOIN tournament_fixtures f ON f.id = g.fixture_id
            WHERE g.tournament_id = %s
              AND (f.stage_id IS NULL OR f.stage_id = 0)
            ORDER BY g.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(f"game {row['game_id']} fixture has no stage_id")

        cur.execute(
            """
            SELECT f.id AS fixture_id, f.status, COUNT(g.id) AS game_count
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            LEFT JOIN amiga_games g ON g.fixture_id = f.id
            WHERE s.tournament_id = %s
            GROUP BY f.id, f.status
            HAVING (f.status = 'played' AND game_count <> 1)
                OR (f.status = 'scheduled' AND game_count <> 0)
            ORDER BY f.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(
                f"fixture {row['fixture_id']} status {row['status']!r} "
                f"has {row['game_count']} game(s)"
            )

        cur.execute(
            """
            SELECT f.id AS fixture_id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            LEFT JOIN amiga_games g ON g.fixture_id = f.id
            WHERE s.tournament_id = %s
              AND g.id IS NULL
            ORDER BY f.id
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            errors.append(f"fixture {row['fixture_id']} has no linked game")

    return errors


def audit_legacy_tier_inventory(
    conn: pymysql.connections.Connection,
    *,
    imported_only: bool = True,
    min_games: int = 1,
) -> dict[str, Any]:
    """Classify imported tournaments into policy tiers A/B/C/D."""
    where = "WHERE EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)"
    if imported_only:
        where += " AND t.source_id IS NOT NULL"
    if min_games > 1:
        where += (
            f" AND (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) >= {int(min_games)}"
        )

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT t.id, t.name, t.format_overrides
            FROM tournaments t
            {where}
            ORDER BY t.id
            """
        )
        tournaments = list(cur.fetchall())

    by_tier: dict[str, list[dict[str, Any]]] = {TIER_A: [], TIER_B: [], TIER_C: [], TIER_D: []}
    materialized_count = 0

    for row in tournaments:
        tid = int(row["id"])
        games = _load_games(conn, tid)
        overrides = _parse_overrides(row.get("format_overrides"))
        tier, tier_detail = classify_legacy_tier(
            games,
            tournament_name=str(row["name"]),
            format_overrides=overrides,
            tournament_id=tid,
        )

        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s",
                (tid,),
            )
            stage_count = int(cur.fetchone()["n"])
            cur.execute(
                """
                SELECT COUNT(*) AS n FROM amiga_games
                WHERE tournament_id = %s AND fixture_id IS NOT NULL
                """,
                (tid,),
            )
            linked = int(cur.fetchone()["n"])

        materialized = stage_count > 0 or linked > 0
        if materialized:
            materialized_count += 1

        by_tier[tier].append(
            {
                "tournament_id": tid,
                "name": str(row["name"]),
                "tier_detail": tier_detail,
                "game_count": len(games),
                "materialized": materialized,
                "stage_count": stage_count,
                "linked_games": linked,
            }
        )

    counts = {tier: len(rows) for tier, rows in by_tier.items()}
    return {
        "audit": "legacy_tier_inventory",
        "imported_only": imported_only,
        "min_games": min_games,
        "tournament_count": len(tournaments),
        "materialized_count": materialized_count,
        "tier_counts": counts,
        "tiers": by_tier,
    }


def main_verify_legacy(argv: list[str] | None = None) -> int:
    from scripts.amiga.tournament_structure.materialize_legacy import _connect

    parser = argparse.ArgumentParser(description="Verify legacy tournament structure integrity")
    parser.add_argument("--tournament-id", type=int, required=True)
    parser.add_argument(
        "--check-standings",
        action="store_true",
        help="Compare stored standings to compute_tournament_standings snapshot",
    )
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv if argv is not None else [])

    conn = _connect()
    try:
        result = verify_legacy_tournament(
            conn,
            args.tournament_id,
            check_standings=args.check_standings,
        )
    finally:
        conn.close()

    if args.json:
        print(json.dumps(result.to_dict(), indent=2))
    elif result.ok:
        mat = "materialized" if result.materialized else "not materialized"
        print(
            f"OK: {result.tournament_name!r} (id={result.tournament_id}) "
            f"tier {result.tier} — {mat}; "
            f"games={result.game_count} stages={result.stage_count} "
            f"fixtures={result.fixture_count} linked={result.linked_count}"
        )
        for warn in result.warnings:
            print(f"  warn: {warn}")
        if result.standings_ok is True:
            print("  standings: OK")
    else:
        print(
            f"FAIL: {result.tournament_name!r} (id={result.tournament_id}) tier {result.tier}",
            file=sys.stderr,
        )
        for err in result.errors:
            print(f"  - {err}", file=sys.stderr)
        for warn in result.warnings:
            print(f"  warn: {warn}", file=sys.stderr)

    return 0 if result.ok else 1


def main_audit_inventory(argv: list[str] | None = None) -> int:
    from scripts.amiga.tournament_structure.materialize_legacy import _connect

    parser = argparse.ArgumentParser(description="Tier A/B/C/D inventory for imported tournaments")
    parser.add_argument("--all-tournaments", action="store_true", help="Include non-imported events")
    parser.add_argument("--min-games", type=int, default=1)
    parser.add_argument("--tier", choices=[TIER_A, TIER_B, TIER_C, TIER_D], default=None)
    parser.add_argument("--out", type=Path, default=None, help="Write JSON report to file")
    parser.add_argument("--json", action="store_true", help="Print full JSON to stdout")
    args = parser.parse_args(argv if argv is not None else [])

    conn = _connect()
    try:
        report = audit_legacy_tier_inventory(
            conn,
            imported_only=not args.all_tournaments,
            min_games=args.min_games,
        )
    finally:
        conn.close()

    if args.tier is not None:
        filtered = {
            "audit": report["audit"],
            "tier": args.tier,
            "count": len(report["tiers"][args.tier]),
            "tournaments": report["tiers"][args.tier],
        }
        payload = json.dumps(filtered, indent=2) + "\n"
    else:
        summary = {
            "audit": report["audit"],
            "tournament_count": report["tournament_count"],
            "materialized_count": report["materialized_count"],
            "tier_counts": report["tier_counts"],
        }
        payload = json.dumps(summary if not args.json else report, indent=2) + "\n"

    if args.out:
        args.out.parent.mkdir(parents=True, exist_ok=True)
        full = json.dumps(report, indent=2) + "\n"
        args.out.write_text(full, encoding="utf-8")
        print(f"Wrote {args.out} ({report['tournament_count']} tournaments)")

    if args.json or args.out is None:
        print(payload, end="")

    return 0


if __name__ == "__main__":
    raise SystemExit(main_verify_legacy())
