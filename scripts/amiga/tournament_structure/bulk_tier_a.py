"""Bulk materialize tier-A NULL-phase round-robin tournaments (slice 5)."""

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
    MaterializeResult,
    StructureReviewRequired,
    _connect,
    materialize_legacy_fixtures,
)
from scripts.amiga.tournament_structure.verify_legacy import (
    TIER_A,
    audit_legacy_tier_inventory,
    verify_legacy_tournament,
)

log = logging.getLogger(__name__)

# Never bulk-touch curated / manual-review events (belt-and-suspenders).
BULK_EXCLUDE_TOURNAMENT_IDS: frozenset[int] = frozenset({
    74,   # Athens IV Cup — tier C cup
    137,  # Homburg — tier D curated
    # STRUCTURE_REVIEW_TOURNAMENT_IDS union handled in materialize_legacy
})


@dataclass
class BulkTierAResult:
    dry_run: bool
    candidate_count: int
    processed: int = 0
    materialized: int = 0
    skipped: int = 0
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
            "skipped": self.skipped,
            "failed": self.failed,
            "standings_rebuilt": self.standings_rebuilt,
            "failures": self.failures,
            "materialized_ids": self.materialized_ids,
            "verify_sample": self.verify_sample,
        }


def tier_a_candidate_ids(
    conn: pymysql.connections.Connection,
    *,
    skip_materialized: bool = True,
    exclude_ids: frozenset[int] = BULK_EXCLUDE_TOURNAMENT_IDS,
) -> list[int]:
    """Return sorted tier-A tournament ids eligible for bulk materialize."""
    report = audit_legacy_tier_inventory(conn)
    ids: list[int] = []
    for row in report["tiers"][TIER_A]:
        tid = int(row["tournament_id"])
        if tid in exclude_ids:
            continue
        if skip_materialized and row["materialized"]:
            continue
        ids.append(tid)
    return sorted(ids)


def bulk_materialize_tier_a(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    rebuild_standings: bool = False,
    verify_sample_size: int = 0,
    limit: int | None = None,
    seed: int = 42,
    skip_materialized: bool = True,
) -> BulkTierAResult:
    """Materialize all tier-A tournaments not yet fixture-backed."""
    candidates = tier_a_candidate_ids(conn, skip_materialized=skip_materialized)
    if limit is not None:
        candidates = candidates[: max(0, limit)]

    result = BulkTierAResult(
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
                "bulk_tier_a %s tournament_id=%s %r stages=%s fixtures=%s",
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
            log.warning("bulk_tier_a skip tournament_id=%s: %s", tid, exc)
        except Exception as exc:
            conn.rollback()
            result.failed += 1
            result.failures.append({"tournament_id": tid, "error": repr(exc)})
            log.exception("bulk_tier_a fail tournament_id=%s", tid)

    if verify_sample_size > 0 and result.materialized_ids and not dry_run:
        rng = random.Random(seed)
        sample_ids = rng.sample(
            result.materialized_ids,
            min(verify_sample_size, len(result.materialized_ids)),
        )
        gate_ids = {1, 318}  # Jerez XI 2× RR; Milan XXIII 1× RR
        for gid in sorted(gate_ids):
            if gid in result.materialized_ids and gid not in sample_ids:
                sample_ids.append(gid)
        for tid in sorted(set(sample_ids)):
            vr = verify_legacy_tournament(conn, tid, check_standings=True)
            result.verify_sample.append(vr.to_dict())

    return result


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Bulk materialize tier-A NULL-phase round-robin tournaments",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Simulate all candidates; roll back each tournament",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Commit materialize (required for live bulk; use after GATE C)",
    )
    parser.add_argument(
        "--rebuild-standings",
        action="store_true",
        help="Rebuild amiga_tournament_standings after each tournament (apply only)",
    )
    parser.add_argument(
        "--verify-sample",
        type=int,
        default=0,
        metavar="N",
        help="After --apply, run verify-legacy on N random ids (+ GATE C anchors)",
    )
    parser.add_argument("--limit", type=int, default=None, help="Process first N candidates only")
    parser.add_argument("--seed", type=int, default=42, help="RNG seed for verify sample")
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args(argv if argv is not None else [])

    if args.dry_run and args.apply:
        print("FAIL: use --dry-run or --apply, not both", file=sys.stderr)
        return 1
    if not args.dry_run and not args.apply:
        print(
            "FAIL: bulk tier-A requires --dry-run (preview) or --apply (live after GATE C)",
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
        bulk = bulk_materialize_tier_a(
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
            f"{mode} tier-A bulk: candidates={bulk.candidate_count} "
            f"materialized={bulk.materialized} failed={bulk.failed} "
            f"standings_rebuilt={bulk.standings_rebuilt}"
        )
        if bulk.failures:
            print("Failures:", file=sys.stderr)
            for row in bulk.failures[:20]:
                print(f"  id={row['tournament_id']}: {row['error']}", file=sys.stderr)
            if len(bulk.failures) > 20:
                print(f"  ... +{len(bulk.failures) - 20} more", file=sys.stderr)
        if bulk.verify_sample:
            ok = sum(1 for row in bulk.verify_sample if row.get("ok"))
            print(f"verify sample: {ok}/{len(bulk.verify_sample)} OK")
            for row in bulk.verify_sample:
                if not row.get("ok"):
                    print(
                        f"  FAIL id={row['tournament_id']} {row['tournament_name']!r}",
                        file=sys.stderr,
                    )

    return 0 if bulk.failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
