"""Unit tests for the World Cup Hall of Fame column manifest (SCH-046)."""

from __future__ import annotations

import re
import unittest
from pathlib import Path

from scripts.amiga.slice_columns import (
    SLICE_AT_EVENT_COLUMNS,
    SLICE_STAT_COLUMNS_WC_HOF,
    SLICE_TOTALS_COLUMNS,
)
from scripts.amiga.slice_totals import empty_world_cup_slice
from scripts.amiga.wc_hof_columns import (
    ANCHOR_GAME,
    ANCHOR_TOURNAMENT,
    WC_ESTABLISHED_MIN_GAMES,
    WC_HOF_DATE_COLUMNS,
    WC_HOF_GAME_ID_COLUMNS,
    WC_HOF_HOLDER_ID_COLUMNS,
    WC_HOF_HOLDER_NAME_COLUMNS,
    WC_HOF_PAYLOAD_COLUMNS,
    WC_HOF_PRESENT_COLUMNS,
    WC_HOF_RECORD_SPECS,
    WC_HOF_SNAPSHOT_COLUMNS,
    WC_HOF_TOURNAMENT_ID_COLUMNS,
    WC_HOF_VALUE_COLUMNS,
    wc_hof_payload_column_sql_types,
)

_DDL_PATH = (
    Path(__file__).resolve().parent / "sql" / "derived" / "046_wc_hof.sql"
)


class WcHofColumnManifestTests(unittest.TestCase):
    def test_record_count_is_28(self) -> None:
        self.assertEqual(len(WC_HOF_RECORD_SPECS), 28)
        self.assertEqual(len(WC_HOF_VALUE_COLUMNS), 28)

    def test_holder_and_anchor_counts(self) -> None:
        # 26 single-holder groups (1 id/name) + 2 pair groups (2 id/name each).
        self.assertEqual(len(WC_HOF_HOLDER_ID_COLUMNS), 30)
        self.assertEqual(len(WC_HOF_HOLDER_NAME_COLUMNS), 30)
        self.assertEqual(len(WC_HOF_DATE_COLUMNS), 28)
        self.assertEqual(len(WC_HOF_GAME_ID_COLUMNS), 4)
        self.assertEqual(len(WC_HOF_TOURNAMENT_ID_COLUMNS), 2)

    def test_payload_and_table_column_counts(self) -> None:
        self.assertEqual(len(WC_HOF_PAYLOAD_COLUMNS), 122)
        self.assertEqual(len(WC_HOF_SNAPSHOT_COLUMNS), 127)  # 5 key + 122 payload
        self.assertEqual(len(WC_HOF_PRESENT_COLUMNS), 123)   # id + 122 payload

    def test_no_duplicate_column_names(self) -> None:
        for label, columns in (
            ("payload", WC_HOF_PAYLOAD_COLUMNS),
            ("snapshot", WC_HOF_SNAPSHOT_COLUMNS),
            ("present", WC_HOF_PRESENT_COLUMNS),
        ):
            self.assertEqual(
                len(columns), len(set(columns)), f"duplicate names in {label}"
            )

    def test_ratio_gate_specs(self) -> None:
        self.assertEqual(WC_ESTABLISHED_MIN_GAMES, 20)
        gated = [s for s in WC_HOF_RECORD_SPECS if s.min_games == 20]
        self.assertEqual(len(gated), 8)
        # Single-WC peaks and awards are NOT gated.
        ungated_decimals = [
            s.prefix
            for s in WC_HOF_RECORD_SPECS
            if s.min_games == 0 and s.value_sql_type.startswith("decimal")
        ]
        self.assertCountEqual(
            ungated_decimals,
            ["BestSingleWcGoalsForPerGame", "BestSingleWcGoalsAgainstPerGame"],
        )

    def test_anchor_columns_match_specs(self) -> None:
        game_prefixes = {
            s.prefix for s in WC_HOF_RECORD_SPECS if s.anchor == ANCHOR_GAME
        }
        self.assertEqual(
            game_prefixes,
            {
                "MostWcGoalsInOneGame",
                "BiggestWcWinDifference",
                "BiggestWcDrawSum",
                "BiggestWcSumOfGoals",
            },
        )
        tourn_prefixes = {
            s.prefix for s in WC_HOF_RECORD_SPECS if s.anchor == ANCHOR_TOURNAMENT
        }
        self.assertEqual(
            tourn_prefixes,
            {"BestSingleWcGoalsForPerGame", "BestSingleWcGoalsAgainstPerGame"},
        )

    def test_slice_extension_columns(self) -> None:
        self.assertEqual(len(SLICE_STAT_COLUMNS_WC_HOF), 6)
        empty = empty_world_cup_slice()
        for col in SLICE_STAT_COLUMNS_WC_HOF:
            self.assertIn(col, empty, f"{col} missing from empty_world_cup_slice()")
            self.assertIn(col, SLICE_TOTALS_COLUMNS)
            self.assertIn(col, SLICE_AT_EVENT_COLUMNS)

    def test_ddl_contains_every_manifest_column(self) -> None:
        ddl = _DDL_PATH.read_text(encoding="utf-8")
        backticked = set(re.findall(r"`([^`]+)`", ddl))
        for col in WC_HOF_PAYLOAD_COLUMNS:
            self.assertIn(col, backticked, f"{col} missing from 046_wc_hof.sql")
        for col in SLICE_STAT_COLUMNS_WC_HOF:
            self.assertIn(col, backticked, f"{col} missing from 046 slice ALTER")

    def test_ddl_value_types_present(self) -> None:
        ddl = _DDL_PATH.read_text(encoding="utf-8")
        for col, sql_type in wc_hof_payload_column_sql_types().items():
            self.assertRegex(
                ddl,
                rf"`{re.escape(col)}`\s+{re.escape(sql_type)}",
                f"{col} should be declared {sql_type}",
            )


if __name__ == "__main__":
    unittest.main()