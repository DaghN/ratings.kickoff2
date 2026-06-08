"""Unit tests for tournament structure spec parsing."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_structure.apply import ApplyContext, apply_structure_spec, structure_specs_manifest
from scripts.amiga.tournament_structure.registry import all_structure_specs
from scripts.amiga.tournament_structure.specs import (
    FixtureSpec,
    GroupRosterSpec,
    StageSpec,
    StructureSpec,
    parse_structure_spec,
)


class StructureSpecParsingTests(unittest.TestCase):
    def test_minimal_spec(self) -> None:
        spec = parse_structure_spec(
            {
                "catalog_name": "Homburg",
                "template_slug": "group_knockout",
            }
        )
        self.assertEqual(spec.catalog_name, "Homburg")
        self.assertEqual(spec.template_slug, "group_knockout")
        self.assertEqual(spec.stages, ())
        self.assertEqual(spec.fixtures, ())

    def test_full_spec_round_trip(self) -> None:
        data = {
            "catalog_name": "Homburg",
            "template_slug": "group_knockout",
            "evidence_url": "https://ko-gathering.com/forum/viewtopic.php?t=7711",
            "format_overrides": {"has_league": True, "has_cup": True},
            "stages": [
                {
                    "stage_key": "groups",
                    "name": "Group stage",
                    "stage_type": "league_groups",
                    "group_keys": ["A", "B"],
                    "groups": [
                        {"group_key": "A", "player_names": ["Alice", "Bob"]},
                    ],
                },
                {
                    "stage_key": "knockout",
                    "name": "Knockout",
                    "stage_type": "knockout",
                    "round_keys": ["last_16", "quarter", "semi", "final"],
                },
            ],
            "fixtures": [
                {
                    "fixture_key": "group-a-001",
                    "stage_key": "groups",
                    "group_key": "A",
                    "player_a": "Alice",
                    "player_b": "Bob",
                },
                {
                    "fixture_key": "sf-001-leg-1",
                    "stage_key": "knockout",
                    "round_key": "semi",
                    "player_a": "Alice",
                    "player_b": "Bob",
                    "leg_no": 1,
                },
            ],
        }
        spec = parse_structure_spec(data)
        self.assertEqual(len(spec.stages), 2)
        self.assertEqual(spec.stages[0].group_keys, ("A", "B"))
        self.assertEqual(spec.stages[0].groups[0].player_names, ("Alice", "Bob"))
        self.assertEqual(spec.fixtures[1].leg_no, 1)
        self.assertEqual(spec.fixtures[1].round_key, "semi")

        round_trip = spec.to_dict()
        self.assertEqual(round_trip["catalog_name"], "Homburg")
        self.assertEqual(len(round_trip["stages"]), 2)
        self.assertEqual(len(round_trip["fixtures"]), 2)

    def test_dataclass_from_dict_helpers(self) -> None:
        group = GroupRosterSpec.from_dict({"group_key": "H", "players": ["P1", "P2"]})
        self.assertEqual(group.player_names, ("P1", "P2"))

        stage = StageSpec.from_dict(
            {"stage_key": "overall", "stage_type": "league", "name": "Overall"}
        )
        self.assertEqual(stage.name, "Overall")

        fixture = FixtureSpec.from_dict(
            {"fixture_key": "f1", "stage_key": "groups", "leg_no": 2}
        )
        self.assertEqual(fixture.leg_no, 2)

    def test_parse_requires_catalog_name(self) -> None:
        with self.assertRaises(ValueError):
            parse_structure_spec({"template_slug": "group_knockout"})

    def test_registry_has_homburg_and_stub(self) -> None:
        from scripts.amiga.tournament_structure.registry import active_structure_specs, all_registry_entries

        self.assertEqual(len(all_registry_entries()), 2)
        active = active_structure_specs()
        self.assertEqual(len(active), 1)
        self.assertEqual(active[0].catalog_name, "Homburg")
        self.assertEqual(active[0].template_slug, "group_knockout")

    def test_homburg_fixture_counts(self) -> None:
        from scripts.amiga.tournament_structure.homburg import HOMEBURG_SPEC
        from scripts.amiga.tournament_structure.build import _expand_group_fixtures, _expand_knockout_fixtures

        groups = _expand_group_fixtures(HOMEBURG_SPEC, {})
        ko = _expand_knockout_fixtures(HOMEBURG_SPEC)
        self.assertEqual(len(groups), 52)
        self.assertEqual(len(ko), 34)


class StructureVerifyTests(unittest.TestCase):
    def test_verify_homburg_ok(self) -> None:
        from scripts.amiga.tournament_structure.verify import verify_catalog

        result = verify_catalog("Homburg")
        self.assertTrue(result.ok, result.errors)
        self.assertEqual(result.planned_fixture_count, 86)

    def test_verify_stub_fails_gracefully(self) -> None:
        from scripts.amiga.tournament_structure.verify import verify_catalog

        result = verify_catalog("Athens LXI")
        self.assertFalse(result.ok)
        self.assertTrue(any("stub" in e.lower() for e in result.errors))

    def test_verify_unregistered_fails(self) -> None:
        from scripts.amiga.tournament_structure.verify import verify_catalog

        result = verify_catalog("Totally Fake Tournament Zz")
        self.assertFalse(result.ok)
        self.assertTrue(any("unregistered" in e.lower() or "no structure" in e.lower() for e in result.errors))


class ApplyStructureSpecTests(unittest.TestCase):
    def test_link_pair_matching(self) -> None:
        from scripts.amiga.tournament_structure.build import BuiltFixture, StructureBuildResult
        from scripts.amiga.tournament_structure.link import link_games_to_fixtures

        build = StructureBuildResult(
            tournament_id=1,
            catalog_name="Homburg",
            fixtures=(
                BuiltFixture(fixture_id=10, player_a_id=1, player_b_id=2, stage_key="g", leg_no=1),
                BuiltFixture(fixture_id=11, player_a_id=1, player_b_id=2, stage_key="ko", leg_no=1),
            ),
            fixture_count=2,
            stage_count=1,
        )
        rows = [
            {"tournament_id": 1, "player_a_id": 2, "player_b_id": 1},
            {"tournament_id": 1, "player_a_id": 1, "player_b_id": 2},
        ]
        result = link_games_to_fixtures(rows, tournament_id=1, build=build)
        self.assertEqual(result.linked, 2)
        self.assertEqual(result.orphans, 0)
        self.assertEqual(rows[0]["fixture_id"], 10)
        self.assertEqual(rows[1]["fixture_id"], 11)


if __name__ == "__main__":
    unittest.main()
