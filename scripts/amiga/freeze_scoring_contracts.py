#!/usr/bin/env python3
"""SC-7 — freeze L4b scoring contracts on finalized tournaments (catalog repair)."""

from __future__ import annotations

import argparse
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.scoring_contract import (
    backfill_scoring_freeze_for_finalized,
    count_scoring_freeze_backfill_candidates,
)


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Freeze L4b scoring contracts on finalized tournaments (SC-7)",
    )
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        candidates = count_scoring_freeze_backfill_candidates(
            conn, tournament_id=args.tournament_id
        )
        if args.dry_run:
            print(f"dry-run: would freeze tournaments={candidates}")
            return 0

        stats = backfill_scoring_freeze_for_finalized(
            conn,
            tournament_id=args.tournament_id,
            dry_run=False,
        )
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    print(
        "freeze-scoring-contracts: "
        f"tournaments={stats['tournaments']} stages={stats['stages']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())