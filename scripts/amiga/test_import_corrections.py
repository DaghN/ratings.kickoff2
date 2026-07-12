"""Unit tests for import_corrections supplemental Scores."""

from __future__ import annotations

import unittest

from scripts.amiga.import_access import AccessScore, merge_supplemental_scores
from scripts.amiga.tournament_format import TournamentFormatInference
from scripts.amiga.import_corrections import (
    IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE,
    IMPORT_SUPPLEMENT_SCORES_ID_BASE,
    SCORE_CORRECTIONS,
    SUPPLEMENTAL_SCORES,
    WORLD_CUP_VENUES,
    apply_catalog_corrections,
    apply_catalog_split_format_overrides,
    apply_catalog_splits,
    apply_player_country_corrections,
    apply_score_corrections,
    catalog_name_after_corrections,
    catalog_splits_manifest,
    resolve_score_tournament_partition,
    supplemental_scores_manifest,
    world_cup_catalog_name,
)


class ImportCorrectionsTests(unittest.TestCase):
    @staticmethod
    def _catalog_parents_for_all_splits() -> list[dict]:
        return [
            {
                "name": "Groningen VII",
                "source_id": 23,
                "chrono": 23.0,
                "event_date": None,
                "is_cup": True,
                "country": "Netherlands",
                "equal_teams": False,
                "player_count": 9,
            },
            {
                "name": "Gloucester III",
                "source_id": 62,
                "chrono": 37.0,
                "event_date": None,
                "is_cup": False,
                "country": "England",
                "equal_teams": False,
                "player_count": 10,
            },
            {
                "name": "Hertford IV",
                "source_id": 165,
                "chrono": 154.0,
                "event_date": None,
                "is_cup": False,
                "country": "England",
                "equal_teams": True,
                "player_count": 4,
            },
        ]

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

    def test_player_country_overrides_fill_missing_identity(self) -> None:
        countries = {"Mark B": "England"}
        applied = apply_player_country_corrections(countries)
        self.assertEqual(countries["Kjetil D"], "Norway")
        self.assertEqual(countries["Mark B"], "England")
        kjetil = next(entry for entry in applied if entry["player"] == "Kjetil D")
        self.assertEqual(kjetil["access"], "")
        self.assertEqual(kjetil["canonical"], "Norway")

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
        tournaments = self._catalog_parents_for_all_splits()
        applied = apply_catalog_splits(tournaments)
        self.assertEqual(len(tournaments), 6)
        cup = next(t for t in tournaments if t["name"] == "Groningen VII Cup")
        self.assertEqual(cup["source_id"], IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE + 1)
        self.assertEqual(cup["chrono"], 23.5)
        self.assertTrue(cup["is_cup"])
        self.assertEqual(cup["player_count"], 8)
        self.assertEqual(len(applied), 3)
        manifest = catalog_splits_manifest()
        self.assertEqual(manifest[0]["tournament"], "Groningen VII Cup")

    def test_catalog_split_format_override_cup_only(self) -> None:
        format_by_name = {
            "Groningen VII Cup": TournamentFormatInference(
                has_league=True,
                has_cup=True,
                game_count=14,
            ),
            "Gloucester III Team": TournamentFormatInference(
                has_league=True,
                has_cup=False,
                game_count=10,
            ),
        }
        applied = apply_catalog_split_format_overrides(format_by_name)
        cup = format_by_name["Groningen VII Cup"]
        self.assertFalse(cup.has_league)
        self.assertTrue(cup.has_cup)
        team = format_by_name["Gloucester III Team"]
        self.assertTrue(team.has_league)
        self.assertFalse(team.has_cup)
        self.assertEqual(len(applied), 1)
        self.assertEqual(applied[0]["tournament"], "Groningen VII Cup")

    def test_hertford_iv_catalog_split_appends_child(self) -> None:
        tournaments = self._catalog_parents_for_all_splits()
        applied = apply_catalog_splits(tournaments)
        cup = next(t for t in tournaments if t["name"] == "Hertford IV Cup")
        self.assertEqual(cup["source_id"], IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE + 3)
        self.assertEqual(cup["chrono"], 154.5)
        self.assertTrue(cup["is_cup"])
        self.assertEqual(cup["player_count"], 4)
        self.assertEqual(len(applied), 3)
        manifest = catalog_splits_manifest()
        hertford = next(m for m in manifest if m["tournament"] == "Hertford IV Cup")
        self.assertEqual(hertford["parent"], "Hertford IV")
        self.assertEqual(hertford["source_id"], IMPORT_CATALOG_SPLIT_SOURCE_ID_BASE + 3)

    def test_hertford_iv_score_partition_routes_cup_ssids(self) -> None:
        for ssid in (7579, 7580, 7581, 7582):
            self.assertEqual(
                resolve_score_tournament_partition("Hertford IV", ssid),
                "Hertford IV Cup",
            )
        for ssid in (7555, 7570, 7578):
            self.assertEqual(
                resolve_score_tournament_partition("Hertford IV", ssid),
                "Hertford IV",
            )
        self.assertEqual(resolve_score_tournament_partition("Other", 7579), "Other")

    def test_hertford_iv_format_overrides_league_and_cup_only(self) -> None:
        format_by_name = {
            "Hertford IV": TournamentFormatInference(
                has_league=False,
                has_cup=True,
                game_count=28,
            ),
            "Hertford IV Cup": TournamentFormatInference(
                has_league=True,
                has_cup=True,
                game_count=0,
            ),
        }
        applied = apply_catalog_split_format_overrides(format_by_name)
        parent = format_by_name["Hertford IV"]
        cup = format_by_name["Hertford IV Cup"]
        self.assertTrue(parent.has_league)
        self.assertFalse(parent.has_cup)
        self.assertFalse(cup.has_league)
        self.assertTrue(cup.has_cup)
        changed = {entry["tournament"] for entry in applied}
        self.assertEqual(changed, {"Hertford IV", "Hertford IV Cup"})

    def test_kristiansand_score_corrections(self) -> None:
        scores = [
            AccessScore(1189, "Aasmund F", "Glenn L", 1, 1, "Kristiansand", None, None),
            AccessScore(1188, "Oskar B", "Glenn L", 0, 0, "Kristiansand", None, None),
            AccessScore(2421, "Gianni T", "Marco C", 7, 2, "Milan", None, None),
            AccessScore(2422, "Gianni T", "Morris C", 0, 5, "Milan", None, None),
            AccessScore(15981, "Frederic B", "Cornelius H", 3, 2, "Duesseldorf V", None, None),
            AccessScore(1, "A", "B", 0, 0, "Other", None, None),
        ]
        applied = apply_score_corrections(scores)
        self.assertEqual(len(applied), len(SCORE_CORRECTIONS))
        semi = next(s for s in scores if s.source_id == 1189)
        self.assertEqual((semi.goals_a, semi.goals_b), (0, 0))
        self.assertEqual(semi.extra, "(1-0) aet")
        self.assertEqual((semi.goals_et_a, semi.goals_et_b), (1, 0))
        bronze = next(s for s in scores if s.source_id == 1188)
        self.assertEqual((bronze.goals_a, bronze.goals_b), (1, 1))
        self.assertEqual((bronze.pens_a, bronze.pens_b), (7, 8))
        self.assertEqual(bronze.extra, "1-1, (0-0, 7-8 on pens)")
        other = next(s for s in scores if s.source_id == 1)
        self.assertIsNone(other.goals_et_a)

    def test_milan_i_player_assignment_corrections(self) -> None:
        scores = [
            AccessScore(1189, "Aasmund F", "Glenn L", 1, 1, "Kristiansand", None, None),
            AccessScore(1188, "Oskar B", "Glenn L", 0, 0, "Kristiansand", None, None),
            AccessScore(2421, "Gianni T", "Marco C", 7, 2, "Milan", "Round 1 - Group A", None),
            AccessScore(2422, "Gianni T", "Morris C", 0, 5, "Milan", "Round 1 - Group A", None),
            AccessScore(15981, "Frederic B", "Cornelius H", 3, 2, "Duesseldorf V", None, None),
        ]
        apply_score_corrections(scores)
        g2421 = next(s for s in scores if s.source_id == 2421)
        self.assertEqual(g2421.team_b, "Marco M")
        self.assertEqual((g2421.goals_a, g2421.goals_b), (7, 2))
        g2422 = next(s for s in scores if s.source_id == 2422)
        self.assertEqual(g2422.team_a, "Filippo D")
        self.assertEqual((g2422.goals_a, g2422.goals_b), (0, 5))
        g15981 = next(s for s in scores if s.source_id == 15981)
        self.assertEqual(g15981.team_b, "Volker B")
        self.assertEqual((g15981.goals_a, g15981.goals_b), (3, 2))


if __name__ == "__main__":
    unittest.main()
