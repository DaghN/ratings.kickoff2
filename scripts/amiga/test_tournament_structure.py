"""Unit tests for tournament structure spec parsing."""

from __future__ import annotations

import unittest

from scripts.amiga.tournament_structure.apply import ApplyContext, apply_structure_spec, structure_specs_manifest
from scripts.amiga.tournament_structure.registry import all_structure_specs
from scripts.amiga.tournament_structure.materialize_legacy import (
    AUTO_RR,
    NEEDS_STRUCTURE_REVIEW,
    classify_null_phase_tournament,
    null_phase_round_robin_bucket,
    round_robin_legs,
    stage_bucket_for_game,
)
from scripts.amiga.tournament_structure.specs import (
    FixtureSpec,
    GroupRosterSpec,
    StageSpec,
    StructureSpec,
    parse_structure_spec,
)
from scripts.amiga.tournament_standings import _fixture_scope
from scripts.amiga.tournament_phases import ScopeType


class FixtureScopeMappingTests(unittest.TestCase):
    def test_round_robin_overall_maps_to_league(self) -> None:
        scope, elimination = _fixture_scope(
            {"fixture_id": 1, "stage_type": "round_robin", "stage_key": "overall", "stage_name": "Overall"},
            10,
            20,
        )
        self.assertIsNotNone(scope)
        assert scope is not None
        self.assertEqual(scope.scope_type, ScopeType.LEAGUE)
        self.assertEqual(scope.scope_key, "")
        self.assertFalse(elimination)

    def test_round_robin_group_maps_to_labeled_league(self) -> None:
        scope, elimination = _fixture_scope(
            {
                "fixture_id": 1,
                "stage_type": "round_robin",
                "stage_key": "group-a",
                "stage_name": "Group A",
            },
            10,
            20,
        )
        self.assertIsNotNone(scope)
        assert scope is not None
        self.assertEqual(scope.scope_type, ScopeType.LEAGUE)
        self.assertEqual(scope.scope_key, "Group A")
        self.assertFalse(elimination)

    def test_knockout_maps_to_pair_scope(self) -> None:
        scope, elimination = _fixture_scope(
            {
                "fixture_id": 2,
                "stage_type": "knockout",
                "stage_key": "semi",
                "fixture_phase_label": "Semi-final",
            },
            10,
            20,
        )
        self.assertIsNotNone(scope)
        assert scope is not None
        self.assertEqual(scope.scope_type, ScopeType.KNOCKOUT)
        self.assertTrue(elimination)
        self.assertIn("10", scope.scope_key)
        self.assertIn("20", scope.scope_key)

    def test_null_fixture_id_returns_none(self) -> None:
        self.assertIsNone(_fixture_scope({"stage_type": "round_robin"}, 1, 2))

