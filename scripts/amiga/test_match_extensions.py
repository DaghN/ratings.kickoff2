#!/usr/bin/env python3
import unittest

from scripts.amiga.match_extensions import extract_structured_from_extra, resolve_game_extension_winner


class MatchExtensionsTest(unittest.TestCase):
    def test_extract_pens(self) -> None:
        ext = extract_structured_from_extra("(0-0) 7-6pen")
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.pens_a, 7)
        self.assertEqual(ext.pens_b, 6)

    def test_extract_pens_with_et_parenthetical_on_draw(self) -> None:
        ext = extract_structured_from_extra("(0-0) 7-8pen", goals_a=0, goals_b=0)
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.pens_a, 7)
        self.assertEqual(ext.pens_b, 8)
        self.assertEqual(ext.goals_et_a, 0)
        self.assertEqual(ext.goals_et_b, 0)

    def test_extract_pens_et_period_only_when_post_et_differs_from_reg(self) -> None:
        ext = extract_structured_from_extra("(0-0) 7-6pen", goals_a=1, goals_b=1)
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.goals_et_a, 0)
        self.assertEqual(ext.goals_et_b, 0)

    def test_extract_et_post_total_with_regulation(self) -> None:
        ext = extract_structured_from_extra("5-4 e.t.", goals_a=4, goals_b=4)
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.goals_et_a, 1)
        self.assertEqual(ext.goals_et_b, 0)

    def test_extract_et_kelkheim_final(self) -> None:
        ext = extract_structured_from_extra("4-4 a.e.t", goals_a=4, goals_b=3)
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.goals_et_a, 0)
        self.assertEqual(ext.goals_et_b, 1)

    def test_extract_et_without_regulation_legacy_literal(self) -> None:
        ext = extract_structured_from_extra("5-4 e.t.")
        self.assertIsNotNone(ext)
        assert ext is not None
        self.assertEqual(ext.goals_et_a, 5)
        self.assertEqual(ext.goals_et_b, 4)

    def test_resolve_extra_time_structured(self) -> None:
        game = {
            "goals_a": 4,
            "goals_b": 4,
            "extra": "5-4 e.t.",
            "goals_et_a": 1,
            "goals_et_b": 0,
            "pens_a": None,
            "pens_b": None,
        }
        wid = resolve_game_extension_winner(game, "extra_time", 10, 20)
        self.assertEqual(wid, 10)


if __name__ == "__main__":
    unittest.main()