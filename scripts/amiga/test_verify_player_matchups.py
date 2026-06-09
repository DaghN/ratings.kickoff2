"""Unit tests for verify_player_matchups spot-check SQL."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.verify_player_matchups import verify_player_matchups


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


class VerifyPlayerMatchupsTests(unittest.TestCase):
    def test_verify_passes_on_rebuilt_data(self) -> None:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
                row_count = int(cur.fetchone()["n"])
            if row_count == 0:
                self.skipTest("matchup summary empty — run replay or matchup-rebuild first")

            errors = verify_player_matchups(conn)
            self.assertEqual(errors, [], errors)
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
