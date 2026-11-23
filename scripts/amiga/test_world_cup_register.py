"""The hand-reviewed World Cup register and the disposition register stay in step."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_structure.disposition_register import HANDLER_WORLD_CUP, DispositionRegister
from scripts.amiga.tournament_structure.world_cup import load_world_cup_register


class WorldCupRegisterTests(unittest.TestCase):
    def test_register_loads_and_every_knockout_round_has_a_place(self) -> None:
        register = load_world_cup_register()
        self.assertGreaterEqual(len(register), 24)
        for tid, wc in register.items():
            for label, d in wc["rounds"].items():
                with self.subTest(tournament_id=tid, round=label):
                    if d["type"] == "knockout":
                        self.assertIsInstance(d["order"], int)

    def test_every_world_cup_uses_the_register(self) -> None:
        register = load_world_cup_register()
        dispositions = DispositionRegister.load()
        for tid in register:
            row = dispositions.get(tid)
            self.assertIsNotNone(row, f"tournament {tid} missing from disposition_register.json")
            self.assertEqual(row.handler, HANDLER_WORLD_CUP, f"tournament {tid}")

    def test_known_decisions(self) -> None:
        """A few rounds decided by hand on 2026-11-23 (the names alone would mislead)."""
        register = load_world_cup_register()
        rounds = {tid: wc["rounds"] for tid, wc in register.items()}
        self.assertEqual(rounds[20]["Positions 5-8"]["type"], "knockout")
        self.assertEqual(rounds[140]["KOA Cup - Final"]["track"], "koa")
        self.assertEqual(rounds[169]["Round 2"]["type"], "knockout")  # main last 32 of World Cup V
        self.assertEqual(rounds[569]["Round 1"]["type"], "group")  # one group of 18 in World Cup XIII
        self.assertEqual(rounds[577]["Round 2 Group D Tie Breaker"]["section"], "other")


if __name__ == "__main__":
    unittest.main()
