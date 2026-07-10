#!/usr/bin/env python3
"""Rebuild L5 standings so stage_id dual-write is populated (SC-9)."""

from __future__ import annotations

import argparse
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_standings import rebuild_standings_for_tournament


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def _tournament_ids(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None,
) -> list[int]:
    if tournament_id is not None:
        return [tournament_id]
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT DISTINCT tournament_id
            FROM amiga_games
            WHERE tournament_id IS NOT NULL
            ORDER BY tournament_id
            """
        )
        return [int(row["tournament_id"]) for row in cur.fetchall()]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Backfill L5 stage_id via standings rebuild (SC-9)")
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        ids = _tournament_ids(conn, tournament_id=args.tournament_id)
        total_rows = 0
        for tid in ids:
            if args.dry_run:
                print(f"dry-run: would rebuild standings tournament_id={tid}")
                continue
            total_rows += rebuild_standings_for_tournament(conn, tid)
        if args.dry_run:
            print(f"backfill-standings-stage-id dry-run: {len(ids)} tournament(s)")
            return 0
        print(f"backfill-standings-stage-id OK: {len(ids)} tournament(s), {total_rows} row(s) written")
    finally:
        conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())