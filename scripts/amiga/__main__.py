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
    p_parity.add_argument("--tournament", required=True)
    p_parity.add_argument("--scope", choices=("overall", "group"), default="overall")
    p_parity.add_argument("--scope-key", default="")
    p_parity.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_parity.add_argument("--top", type=int, default=10)

    sub.add_parser("verify-track-b", help="Post-import smoke: extra column + Elo draw rule")

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

    if args.cmd == "standings-parity":
        return standings_parity_main(
            [
                "--tournament",
                args.tournament,
                "--scope",
                args.scope,
                "--scope-key",
                args.scope_key,
                "--mdb",
                str(args.mdb),
                "--top",
                str(args.top),
            ]
        )

    return 1


if __name__ == "__main__":
    sys.exit(main())
