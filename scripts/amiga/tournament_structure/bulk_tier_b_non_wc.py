"""Bulk materialize slice-6 non-WC tier-B cups (auto-OK ids only)."""

from __future__ import annotations

import argparse
import json
import logging
import random
import sys
from dataclasses import dataclass, field
from typing import Any

import pymysql

from scripts.amiga.tournament_standings import rebuild_standings_for_tournament
from scripts.amiga.tournament_structure.materialize_legacy import (
    StructureReviewRequired,
    _connect,
    _count_existing_stages,
    _load_tournament,
    materialize_legacy_fixtures,
)
from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
    DEFERRED_WORLD_CUP_TOURNAMENT_IDS,
    NON_WC_PILOT_TOURNAMENT_IDS,
    NON_WC_TIER_B_AUTO_MATERIALIZE_IDS,
    is_slice_6_auto_ok,
)
from scripts.amiga.tournament_structure.verify_legacy import verify_legacy_tournament

log = logging.getLogger(__name__)

BULK_EXCLUDE_TOURNAMENT_IDS: frozenset[int] = frozenset({
    137,  # Homburg — tier D curated
})


@dataclass
class BulkTierBNonWcResult:
    dry_run: bool
    candidate_count: int
    processed: int = 0
    materialized: int = 0
    failed: int = 0
    standings_rebuilt: int = 0
    failures: list[dict[str, Any]] = field(default_factory=list)
    materialized_ids: list[int] = field(default_factory=list)
    verify_sample: list[dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "dry_run": self.dry_run,
            "candidate_count": self.candidate_count,
            "processed": self.processed,
            "materialized": self.materialized,
            "failed": self.failed,
            "standings_rebuilt": self.standings_rebuilt,
            "failures": self.failures,
            "materialized_ids": self.materialized_ids,
            "verify_sample": self.verify_sample,
        }


