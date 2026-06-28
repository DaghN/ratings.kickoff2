"""Tests for realm snapshot row build + persist."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id
from scripts.amiga.realm_incremental import build_generalstats_payload_incremental
from scripts.amiga.realm_persist import build_realm_row
from scripts.amiga.server_records import build_generalstats_payload

_TOLERANCE = 1e-5
_DECIMAL_COLUMNS = frozenset(
    {
        "DifferentOpponentsAverage",
        "GamesPlayedAverage",
        "DecidedGamesRatio",
        "DrawsRatio",
        "GoalsPerGameAverage",
        "DoubleDigitsRatio",
        "CleanSheetsRatio",
        "BiggestRatingAscent",
        "BiggestWinRatio",
        "BiggestGoalsForAverage",
        "SmallestGoalsAgainstAverage",
        "BiggestGoalRatio",
        "BiggestDoubleDigitsRatio",
        "BiggestCleanSheetsRatio",
    }
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


def _values_match(left: object, right: object, column: str) -> bool:
    if left is None and right is None:
        return True
    if column in _DECIMAL_COLUMNS:
        if left is None or right is None:
            return False
        return abs(float(left) - float(right)) <= _TOLERANCE
    return left == right


class RealmRowBuildTests(unittest.TestCase):
    def test_payload_keys_match_manifest(self) -> None:
        conn = _connect()
        try:
            tid = latest_finalized_tournament_id(conn)
            if tid is None:
                self.skipTest("no finalized tournaments")
            payload = build_generalstats_payload(conn, as_of_tournament_id=tid)
            self.assertTrue(set(payload.keys()).issubset(set(GENERALSTATS_PAYLOAD_COLUMNS)))
            self.assertNotIn("GamesPlayed", payload)
            self.assertIn("MostGamesPlayed", payload)
            row = build_realm_row(conn, as_of_tournament_id=tid)
            self.assertEqual(row["tournament_id"], tid)
            self.assertNotIn("GamesPlayed", row)
            self.assertIn("BiggestWinRatio", row)
        finally:
            conn.close()

    def test_incremental_matches_oracle_on_latest(self) -> None:
        conn = _connect()
        try:
            tid = latest_finalized_tournament_id(conn)
            if tid is None:
                self.skipTest("no finalized tournaments")
            oracle = build_generalstats_payload(conn, as_of_tournament_id=tid)
            incremental = build_generalstats_payload_incremental(conn, tid)
            mismatches = [
                col
                for col in GENERALSTATS_PAYLOAD_COLUMNS
                if not _values_match(oracle.get(col), incremental.get(col), col)
            ]
            self.assertEqual(
                [],
                mismatches[:5],
                msg=f"incremental vs oracle mismatches: {mismatches[:10]}",
            )
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
