"""Integration checks for incremental tournament participation rebuild."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_tournament_participation import (
    player_ids_for_tournament,
    rebuild_participation_and_totals_for_tournament,
)


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


def _participation_rows(conn: pymysql.connections.Connection, tournament_id: int) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, tournament_id, event_finish_position, event_points, is_winner
            FROM amiga_player_tournament_participation
            WHERE tournament_id = %s
            ORDER BY player_id
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def _totals_rows(conn: pymysql.connections.Connection, player_ids: list[int]) -> list[dict]:
    if not player_ids:
        return []
    placeholders = ", ".join(["%s"] * len(player_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT player_id, tournaments_played, tournaments_won,
                   event_gold, event_silver, event_bronze, event_podiums,
                   wc_played, wc_gold, wc_silver, wc_bronze, wc_podiums,
                   last_tournament_id
            FROM amiga_player_tournament_totals
            WHERE player_id IN ({placeholders})
            ORDER BY player_id
            """,
            player_ids,
        )
        return list(cur.fetchall())


def _assert_v2_totals_invariants(totals_row: dict) -> None:
    event_gold = int(totals_row["event_gold"])
    event_silver = int(totals_row["event_silver"])
    event_bronze = int(totals_row["event_bronze"])
    event_podiums = int(totals_row["event_podiums"])
    wc_gold = int(totals_row["wc_gold"])
    wc_silver = int(totals_row["wc_silver"])
    wc_bronze = int(totals_row["wc_bronze"])
    wc_podiums = int(totals_row["wc_podiums"])
    assert int(totals_row["tournaments_won"]) == event_gold
    assert event_podiums == event_gold + event_silver + event_bronze
    assert wc_podiums == wc_gold + wc_silver + wc_bronze
    assert wc_gold <= event_gold
    assert wc_silver <= event_silver
    assert wc_bronze <= event_bronze
    assert wc_podiums <= event_podiums


class IncrementalTournamentRebuildTests(unittest.TestCase):
    TOURNAMENT_IDS = (603, 23)  # World Cup XVII + Nottingham II

    def test_incremental_rebuild_matches_snapshot(self) -> None:
        conn = _connect()
        try:
            for tournament_id in self.TOURNAMENT_IDS:
                with self.subTest(tournament_id=tournament_id):
                    before_part = _participation_rows(conn, tournament_id)
                    self.assertGreater(len(before_part), 0, "expected participation baseline")
                    player_ids = player_ids_for_tournament(conn, tournament_id)
                    before_totals = _totals_rows(conn, player_ids)

                    rebuild_participation_and_totals_for_tournament(conn, tournament_id)

                    after_part = _participation_rows(conn, tournament_id)
                    after_totals = _totals_rows(conn, player_ids)

                    self.assertEqual(after_part, before_part)
                    self.assertEqual(len(after_totals), len(before_totals))
                    for row in after_totals:
                        _assert_v2_totals_invariants(row)
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