class MaterializeLegacyTests(unittest.TestCase):
    def test_null_phase_marathon_is_auto_rr(self) -> None:
        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 3},
        ]
        self.assertEqual(classify_null_phase_tournament(games), AUTO_RR)
        self.assertEqual(round_robin_legs(games), 1)
        bucket = null_phase_round_robin_bucket()
        self.assertEqual(bucket.stage_type, "round_robin")
        self.assertEqual(bucket.stage_key, "overall")

    def test_null_phase_double_rr_is_auto_rr(self) -> None:
        # 3 players, single leg = 3 games; double RR = 6 games, 4 per player
        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 1},
            {"player_a_id": 3, "player_b_id": 1},
            {"player_a_id": 3, "player_b_id": 2},
        ]
        self.assertEqual(round_robin_legs(games), 2)
        self.assertEqual(classify_null_phase_tournament(games), AUTO_RR)

    def test_null_phase_uneven_per_player_needs_review(self) -> None:
        # Duesseldorf V pattern: 4 players, 18 games (3× total) but uneven per-player
        games = []
        pairs = [
            (71, 126),
            (71, 345),
            (71, 465),
            (126, 345),
            (126, 465),
            (345, 465),
        ]
        for _ in range(3):
            for a, b in pairs:
                games.append({"player_a_id": a, "player_b_id": b})
        # Drop one game for player 465 and add one for 71 → uneven counts
        games.pop()
        games.append({"player_a_id": 71, "player_b_id": 126})
        self.assertIsNone(round_robin_legs(games))
        self.assertEqual(classify_null_phase_tournament(games), NEEDS_STRUCTURE_REVIEW)

    def test_null_phase_incomplete_rr_needs_review(self) -> None:
        # 3 players full RR = 3 games; 2 games = withdrawal / incomplete
        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
        ]
        self.assertEqual(classify_null_phase_tournament(games), NEEDS_STRUCTURE_REVIEW)

    def test_null_phase_cup_needs_review(self) -> None:
        # 6 players, 6 games — Athens IV pattern; not auto-classifiable
        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 3, "player_b_id": 4},
            {"player_a_id": 5, "player_b_id": 6},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 4},
            {"player_a_id": 5, "player_b_id": 1},
        ]
        self.assertEqual(classify_null_phase_tournament(games), NEEDS_STRUCTURE_REVIEW)

    def test_labeled_knockout_is_one_stage_per_tie(self) -> None:
        bucket = stage_bucket_for_game(
            {"phase": "Semi Finals", "player_a_id": 10, "player_b_id": 20},
            all_null_phase=False,
        )
        self.assertEqual(bucket.stage_type, "knockout")
        self.assertEqual(bucket.name, "Semi Finals")
        self.assertIn("10-20", bucket.stage_key)

        other_pair = stage_bucket_for_game(
            {"phase": "Semi Finals", "player_a_id": 30, "player_b_id": 40},
            all_null_phase=False,
        )
        self.assertNotEqual(bucket.stage_key, other_pair.stage_key)


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
                    "stage_type": "round_robin",
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
            {"stage_key": "overall", "stage_type": "round_robin", "name": "Overall"}
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


