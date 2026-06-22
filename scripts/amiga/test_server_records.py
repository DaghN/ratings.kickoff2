"""Tests for amiga_generalstats rebuild."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.server_records import rebuild_generalstats


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


class GeneralstatsRebuildTests(unittest.TestCase):
    def test_rebuild_populates_row(self) -> None:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
                game_count = int(cur.fetchone()["n"])
            if game_count == 0:
                self.skipTest("no amiga_games — run replay first")

            patch = rebuild_generalstats(conn, dry_run=False)

            with conn.cursor() as cur:
                cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1")
                row = cur.fetchone()
                cur.execute("SELECT GamesPlayed FROM amiga_community_stats WHERE id = 1")
                community = cur.fetchone()

            self.assertIsNotNone(row)
            self.assertIsNotNone(community)
            self.assertEqual(int(community["GamesPlayed"]), game_count)
            self.assertNotIn("GamesPlayed", row)
            self.assertGreater(int(row["MostGamesPlayed"] or 0), 0)
            self.assertIsNotNone(row["MostGamesPlayedName"])
            self.assertIsNone(row.get("LongestWinningStreak"))
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
