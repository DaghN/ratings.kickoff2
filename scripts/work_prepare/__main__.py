"""CLI: python -m scripts.work_prepare <verb> --target local-work [--dry-run]"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.work_prepare.migrate import migrate_work
from scripts.work_prepare.parity import print_parity_report, run_parity_checks
from scripts.work_prepare.prepare import prepare_fast, prepare_full
from scripts.work_prepare.refresh import refresh_work
from scripts.work_prepare.seed_catalog import seed_milestone_definitions
from scripts.work_prepare.targets import load_target
from scripts.work_prepare.zero_derived import zero_derived


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Work DB prepare platform v2 (docs/work-db-prepare.md).",
    )
    parser.add_argument(
        "verb",
        choices=("prepare", "refresh-work", "migrate-work", "seed-catalog", "zero-derived", "parity"),
        help="prepare=orchestrator; refresh/migrate/seed-catalog/zero-derived=steps; parity=read-only checks",
    )
    parser.add_argument(
        "--target",
        default="local-work",
        help="Profile: local-work (default), staging-work (see work-targets.ini.example)",
    )
    parser.add_argument("--dry-run", action="store_true", help="Log only; no writes")
    parser.add_argument(
        "--full",
        action="store_true",
        help="prepare only: refresh → migrate → zero derived (default for prepare)",
    )
    parser.add_argument(
        "--zero-only",
        action="store_true",
        help="prepare only: zero derived without refresh/migrate",
    )
    parser.add_argument("-v", "--verbose", action="store_true")
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(levelname)s %(message)s",
    )

    target = load_target(args.target)

    if args.verb == "prepare":
        if args.zero_only:
            prepare_fast(target, dry_run=args.dry_run)
        else:
            prepare_full(target, dry_run=args.dry_run)
        if not args.dry_run:
            sys.exit(print_parity_report(run_parity_checks(target)))
        return

    if args.verb == "refresh-work":
        refresh_work(target, dry_run=args.dry_run)
    elif args.verb == "migrate-work":
        migrate_work(target, dry_run=args.dry_run)
    elif args.verb == "seed-catalog":
        seed_milestone_definitions(target, dry_run=args.dry_run)
    elif args.verb == "zero-derived":
        zero_derived(target, dry_run=args.dry_run)
    elif args.verb == "parity":
        sys.exit(print_parity_report(run_parity_checks(target)))


if __name__ == "__main__":
    main()
