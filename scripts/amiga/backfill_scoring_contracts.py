#!/usr/bin/env python3
"""SC-6 — explicit L4b scoring contracts on catalog tournaments/stages."""

from __future__ import annotations

import argparse
import logging
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.scoring_contract import (
    backfill_scoring_contracts,
    count_scoring_contract_backfill_candidates,
)

log = logging.getLogger(__name__)


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
        description="Backfill L4b scoring contracts on catalog tournaments/stages (SC-6)",
    )
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        candidates = count_scoring_contract_backfill_candidates(
            conn, tournament_id=args.tournament_id
        )
        if args.dry_run:
            print(
                "dry-run: would backfill "
                f"tournaments={candidates['tournaments']} stages={candidates['stages']}"
            )
            return 0

        stats = backfill_scoring_contracts(
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
        "backfill-scoring-contracts: "
        f"tournaments={stats['tournaments']} stages={stats['stages']} "
        f"skipped_tournament={stats['skipped_tournament']} "
        f"skipped_stage={stats['skipped_stage']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())