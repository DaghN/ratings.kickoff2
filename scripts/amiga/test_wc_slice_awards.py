"""Unit tests for World Cup per-event awards + single-WC peaks (WCH-2)."""

from __future__ import annotations

import unittest

from scripts.amiga.slice_totals import empty_world_cup_slice
from scripts.amiga.wc_slice_awards import (
    apply_wc_slice_awards_and_peaks,
    compute_event_award_winners,
    event_average,
)


def _part(pid: int, games: int, gf: int, ga: int) -> dict:
    return {"player_id": pid, "games": games, "goals_for": gf, "goals_against": ga}


class EventAwardWinnerTests(unittest.TestCase):
    def test_basic_attack_and_defense(self) -> None:
        rows = [
            _part(10, 5, 20, 4),   # gf/g 4.0, ga/g 0.8
            _part(11, 5, 10, 1),   # gf/g 2.0, ga/g 0.2  <- best defense
            _part(12, 4, 24, 12),  # gf/g 6.0, ga/g 3.0  <- best attack
        ]
        attack, defense = compute_event_award_winners(rows)
        self.assertEqual(attack, 12)
        self.assertEqual(defense, 11)

    def test_attack_tie_breaks_to_lowest_pid(self) -> None:
        rows = [_part(20, 2, 6, 0), _part(15, 2, 6, 0), _part(30, 1, 1, 5)]
        attack, defense = compute_event_award_winners(rows)
        self.assertEqual(attack, 15)   # gf/g 3.0 tie -> lowest pid
        self.assertEqual(defense, 15)  # ga/g 0.0 tie -> lowest pid

    def test_zero_games_excluded(self) -> None:
        rows = [_part(5, 0, 0, 0), _part(6, 3, 9, 3)]
        attack, defense = compute_event_award_winners(rows)
        self.assertEqual(attack, 6)
        self.assertEqual(defense, 6)

    def test_no_eligible_participants(self) -> None:
        self.assertEqual(compute_event_award_winners([_part(1, 0, 0, 0)]), (None, None))


class ApplyAwardsAndPeaksTests(unittest.TestCase):
    def _accum(self, pids: list[int]) -> dict[int, dict]:
        return {pid: empty_world_cup_slice() for pid in pids}

    def test_award_counters_increment(self) -> None:
        accum = self._accum([10, 11, 12])
        rows = [_part(10, 5, 20, 4), _part(11, 5, 10, 1), _part(12, 4, 24, 12)]
        apply_wc_slice_awards_and_peaks(accum, rows, tournament_id=900)
        self.assertEqual(accum[12]["best_attack_awards"], 1)
        self.assertEqual(accum[11]["best_defense_awards"], 1)
        self.assertEqual(accum[10]["best_attack_awards"], 0)

    def test_peaks_set_and_anchor(self) -> None:
        accum = self._accum([10])
        apply_wc_slice_awards_and_peaks(accum, [_part(10, 4, 12, 4)], tournament_id=900)
        self.assertEqual(accum[10]["best_single_wc_gf_per_game"], event_average(12, 4))  # 3.0
        self.assertEqual(accum[10]["best_single_wc_gf_per_game_tournament_id"], 900)
        self.assertEqual(accum[10]["best_single_wc_ga_per_game"], event_average(4, 4))   # 1.0
        self.assertEqual(accum[10]["best_single_wc_ga_per_game_tournament_id"], 900)

    def test_peak_strict_beat_only(self) -> None:
        accum = self._accum([10])
        apply_wc_slice_awards_and_peaks(accum, [_part(10, 4, 12, 4)], tournament_id=900)
        # Equal GF/g (3.0) must NOT replace anchor; worse GA/g must NOT replace.
        apply_wc_slice_awards_and_peaks(accum, [_part(10, 2, 6, 10)], tournament_id=901)
        self.assertEqual(accum[10]["best_single_wc_gf_per_game_tournament_id"], 900)
        self.assertEqual(accum[10]["best_single_wc_ga_per_game_tournament_id"], 900)
        # Strictly better GF/g replaces.
        apply_wc_slice_awards_and_peaks(accum, [_part(10, 5, 20, 0)], tournament_id=902)
        self.assertEqual(accum[10]["best_single_wc_gf_per_game"], event_average(20, 5))  # 4.0
        self.assertEqual(accum[10]["best_single_wc_gf_per_game_tournament_id"], 902)
        self.assertEqual(accum[10]["best_single_wc_ga_per_game"], event_average(0, 5))   # 0.0
        self.assertEqual(accum[10]["best_single_wc_ga_per_game_tournament_id"], 902)

    def test_award_winner_not_in_accum_is_noop(self) -> None:
        accum = self._accum([10])
        # pid 99 wins attack but has no slice row -> skipped without error.
        apply_wc_slice_awards_and_peaks(accum, [_part(99, 5, 50, 0), _part(10, 1, 1, 1)], 900)
        self.assertEqual(accum[10]["best_attack_awards"], 0)


if __name__ == "__main__":
    unittest.main()