"""Tests for Amiga country registry JSON and loader."""

from __future__ import annotations

import json
import unittest

from scripts.amiga.country_registry import (
    alias_map,
    canonicalize_country_token,
    choosable_flag_codes,
    load_registry,
    official_names,
    registry_path,
    resolve_official,
    validate_official,
)
from scripts.amiga.import_corrections import PLAYER_COUNTRY_OVERRIDES, WORLD_CUP_VENUES


class CountryRegistryTests(unittest.TestCase):
    def test_registry_file_exists(self) -> None:
        self.assertTrue(registry_path().is_file())

    def test_world_cup_venue_countries_in_registry(self) -> None:
        names = official_names()
        hosts = {country for _city, country in WORLD_CUP_VENUES.values()}
        missing = sorted(hosts - names)
        self.assertEqual(missing, [], f"WC hosts missing from registry: {missing}")

    def test_player_country_overrides_in_registry(self) -> None:
        names = official_names()
        missing = sorted(set(PLAYER_COUNTRY_OVERRIDES.values()) - names)
        self.assertEqual(missing, [], f"Player overrides missing from registry: {missing}")

    def test_legacy_aliases(self) -> None:
        aliases = alias_map()
        self.assertEqual(aliases["N. Ireland"], "Northern Ireland")
        self.assertEqual(aliases["UAE"], "United Arab Emirates")
        self.assertEqual(canonicalize_country_token("N. Ireland"), "Northern Ireland")
        self.assertEqual(canonicalize_country_token("UAE"), "United Arab Emirates")

    def test_united_kingdom_not_choosable(self) -> None:
        uk = next(r for r in load_registry()["countries"] if r["official_name"] == "United Kingdom")
        self.assertFalse(uk["choosable"])

    def test_uk_home_nations_choosable(self) -> None:
        names = {r["official_name"] for r in load_registry()["countries"] if r.get("choosable")}
        for name in ("England", "Scotland", "Wales", "Northern Ireland"):
            self.assertIn(name, names)

    def test_no_duplicate_official_names_or_flag_codes(self) -> None:
        data = load_registry()
        names = [r["official_name"] for r in data["countries"]]
        codes = [r["flag_code"] for r in data["countries"]]
        self.assertEqual(len(names), len(set(names)))
        self.assertEqual(len(codes), len(set(codes)))

    def test_ireland_and_taiwan_official_names(self) -> None:
        self.assertIn("Ireland", official_names())
        self.assertIn("Taiwan", official_names())
        self.assertIn("Turkey", official_names())
        self.assertNotIn("Republic of Ireland", official_names())
        self.assertNotIn("Türkiye", official_names())

    def test_uae_shorthand_metadata(self) -> None:
        row = next(r for r in load_registry()["countries"] if r["official_name"] == "United Arab Emirates")
        self.assertEqual(row.get("site_shorthand"), "UAE")

    def test_choosable_flag_codes_non_empty(self) -> None:
        codes = choosable_flag_codes()
        self.assertGreater(len(codes), 250)
        self.assertIn("dk", codes)
        self.assertIn("gb-nir", codes)
        self.assertNotIn("gb", codes)

    def test_resolve_official(self) -> None:
        self.assertEqual(resolve_official("Denmark"), "Denmark")
        self.assertEqual(resolve_official("UAE"), "United Arab Emirates")
        self.assertIsNone(resolve_official("Not A Country"))
        self.assertTrue(validate_official("Greece"))
        self.assertFalse(validate_official("UAE"))


if __name__ == "__main__":
    unittest.main()