class VerifyLegacyTests(unittest.TestCase):
    def test_classify_tier_a_full_rr(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_A, classify_legacy_tier

        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 3},
        ]
        tier, detail = classify_legacy_tier(games, tournament_name="Test Marathon", format_overrides={})
        self.assertEqual(tier, TIER_A)
        self.assertIn("round-robin", detail)

    def test_classify_tier_a_double_rr(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_A, classify_legacy_tier

        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 1},
            {"player_a_id": 3, "player_b_id": 1},
            {"player_a_id": 3, "player_b_id": 2},
        ]
        tier, detail = classify_legacy_tier(games, tournament_name="Double Kitchen", format_overrides={})
        self.assertEqual(tier, TIER_A)
        self.assertIn("2×", detail)

    def test_classify_tier_c_manual_review_flag(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_C, classify_legacy_tier

        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 3},
        ]
        tier, detail = classify_legacy_tier(
            games,
            tournament_name="Duesseldorf V",
            format_overrides={},
            tournament_id=416,
        )
        self.assertEqual(tier, TIER_C)
        self.assertIn("audit flag", detail)

    def test_classify_tier_c_athens_lxxxv_mixed_labeled(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_C, classify_legacy_tier

        games = [
            {"player_a_id": 1, "player_b_id": 2, "phase": "Round 1"},
            {"player_a_id": 3, "player_b_id": 4},
        ]
        tier, detail = classify_legacy_tier(
            games,
            tournament_name="Athens LXXXV",
            format_overrides={},
            tournament_id=592,
        )
        self.assertEqual(tier, TIER_C)
        self.assertIn("audit flag", detail)

    def test_classify_tier_b_wc_deferred_detail(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_B, classify_legacy_tier

        games = [{"player_a_id": 1, "player_b_id": 2, "phase": "Group A"}]
        tier, detail = classify_legacy_tier(
            games,
            tournament_name="World Cup XVIII (Bournemouth)",
            format_overrides={},
            tournament_id=5,
        )
        self.assertEqual(tier, TIER_B)
        self.assertIn("WC track deferred", detail)

    def test_materialize_refuses_parser_fix_deferred(self) -> None:
        from scripts.amiga.tournament_structure.materialize_legacy import (
            StructureReviewRequired,
            _connect,
            materialize_legacy_fixtures,
        )

        conn = _connect()
        try:
            with self.assertRaises(StructureReviewRequired) as ctx:
                materialize_legacy_fixtures(conn, 48, dry_run=True)
            self.assertIn("6a", str(ctx.exception))
            self.assertIn("PARSER_FIX", str(ctx.exception))
        finally:
            conn.close()

    def test_classify_tier_c_athens_iv_pattern(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_C, classify_legacy_tier

        games = [
            {"player_a_id": 1, "player_b_id": 2},
            {"player_a_id": 3, "player_b_id": 4},
            {"player_a_id": 5, "player_b_id": 6},
            {"player_a_id": 1, "player_b_id": 3},
            {"player_a_id": 2, "player_b_id": 4},
            {"player_a_id": 5, "player_b_id": 1},
        ]
        tier, detail = classify_legacy_tier(games, tournament_name="Athens IV Cup", format_overrides={})
        self.assertEqual(tier, TIER_C)
        self.assertIn("needs_structure_review", detail)

    def test_classify_tier_b_labeled_phases(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_B, classify_legacy_tier

        games = [{"player_a_id": 1, "player_b_id": 2, "phase": "Semi Finals"}]
        tier, _ = classify_legacy_tier(games, tournament_name="Some Cup", format_overrides={})
        self.assertEqual(tier, TIER_B)

    def test_classify_tier_d_registry(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import TIER_D, classify_legacy_tier

        tier, detail = classify_legacy_tier([], tournament_name="Homburg", format_overrides={})
        self.assertEqual(tier, TIER_D)
        self.assertIn("StructureSpec", detail)

    def test_standings_compare_detects_mismatch(self) -> None:
        from scripts.amiga.tournament_structure.verify_legacy import _standings_compare

        stored = [
            {
                "player_id": 1,
                "scope_type": "league",
                "scope_key": "",
                "position": 1,
                "games": 2,
                "wins": 1,
                "draws": 1,
                "losses": 0,
                "goals_for": 3,
                "goals_against": 2,
                "points": 4,
            }
        ]
        computed = [dict(stored[0], points=3)]
        mismatches = _standings_compare(stored, computed)
        self.assertTrue(any("points" in m for m in mismatches))


class BulkTierATests(unittest.TestCase):
    def test_bulk_cli_requires_mode(self) -> None:
        from scripts.amiga.tournament_structure.bulk_tier_a import main

        self.assertEqual(main([]), 1)


class BulkTierBNonWcTests(unittest.TestCase):
    def test_register_auto_ok_count(self) -> None:
        from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
            NON_WC_TIER_B_AUTO_MATERIALIZE_IDS,
        )

        self.assertEqual(len(NON_WC_TIER_B_AUTO_MATERIALIZE_IDS), 41)

    def test_is_slice_6_auto_ok_pilot_75(self) -> None:
        from scripts.amiga.tournament_structure.tier_b_non_wc_register import is_slice_6_auto_ok

        self.assertTrue(is_slice_6_auto_ok(75, "Gloucester I Cup"))

    def test_is_slice_6_auto_ok_wc_deferred(self) -> None:
        from scripts.amiga.tournament_structure.tier_b_non_wc_register import is_slice_6_auto_ok

        self.assertFalse(is_slice_6_auto_ok(5, "World Cup XVIII (Bournemouth)"))

    def test_is_slice_6_auto_ok_review_refused(self) -> None:
        from scripts.amiga.tournament_structure.tier_b_non_wc_register import is_slice_6_auto_ok

        self.assertFalse(is_slice_6_auto_ok(592, "Athens LXXXV"))

    def test_is_slice_6_auto_ok_parser_fix_refused(self) -> None:
        from scripts.amiga.tournament_structure.tier_b_non_wc_register import is_slice_6_auto_ok

        self.assertFalse(is_slice_6_auto_ok(48, "Groningen VII"))

    def test_bulk_cli_requires_mode(self) -> None:
        from scripts.amiga.tournament_structure.bulk_tier_b_non_wc import main

        self.assertEqual(main([]), 1)

    def test_tier_b_candidate_ids_count(self) -> None:
        from scripts.amiga.tournament_structure.bulk_tier_b_non_wc import (
            _connect,
            tier_b_non_wc_candidate_ids,
        )

        conn = _connect()
        try:
            ids = tier_b_non_wc_candidate_ids(conn, skip_materialized=False)
            self.assertEqual(len(ids), 41)
            self.assertNotIn(5, ids)
            self.assertNotIn(48, ids)
            self.assertNotIn(592, ids)
            self.assertIn(75, ids)
        finally:
            conn.close()


if __name__ == "__main__":
    unittest.main()
