"""World Cup Hall of Fame: a tied record stays with its holder (policy WCH9)."""

from __future__ import annotations

import unittest

from scripts.amiga.wc_hof import _pick_cumulative


def _row(pid: int, gold: int) -> dict:
    return {"player_id": pid, "player_name": str(pid), "gold": gold}


class WcHofTieTests(unittest.TestCase):
    def test_holder_keeps_a_tied_record(self) -> None:
        # Gianni T (149) had 5 golds from 2022; Dagh N (73) equalled it at Oslo 2026.
        rows = [_row(73, 5), _row(149, 5), _row(14, 2)]
        self.assertEqual(_pick_cumulative(rows, "gold", 149)["player_id"], 149)

    def test_new_holder_on_a_better_value(self) -> None:
        rows = [_row(73, 6), _row(149, 5)]
        self.assertEqual(_pick_cumulative(rows, "gold", 149)["player_id"], 73)

    def test_tie_without_a_holder_goes_to_the_lowest_number(self) -> None:
        rows = [_row(149, 5), _row(73, 5)]
        self.assertEqual(_pick_cumulative(rows, "gold")["player_id"], 73)


if __name__ == "__main__":
    unittest.main()
