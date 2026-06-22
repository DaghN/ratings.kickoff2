"""Tests for community headline + facts."""

from __future__ import annotations

import unittest

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.community_stat_facts import build_community_facts_at_cutoff
from scripts.amiga.community_stats import (
    build_community_headline_row,
    compute_community_headline_aggregates,
)
from scripts.amiga.community_stats_columns import COMMUNITY_HEADLINE_COLUMNS
from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import GENERALSTATS_AGGREGATE_COLUMNS
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id
from scripts.amiga.server_records import compute_server_aggregates

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


class CommunityHeadlineTests(unittest.TestCase):
    def test_headline_matches_server_aggregates(self) -> None:
        conn = _connect()
        try:
            tid = latest_finalized_tournament_id(conn)
            if tid is None:
                self.skipTest("no finalized tournaments")
            community = compute_community_headline_aggregates(
                conn, as_of_tournament_id=tid
            )
            legacy = compute_server_aggregates(conn, as_of_tournament_id=tid)
            for col in GENERALSTATS_AGGREGATE_COLUMNS:
                self.assertTrue(
                    _values_match(community.get(col), legacy.get(col), col),
                    col,
                )
            row = build_community_headline_row(conn, as_of_tournament_id=tid)
            self.assertEqual(row["tournament_id"], tid)
            for col in COMMUNITY_HEADLINE_COLUMNS:
                self.assertIn(col, row)
        finally:
            conn.close()

    def test_facts_non_empty_at_tail(self) -> None:
        conn = _connect()
        try:
            tid = latest_finalized_tournament_id(conn)
            if tid is None:
                self.skipTest("no finalized tournaments")
            facts = build_community_facts_at_cutoff(conn, tid)
            self.assertGreater(len(facts), 0)
            sample = facts[0]
            for key in (
                "tournament_id",
                "period_type",
                "period_key",
                "slice_type",
                "slice_key",
                "metric_key",
                "count_basis",
                "value",
            ):
                self.assertIn(key, sample)
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
