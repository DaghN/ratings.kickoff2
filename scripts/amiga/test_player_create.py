"""Tests for Amiga live player create."""

from __future__ import annotations

import unittest

from scripts.amiga.country_registry import official_name_to_row
from scripts.amiga.player_names import identity_key, koa_abbreviation_candidates, suggest_koa_display_name
from scripts.amiga.player_registry import _validate_live_country


class PlayerNamingTests(unittest.TestCase):
    def test_koa_minimum_prefix_uniqueness(self) -> None:
        taken = {identity_key("Mark B")}
        result = suggest_koa_display_name("Mark Bentley", taken)
        self.assertIsNotNone(result.suggested_name)
        self.assertEqual(result.suggested_name, "Mark Be")

    def test_mononym_rejected(self) -> None:
        result = suggest_koa_display_name("Madonna", set())
        self.assertIsNone(result.suggested_name)

    def test_abbreviation_candidates_through_full_surname(self) -> None:
        candidates = koa_abbreviation_candidates("Dagh", "Nielsen")
        self.assertEqual(candidates[0], "Dagh N")
        self.assertEqual(candidates[-1], "Dagh Nielsen")

    def test_validate_live_country_requires_choosable(self) -> None:
        self.assertEqual(_validate_live_country("Norway"), "Norway")
        with self.assertRaises(ValueError):
            _validate_live_country("")
        uk = official_name_to_row().get("United Kingdom")
        if uk is not None and not uk.get("choosable", True):
            with self.assertRaises(ValueError):
                _validate_live_country("United Kingdom")


if __name__ == "__main__":
    unittest.main()