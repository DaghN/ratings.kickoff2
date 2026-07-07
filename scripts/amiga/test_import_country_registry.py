"""Tests for L3 country registry normalization."""

from __future__ import annotations

import unittest
from types import SimpleNamespace

from scripts.amiga.import_country_registry import apply_country_registry_to_prepared


class ImportCountryRegistryTests(unittest.TestCase):
    def test_canonicalize_player_and_tournament(self) -> None:
        prepared = SimpleNamespace(
            countries={"Stephen D": "N. Ireland", "Mark B": "England"},
            tournaments=[{"name": "Dubai I", "country": "UAE"}],
        )
        normalizations = apply_country_registry_to_prepared(prepared)
        self.assertEqual(prepared.countries["Stephen D"], "Northern Ireland")
        self.assertEqual(prepared.countries["Mark B"], "England")
        self.assertEqual(prepared.tournaments[0]["country"], "United Arab Emirates")
        accesses = {n["access"] for n in normalizations}
        self.assertEqual(accesses, {"N. Ireland", "UAE"})


if __name__ == "__main__":
    unittest.main()