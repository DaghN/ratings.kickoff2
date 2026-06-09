"""Tests for amiga_player_matchup_summary rebuild."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_matchup_summary import rebuild_all_matchup_summary


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


class MatchupSummaryRebuildTests(unittest.TestCase):
    def test_rebuild_parity(self) -> None:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
                game_count = int(cur.fetchone()["n"])
            self.assertGreater(game_count, 0, "expected amiga_games baseline")

            rows = rebuild_all_matchup_summary(conn, dry_run=False)

            with conn.cursor() as cur:
                cur.execute("SELECT COALESCE(SUM(games), 0) AS n FROM amiga_player_matchup_summary")
                games_sum = int(cur.fetchone()["n"])
                cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
                row_count = int(cur.fetchone()["n"])

            self.assertEqual(games_sum, game_count * 2)
            self.assertEqual(rows, row_count)
            self.assertGreater(row_count, 0)
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
