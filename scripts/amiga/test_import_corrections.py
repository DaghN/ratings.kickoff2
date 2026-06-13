"""Unit tests for import_corrections supplemental Scores."""

from __future__ import annotations

import unittest

from scripts.amiga.import_access import AccessScore, merge_supplemental_scores
from scripts.amiga.import_corrections import (
    IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE,
    IMPORT_SUPPLEMENT_SCORES_ID_BASE,
    SUPPLEMENTAL_SCORES,
    WORLD_CUP_VENUES,
    apply_catalog_corrections,
    apply_catalog_splits,
    catalog_name_after_corrections,
    catalog_splits_manifest,
    supplemental_scores_manifest,
    world_cup_catalog_name,
)


class ImportCorrectionsTests(unittest.TestCase):
    def test_rodenbach_ii_is_complete_round_robin(self) -> None:
        players = {"Frank F", "Horst L", "Joerg D", "Jan K", "Thorsten B"}
        pairs: set[tuple[str, str]] = set()
        for row in SUPPLEMENTAL_SCORES:
            if row.tournament != "Rodenbach II":
                continue
            self.assertIn(row.team_a, players)
            self.assertIn(row.team_b, players)
            key = tuple(sorted((row.team_a, row.team_b)))
            self.assertNotIn(key, pairs)
            pairs.add(key)
        self.assertEqual(len(pairs), 10)

    def test_merge_supplemental_scores_reserves_ids(self) -> None:
        base = [
            AccessScore(1, "A", "B", 1, 0, "T", None, None),
        ]
        merged = merge_supplemental_scores(base)
        self.assertEqual(len(merged), 1 + len(SUPPLEMENTAL_SCORES))
        sup_ids = {s.source_id for s in merged[len(base) :]}
        self.assertTrue(all(i >= IMPORT_SUPPLEMENT_SCORES_ID_BASE for i in sup_ids))
        self.assertEqual(len(sup_ids), len(SUPPLEMENTAL_SCORES))

    def test_supplemental_manifest_counts(self) -> None:
        manifest = supplemental_scores_manifest()
        rodenbach = next(m for m in manifest if m["tournament"] == "Rodenbach II")
        self.assertEqual(rodenbach["games_added"], 10)

    def test_world_cup_venue_corrections(self) -> None:
        tournaments = [
            {
                "name": "World Cup I",
                "country": "WC",
                "event_date": None,
            },
            {
                "name": "World Cup 2015",
                "country": "WC",
                "event_date": None,
            },
        ]
        applied = apply_catalog_corrections(tournaments)
        self.assertEqual(tournaments[0]["name"], "World Cup I (Dartford)")
        self.assertEqual(tournaments[0]["country"], "England")
        self.assertEqual(tournaments[1]["name"], "World Cup XV (Dublin)")
        self.assertEqual(tournaments[1]["country"], "Ireland")
        self.assertEqual(len(WORLD_CUP_VENUES), 23)
        self.assertEqual(
            catalog_name_after_corrections("World Cup VIII"),
            world_cup_catalog_name("World Cup VIII"),
        )

    def test_catalog_split_appends_synthetic_row(self) -> None:
        tournaments = [
            {
                "name": "Groningen VII",
                "source_id": 23,
                "chrono": 23.0,
                "event_date": None,
                "is_cup": True,
                "country": "Netherlands",
                "equal_teams": False,
                "player_count": 9,
            }
        ]
        applied = apply_catalog_splits(tournaments)
        self.assertEqual(len(tournaments), 2)
        cup = tournaments[1]
        self.assertEqual(cup["name"], "Groningen VII Cup")
        self.assertEqual(cup["source_id"], IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE + 1)
        self.assertEqual(cup["chrono"], 23.5)
        self.assertTrue(cup["is_cup"])
        self.assertEqual(cup["player_count"], 8)
        self.assertEqual(len(applied), 1)
        manifest = catalog_splits_manifest()
        self.assertEqual(manifest[0]["tournament"], "Groningen VII Cup")


if __name__ == "__main__":
    unittest.main()
