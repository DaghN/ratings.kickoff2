#!/usr/bin/env python3
"""
One-off template — copy to a new file, register in docs/coordination/one-off-register.md.

Usage (repo root):
  python scripts/oneoff/your_script.py --dry-run
  python scripts/oneoff/your_script.py
"""
from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

# Repo root on sys.path for scripts.ladder.config
_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402

logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
log = logging.getLogger("oneoff")


def main(dry_run: bool) -> None:
    cfg = load_db_config()
    conn = connect(cfg, dry_run=dry_run)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM ratedresults")
            row = cur.fetchone()
            assert row is not None
            log.info("DATABASE()=%s ratedresults rows: %s", cfg.database, row["n"])

            # --- your logic ---

            if dry_run:
                log.info("DRY RUN — no writes")
                return

            # Example write (remove or replace):
            # cur.execute("UPDATE ...")
            # conn.commit()
            log.info("Done")
    finally:
        conn.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Log only; do not commit writes",
    )
    args = parser.parse_args()
    main(dry_run=args.dry_run)
