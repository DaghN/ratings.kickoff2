#!/usr/bin/env python3
"""Backfill structured ET/pens columns from witness extra text (SC-11)."""

from __future__ import annotations

import argparse
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.match_extensions import extract_structured_from_extra

_UPDATE_GAME = """
UPDATE amiga_games
SET goals_et_a = %s, goals_et_b = %s, pens_a = %s, pens_b = %s
WHERE id = %s
"""

_UPDATE_FIXTURE = """
UPDATE tournament_fixtures
SET goals_et_a = %s, goals_et_b = %s, pens_a = %s, pens_b = %s
WHERE id = %s
"""


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


def backfill_match_extensions(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> dict[str, int]:
    stats = {"games_seen": 0, "games_updated": 0, "fixtures_updated": 0, "unparsed": 0}

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, extra
            FROM amiga_games
            WHERE extra IS NOT NULL AND TRIM(extra) <> ''
            """
        )
        games = list(cur.fetchall())

    for row in games:
        stats["games_seen"] += 1
        structured = extract_structured_from_extra(row["extra"])
        if structured is None:
            stats["unparsed"] += 1
            continue
        if dry_run:
            stats["games_updated"] += 1
            continue
        with conn.cursor() as cur:
            cur.execute(
                _UPDATE_GAME,
                (
                    structured.goals_et_a,
                    structured.goals_et_b,
                    structured.pens_a,
                    structured.pens_b,
                    int(row["id"]),
                ),
            )
        stats["games_updated"] += 1

    if not dry_run:
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE tournament_fixtures f
                INNER JOIN amiga_games g ON g.fixture_id = f.id
                SET f.goals_et_a = g.goals_et_a,
                    f.goals_et_b = g.goals_et_b,
                    f.pens_a = g.pens_a,
                    f.pens_b = g.pens_b
                WHERE g.fixture_id IS NOT NULL
                """
            )
            stats["fixtures_updated"] = int(cur.rowcount)
    else:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*) AS n
                FROM tournament_fixtures f
                INNER JOIN amiga_games g ON g.fixture_id = f.id
                WHERE g.fixture_id IS NOT NULL
                  AND (g.goals_et_a IS NOT NULL OR g.pens_a IS NOT NULL)
                """
            )
            stats["fixtures_updated"] = int(cur.fetchone()["n"])

    if not dry_run:
        conn.commit()
    return stats


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Backfill structured match extensions (SC-11)")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        stats = backfill_match_extensions(conn, dry_run=args.dry_run)
    finally:
        conn.close()

    prefix = "dry-run: " if args.dry_run else ""
    print(
        f"{prefix}backfill-match-extensions games_seen={stats['games_seen']} "
        f"games_updated={stats['games_updated']} fixtures_updated={stats['fixtures_updated']} "
        f"unparsed={stats['unparsed']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())