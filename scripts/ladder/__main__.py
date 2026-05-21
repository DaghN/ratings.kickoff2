"""CLI: python -m scripts.ladder [reset|replay|run] [--dry-run] [--limit N]"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

# Allow `python -m scripts.ladder` from repo root
_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config
from scripts.ladder.engine import connect, replay_all, reset_universe, run_full


def main() -> None:
    parser = argparse.ArgumentParser(
        description="KO2 online ladder replay v1 (ko2unity_db only). See docs/replay-v1-scope-and-reset.md."
    )
    parser.add_argument(
        "command",
        choices=("reset", "replay", "run"),
        help="reset=clear derived; replay=chronological Elo; run=reset then replay",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Log actions and sample math; no UPDATE/COMMIT",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=None,
        metavar="N",
        help="Replay only first N games (after reset on run)",
    )
    parser.add_argument(
        "--ini",
        type=Path,
        default=None,
        help="Optional ladder.ini override (default: ko2unitydb_config.php)",
    )
    parser.add_argument("-v", "--verbose", action="store_true")
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(levelname)s %(message)s",
    )

    cfg = load_db_config(args.ini)
    if args.command == "run":
        run_full(cfg, dry_run=args.dry_run, limit=args.limit)
        return

    conn = connect(cfg, dry_run=args.dry_run)
    try:
        if args.command == "reset":
            reset_universe(conn, dry_run=args.dry_run)
        else:
            replay_all(conn, dry_run=args.dry_run, limit=args.limit)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