def _is_materialized(conn: pymysql.connections.Connection, tournament_id: int) -> bool:
    if _count_existing_stages(conn, tournament_id) > 0:
        return True
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n FROM amiga_games
            WHERE tournament_id = %s AND fixture_id IS NOT NULL
            """,
            (tournament_id,),
        )
        return int(cur.fetchone()["n"]) > 0


def tier_b_non_wc_candidate_ids(
    conn: pymysql.connections.Connection,
    *,
    skip_materialized: bool = True,
    exclude_ids: frozenset[int] = BULK_EXCLUDE_TOURNAMENT_IDS,
) -> list[int]:
    """Return sorted slice-6 bulk allow list (41 ids), optionally skipping materialized."""
    ids: list[int] = []
    for tid in sorted(NON_WC_TIER_B_AUTO_MATERIALIZE_IDS):
        if tid in exclude_ids:
            continue
        if tid in DEFERRED_WORLD_CUP_TOURNAMENT_IDS:
            continue
        tournament = _load_tournament(conn, tid)
        name = str(tournament["name"])
        if not is_slice_6_auto_ok(tid, name):
            raise RuntimeError(
                f"register inconsistency: id={tid} {name!r} is in "
                "NON_WC_TIER_B_AUTO_MATERIALIZE_IDS but fails is_slice_6_auto_ok()"
            )
        if skip_materialized and _is_materialized(conn, tid):
            continue
        ids.append(tid)
    return ids


def bulk_materialize_tier_b_non_wc(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    rebuild_standings: bool = False,
    verify_sample_size: int = 0,
    limit: int | None = None,
    seed: int = 42,
    skip_materialized: bool = True,
) -> BulkTierBNonWcResult:
    """Materialize only NON_WC_TIER_B_AUTO_MATERIALIZE_IDS (slice 6)."""
    candidates = tier_b_non_wc_candidate_ids(conn, skip_materialized=skip_materialized)
    if limit is not None:
        candidates = candidates[: max(0, limit)]

    result = BulkTierBNonWcResult(
        dry_run=dry_run,
        candidate_count=len(candidates),
    )

    for tid in candidates:
        result.processed += 1
        try:
            mat = materialize_legacy_fixtures(conn, tid, dry_run=dry_run)
            if dry_run:
                conn.rollback()
            else:
                conn.commit()
                if rebuild_standings:
                    rebuild_standings_for_tournament(conn, tid)
                    result.standings_rebuilt += 1

            result.materialized += 1
            result.materialized_ids.append(tid)
            log.info(
                "bulk_tier_b_non_wc %s tournament_id=%s %r stages=%s fixtures=%s",
                "dry_run" if dry_run else "applied",
                tid,
                mat.tournament_name,
                mat.stages_created,
                mat.fixtures_created,
            )
        except (ValueError, StructureReviewRequired) as exc:
            conn.rollback()
            result.failed += 1
            result.failures.append({"tournament_id": tid, "error": str(exc)})
            log.warning("bulk_tier_b_non_wc skip tournament_id=%s: %s", tid, exc)
        except Exception as exc:
            conn.rollback()
            result.failed += 1
            result.failures.append({"tournament_id": tid, "error": repr(exc)})
            log.exception("bulk_tier_b_non_wc fail tournament_id=%s", tid)

    if verify_sample_size > 0 and result.materialized_ids and not dry_run:
        rng = random.Random(seed)
        sample_ids = rng.sample(
            result.materialized_ids,
            min(verify_sample_size, len(result.materialized_ids)),
        )
        gate_ids = {pid for pid in NON_WC_PILOT_TOURNAMENT_IDS if pid in NON_WC_TIER_B_AUTO_MATERIALIZE_IDS}
        for gid in sorted(gate_ids):
            if gid in result.materialized_ids and gid not in sample_ids:
                sample_ids.append(gid)
        for tid in sorted(set(sample_ids)):
            vr = verify_legacy_tournament(conn, tid, check_standings=True)
            result.verify_sample.append(vr.to_dict())

    return result


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Bulk materialize slice-6 non-WC tier-B cups (auto-OK ids only)",
    )
    parser.add_argument("--dry-run", action="store_true", help="Preview; roll back each tournament")
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Commit materialize (after GATE E)",
    )
    parser.add_argument(
        "--rebuild-standings",
        action="store_true",
        help="Rebuild standings per tournament (apply only)",
    )
    parser.add_argument(
        "--verify-sample",
        type=int,
        default=0,
        metavar="N",
        help="After --apply, verify-legacy on N random ids (+ GATE E pilots)",
    )
    parser.add_argument("--limit", type=int, default=None)
    parser.add_argument("--seed", type=int, default=42)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv if argv is not None else [])

    if args.dry_run and args.apply:
        print("FAIL: use --dry-run or --apply, not both", file=sys.stderr)
        return 1
    if not args.dry_run and not args.apply:
        print(
            "FAIL: materialize-tier-b-non-wc requires --dry-run or --apply",
            file=sys.stderr,
        )
        return 1
    if args.rebuild_standings and not args.apply:
        print("FAIL: --rebuild-standings requires --apply", file=sys.stderr)
        return 1
    if args.verify_sample and not args.apply:
        print("FAIL: --verify-sample requires --apply", file=sys.stderr)
        return 1

    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    conn = _connect()
    try:
        bulk = bulk_materialize_tier_b_non_wc(
            conn,
            dry_run=args.dry_run,
            rebuild_standings=args.rebuild_standings,
            verify_sample_size=args.verify_sample,
            limit=args.limit,
            seed=args.seed,
        )
    finally:
        conn.close()

    if args.json:
        print(json.dumps(bulk.to_dict(), indent=2))
    else:
        mode = "DRY RUN" if args.dry_run else "APPLIED"
        print(
            f"{mode} tier-B non-WC bulk: candidates={bulk.candidate_count} "
            f"materialized={bulk.materialized} failed={bulk.failed} "
            f"standings_rebuilt={bulk.standings_rebuilt}"
        )
        if bulk.failures:
            print("Failures:", file=sys.stderr)
            for row in bulk.failures[:20]:
                print(f"  id={row['tournament_id']}: {row['error']}", file=sys.stderr)
        if bulk.verify_sample:
            ok = sum(1 for row in bulk.verify_sample if row.get("ok"))
            print(f"verify sample: {ok}/{len(bulk.verify_sample)} OK")

    return 0 if bulk.failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
