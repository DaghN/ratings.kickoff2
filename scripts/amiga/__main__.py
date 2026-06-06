#!/usr/bin/env python3
"""CLI: python -m scripts.amiga import | replay | run"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.import_access import _DEFAULT_MDB, import_all
from scripts.amiga.replay import run_replay

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

    return 1


if __name__ == "__main__":
    sys.exit(main())
