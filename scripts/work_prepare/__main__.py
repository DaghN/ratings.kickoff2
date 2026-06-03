"""CLI: python -m scripts.work_prepare <verb> --target local-work [--dry-run]"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.work_prepare.ab_post_game import main_ab_post_game, register_ab_post_game_parser
from scripts.work_prepare.migrate import migrate_work
from scripts.work_prepare.parity import print_parity_report, run_parity_checks
from scripts.work_prepare.prepare import prepare_fast, prepare_full
from scripts.work_prepare.refresh import refresh_work
from scripts.work_prepare.seed_catalog import seed_milestone_definitions
from scripts.work_prepare.targets import load_target
from scripts.work_prepare.zero_derived import zero_derived


def _common_parent() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(add_help=False)
    p.add_argument(
        "--target",
        default="local-work",
        help="Profile: local-work (default), staging-work (see work-targets.ini.example)",
    )
    p.add_argument("--dry-run", action="store_true", help="Log only; no writes")
    p.add_argument("-v", "--verbose", action="store_true")
    return p


def main() -> None:
    common = _common_parent()
    parser = argparse.ArgumentParser(
        description="Work DB prepare platform v2 (docs/work-db-prepare.md).",
        parents=[common],
    )
    sub = parser.add_subparsers(dest="verb", required=True)

    p_prepare = sub.add_parser(
        "prepare",
        parents=[common],
        help="prepare=orchestrator; refresh/migrate/seed/zero-derived",
    )
    p_prepare.add_argument(
        "--full",
        action="store_true",
        help="refresh → migrate → zero derived (default for prepare)",
    )
    p_prepare.add_argument(
        "--zero-only",
        action="store_true",
        help="zero derived without refresh/migrate",
    )

    for name, help_text in (
        ("refresh-work", "clone baseline → work"),
        ("migrate-work", "schema/migrations on work"),
        ("seed-catalog", "milestone_definitions seed"),
        ("zero-derived", "§4 zero derived + aggregate truncates"),
        ("parity", "read-only ground-truth / day-zero checks"),
    ):
        sub.add_parser(name, parents=[common], help=help_text)

    register_ab_post_game_parser(sub, parents=[common])

    args = parser.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(levelname)s %(message)s",
    )

    if args.verb == "ab-post-game":
        sys.exit(main_ab_post_game(args))

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
