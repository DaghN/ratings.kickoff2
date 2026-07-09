"""Tests for L4b scoring contract presets (SC-1)."""

from __future__ import annotations

import unittest

from scripts.amiga.scoring_contract import (
    KNOCKOUT_TIE_DEFAULT_STEPS,
    KNOCKOUT_TIE_PRIMITIVE,
    LEAGUE_TABLE_DEFAULT_STEPS,
    LEAGUE_TABLE_PRIMITIVE,
    PLATFORM_DEFAULT_V1,
    SCORING_SCHEMA_VERSION,
    default_steps_for_primitive,
    primitive_for_stage_type,
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


if __name__ == "__main__":
    unittest.main()
