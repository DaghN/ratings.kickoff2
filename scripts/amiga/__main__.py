#!/usr/bin/env python3
"""CLI: python -m scripts.amiga import | replay | run"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import _DEFAULT_MDB, import_all
from scripts.ladder.engine import connect, replay_all, reset_universe

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

    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        log.error("Refusing: database must be ko2amiga_db (got %r)", cfg.database)
        return 1

    if args.cmd == "replay":
        conn = connect(cfg, dry_run=args.dry_run, target="amiga")
        try:
            reset_universe(conn, dry_run=args.dry_run)
            replay_all(conn, dry_run=args.dry_run, limit=args.limit)
        finally:
            conn.close()
        return 0

    if args.cmd == "run":
        stats = import_all(mdb=args.mdb, recreate_schema=args.recreate_schema)
        log.info("Import complete: %s", stats)
        conn = connect(cfg, dry_run=args.dry_run, target="amiga")
        try:
            reset_universe(conn, dry_run=args.dry_run)
            replay_all(conn, dry_run=args.dry_run, limit=args.limit)
        finally:
            conn.close()
        return 0

    return 1


if __name__ == "__main__":
    sys.exit(main())
