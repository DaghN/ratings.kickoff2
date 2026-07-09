"""Tests for L4b scoring contract presets (SC-1) and reader/validate (SC-2)."""

from __future__ import annotations

import unittest

from scripts.amiga.scoring_contract import (
    KNOCKOUT_TIE_DEFAULT_STEPS,
    KNOCKOUT_TIE_PRIMITIVE,
    LEAGUE_TABLE_DEFAULT_STEPS,
    LEAGUE_TABLE_PRIMITIVE,
    PLATFORM_DEFAULT_V1,
    SCORING_SCHEMA_VERSION,
    StageScoringContract,
    default_steps_for_primitive,
    primitive_for_stage_type,
    validate_stage_scoring_contract,
)


def _sample_league_contract(*, steps: tuple[str, ...] | None = None) -> StageScoringContract:
    return StageScoringContract(
        stage_id=1,
        tournament_id=10,
        stage_key="overall",
        stage_type="round_robin",
        primitive=LEAGUE_TABLE_PRIMITIVE,
        schema_version=SCORING_SCHEMA_VERSION,
        win_points=3,
        draw_points=1,
        loss_points=0,
        steps=LEAGUE_TABLE_DEFAULT_STEPS if steps is None else steps,
    )


class ScoringContractPresetTests(unittest.TestCase):
    def test_platform_default_v1_constants(self) -> None:
        self.assertEqual(PLATFORM_DEFAULT_V1, "platform_default_v1")
        self.assertEqual(SCORING_SCHEMA_VERSION, 1)

    def test_primitive_for_stage_type(self) -> None:
        self.assertEqual(primitive_for_stage_type("round_robin"), LEAGUE_TABLE_PRIMITIVE)
        self.assertEqual(primitive_for_stage_type("knockout"), KNOCKOUT_TIE_PRIMITIVE)
        with self.assertRaises(ValueError):
            primitive_for_stage_type("swiss")

    def test_default_chains_match_policy(self) -> None:
        self.assertEqual(
            default_steps_for_primitive(LEAGUE_TABLE_PRIMITIVE),
            LEAGUE_TABLE_DEFAULT_STEPS,
        )
        self.assertEqual(
            default_steps_for_primitive(KNOCKOUT_TIE_PRIMITIVE),
            KNOCKOUT_TIE_DEFAULT_STEPS,
        )
        self.assertNotIn("head_to_head", LEAGUE_TABLE_DEFAULT_STEPS)
        self.assertNotIn("golden_goal", KNOCKOUT_TIE_DEFAULT_STEPS)


class ScoringContractValidateTests(unittest.TestCase):
    def test_valid_league_contract(self) -> None:
        self.assertEqual(validate_stage_scoring_contract(_sample_league_contract()), [])

    def test_rejects_unknown_schema_version(self) -> None:
        bad = _sample_league_contract()
        bad = StageScoringContract(
            stage_id=bad.stage_id,
            tournament_id=bad.tournament_id,
            stage_key=bad.stage_key,
            stage_type=bad.stage_type,
            primitive=bad.primitive,
            schema_version=99,
            win_points=bad.win_points,
            draw_points=bad.draw_points,
            loss_points=bad.loss_points,
            steps=bad.steps,
        )
        errors = validate_stage_scoring_contract(bad)
        self.assertTrue(any("scoring_schema_version=99" in err for err in errors))

    def test_rejects_primitive_stage_type_mismatch(self) -> None:
        bad = _sample_league_contract()
        bad = StageScoringContract(
            stage_id=bad.stage_id,
            tournament_id=bad.tournament_id,
            stage_key=bad.stage_key,
            stage_type="knockout",
            primitive=LEAGUE_TABLE_PRIMITIVE,
            schema_version=bad.schema_version,
            win_points=bad.win_points,
            draw_points=bad.draw_points,
            loss_points=bad.loss_points,
            steps=bad.steps,
        )
        errors = validate_stage_scoring_contract(bad)
        self.assertTrue(any("does not match stage_type" in err for err in errors))

    def test_rejects_empty_steps(self) -> None:
        errors = validate_stage_scoring_contract(_sample_league_contract(steps=()))
        self.assertTrue(
            any(
                "scoring step chain is empty" in err or "must include points step" in err
                for err in errors
            )
        )

    def test_rejects_wrong_step_for_primitive(self) -> None:
        errors = validate_stage_scoring_contract(
            _sample_league_contract(steps=("aggregate_goal_difference",))
        )
        self.assertTrue(any("not allowed for primitive" in err for err in errors))


if __name__ == "__main__":
    unittest.main()
