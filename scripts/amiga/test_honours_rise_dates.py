"""Unit tests for honours last-rise date tracking."""

from __future__ import annotations

import unittest
from datetime import date

from scripts.amiga.honours_totals import (
    HONOURS_RISE_METRICS,
    empty_honours_totals,
    honours_from_current_row,
    increment_honours_totals,
)


def _participation(
    *,
    tournament_id: int,
    event_date: date,
    tournament_name: str = "Athens I",
    event_finish_position: int | None = None,
    is_winner: int = 0,
) -> dict:
    return {
        "tournament_id": tournament_id,
        "event_date": event_date,
        "tournament_name": tournament_name,
        "event_finish_position": event_finish_position,
        "is_winner": is_winner,
    }


class HonoursRiseDatesTests(unittest.TestCase):
    def test_empty_has_null_rise_fields(self) -> None:
        totals = empty_honours_totals()
        for metric in HONOURS_RISE_METRICS:
            self.assertIsNone(totals[f"{metric}_last_rise_tournament_id"])
            self.assertIsNone(totals[f"{metric}_last_rise_event_date"])

    def test_tournaments_played_rises_every_participation(self) -> None:
        totals = empty_honours_totals()
        increment_honours_totals(
            totals,
            _participation(tournament_id=10, event_date=date(2020, 1, 1), event_finish_position=5),
        )
        self.assertEqual(totals["tournaments_played_last_rise_tournament_id"], 10)
        self.assertEqual(totals["tournaments_played_last_rise_event_date"], date(2020, 1, 1))
        self.assertIsNone(totals["event_gold_last_rise_tournament_id"])

    def test_event_gold_rise_only_on_first_place(self) -> None:
        totals = empty_honours_totals()
        increment_honours_totals(
            totals,
            _participation(tournament_id=11, event_date=date(2021, 2, 1), event_finish_position=2),
        )
        self.assertIsNone(totals["event_gold_last_rise_tournament_id"])

        increment_honours_totals(
            totals,
            _participation(tournament_id=12, event_date=date(2021, 3, 1), event_finish_position=1),
        )
        self.assertEqual(totals["event_gold"], 1)
        self.assertEqual(totals["event_gold_last_rise_tournament_id"], 12)
        self.assertEqual(totals["event_gold_last_rise_event_date"], date(2021, 3, 1))

    def test_wc_played_rise_only_on_world_cup_name(self) -> None:
        totals = empty_honours_totals()
        increment_honours_totals(
            totals,
            _participation(
                tournament_id=20,
                event_date=date(2022, 6, 1),
                tournament_name="Athens XC",
                event_finish_position=4,
            ),
        )
        self.assertIsNone(totals["wc_played_last_rise_tournament_id"])

        increment_honours_totals(
            totals,
            _participation(
                tournament_id=21,
                event_date=date(2022, 11, 1),
                tournament_name="World Cup XXII (Nottingham)",
                event_finish_position=8,
            ),
        )
        self.assertEqual(totals["wc_played"], 1)
        self.assertEqual(totals["wc_played_last_rise_tournament_id"], 21)
        self.assertEqual(totals["wc_played_last_rise_event_date"], date(2022, 11, 1))

    def test_participation_without_metric_rise_preserves_prior_rise(self) -> None:
        """Alkis pattern: 58th win then later WC without winning — gold rise stays at win event."""
        totals = empty_honours_totals()
        for tid, edate in ((24, date(2025, 9, 20)),):
            increment_honours_totals(
                totals,
                _participation(
                    tournament_id=tid,
                    event_date=edate,
                    event_finish_position=1,
                ),
            )
        self.assertEqual(totals["event_gold"], 1)
        win_tid = totals["event_gold_last_rise_tournament_id"]
        win_date = totals["event_gold_last_rise_event_date"]

        increment_honours_totals(
            totals,
            _participation(
                tournament_id=25,
                event_date=date(2025, 11, 1),
                tournament_name="World Cup XXIII (Milan)",
                event_finish_position=5,
            ),
        )
        self.assertEqual(totals["event_gold"], 1)
        self.assertEqual(totals["event_gold_last_rise_tournament_id"], win_tid)
        self.assertEqual(totals["event_gold_last_rise_event_date"], win_date)
        self.assertEqual(totals["last_event_date"], date(2025, 11, 1))
        self.assertEqual(totals["wc_played_last_rise_tournament_id"], 25)

    def test_honours_from_current_row_carries_rise_fields(self) -> None:
        row = {
            "tournaments_played": 3,
            "event_gold": 2,
            "wc_played": 1,
            "last_event_date": date(2024, 1, 1),
            "last_tournament_id": 99,
            "event_gold_last_rise_tournament_id": 42,
            "event_gold_last_rise_event_date": date(2023, 5, 5),
        }
        totals = honours_from_current_row(row)
        self.assertEqual(totals["event_gold_last_rise_tournament_id"], 42)
        self.assertEqual(totals["event_gold_last_rise_event_date"], date(2023, 5, 5))
        self.assertIsNone(totals["tournaments_played_last_rise_tournament_id"])


if __name__ == "__main__":
    unittest.main()
