#!/usr/bin/env python3
"""CLI: python -m scripts.amiga import | replay | run"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.import_access import _DEFAULT_MDB, import_all
from scripts.amiga.replay import run_replay
from scripts.amiga.standings_parity import main as standings_parity_main
from scripts.amiga.verify_track_b import main as verify_track_b_main
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.audit_catalog_dates import main as audit_catalog_dates_main

log = logging.getLogger("scripts.amiga")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Amiga realm import + Elo replay (ko2amiga_db)")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_import = sub.add_parser("import", help="Load Access ground truth into MySQL")
    p_import.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_import.add_argument("--recreate-schema", action="store_true", help="Apply 001_core.sql first")

    p_replay = sub.add_parser("replay", help="Elo replay (1600 / K=32)")
    p_replay.add_argument("--dry-run", action="store_true")
    p_replay.add_argument("--limit", type=int, default=None)

    p_run = sub.add_parser("run", help="import + replay")
    p_run.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_run.add_argument("--recreate-schema", action="store_true")
    p_run.add_argument("--dry-run", action="store_true")
    p_run.add_argument("--limit", type=int, default=None)

    p_parity = sub.add_parser(
        "standings-parity",
        help="Compare derived standings to Access Tables (reference only)",
    )
    p_parity.add_argument("--tournament", help="Single tournament (omit with --sweep)")
    p_parity.add_argument("--scope", choices=("overall", "group"), default="overall")
    p_parity.add_argument("--scope-key", default="")
    p_parity.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_parity.add_argument("--top", type=int, default=10)
    p_parity.add_argument(
        "--sweep",
        action="store_true",
        help="Sweep all tournaments with Access reference standings",
    )
    p_parity.add_argument("--tournament-id", type=int, default=None)
    p_parity.add_argument("--fail-fast", action="store_true")
    p_parity.add_argument("--only-failures", action="store_true")
    p_parity.add_argument("--report", type=Path, default=None)

    sub.add_parser("verify-track-b", help="Post-import smoke: extra column + Elo draw rule")

    sub.add_parser(
        "verify-chronology",
        help="Assert 0 backward game_date transitions in canonical order",
    )

    sub.add_parser(
        "verify-import-manifest",
        help="Assert import_manifest.json and catalog overrides in MySQL",
    )

    sub.add_parser(
        "audit-catalog-dates",
        help="Scan Access for chrono/date inversions; fail if uncorrected",
    )

    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")

    if args.cmd == "import":
        stats = import_all(mdb=args.mdb, recreate_schema=args.recreate_schema)
        log.info("Import complete: %s", stats)
        return 0

    if args.cmd == "replay":
        run_replay(dry_run=args.dry_run, limit=args.limit)
        return 0

    if args.cmd == "run":
        stats = import_all(mdb=args.mdb, recreate_schema=args.recreate_schema)
        log.info("Import complete: %s", stats)
        run_replay(dry_run=args.dry_run, limit=args.limit)
        return 0

    if args.cmd == "verify-track-b":
        return verify_track_b_main()

    if args.cmd == "verify-chronology":
        return verify_chronology_main()

    if args.cmd == "verify-import-manifest":
        return verify_import_manifest_main()

    if args.cmd == "audit-catalog-dates":
        return audit_catalog_dates_main()

    if args.cmd == "standings-parity":
        parity_argv: list[str] = [
            "--scope",
            args.scope,
            "--scope-key",
            args.scope_key,
            "--mdb",
            str(args.mdb),
            "--top",
            str(args.top),
        ]
        if args.sweep:
            parity_argv.append("--sweep")
        if args.tournament:
            parity_argv.extend(["--tournament", args.tournament])
        if args.tournament_id is not None:
            parity_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.fail_fast:
            parity_argv.append("--fail-fast")
        if args.only_failures:
            parity_argv.append("--only-failures")
        if args.report is not None:
            parity_argv.extend(["--report", str(args.report)])
        return standings_parity_main(parity_argv)

    return 1


if __name__ == "__main__":
    sys.exit(main())
