"""Unit tests for KOA player naming helpers (stdlib unittest)."""

from __future__ import annotations

import unittest

from scripts.amiga.player_names import (
    identity_key,
    koa_abbreviation_candidates,
    normalize_display_name,
    suggest_koa_display_name,
)


class PlayerNamesTests(unittest.TestCase):
    def test_normalize_display_name(self) -> None:
        self.assertEqual(normalize_display_name("  Mark   B. "), "Mark B")

    def test_koa_candidates(self) -> None:
        self.assertEqual(
            koa_abbreviation_candidates("Mark", "Bentley"),
            ["Mark B", "Mark Be", "Mark Ben", "Mark Bent", "Mark Bentl", "Mark Bentle", "Mark Bentley"],
        )

    def test_suggest_skips_taken_prefixes(self) -> None:
        taken = {identity_key("Mark B")}
        result = suggest_koa_display_name("Mark Bentley", taken)
        self.assertEqual(result.suggested_name, "Mark Be")

    def test_suggest_exhaustion(self) -> None:
        taken = {identity_key(c) for c in koa_abbreviation_candidates("Mark", "Bentley")}
        result = suggest_koa_display_name("Mark Bentley", taken)
        self.assertIsNone(result.suggested_name)
        self.assertIn("already taken", result.reason or "")

    def test_suggest_canonical_style_as_is(self) -> None:
        taken: set[str] = set()
        result = suggest_koa_display_name("Mark B", taken)
        self.assertEqual(result.suggested_name, "Mark B")

    def test_suggest_refuses_single_token(self) -> None:
        result = suggest_koa_display_name("Madonna", set())
        self.assertIsNone(result.suggested_name)
        self.assertIn("first name and surname", result.reason or "")


if __name__ == "__main__":
    unittest.main()
