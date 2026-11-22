"""Tests for L4b scoring contract presets (SC-1) and reader/validate (SC-2)."""

from __future__ import annotations

import unittest
from datetime import datetime
from unittest.mock import MagicMock

from scripts.amiga.scoring_contract import (
    KNOCKOUT_TIE_DEFAULT_STEPS,
    KNOCKOUT_TIE_PRIMITIVE,
    LEAGUE_TABLE_DEFAULT_STEPS,
    LEAGUE_TABLE_PRIMITIVE,
    PLATFORM_DEFAULT_V1,
    SCORING_SCHEMA_VERSION,
    StageScoringContract,
    default_steps_for_primitive,
    freeze_scoring_contracts_for_tournament,
    primitive_for_stage_type,
    sync_unfrozen_stage_scoring_contracts,
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

    def test_catalog_legacy_knockout_chain_valid(self) -> None:
        from scripts.amiga.scoring_contract import LEGACY_KNOCKOUT_BRIDGE_STEPS

        contract = StageScoringContract(
            stage_id=2,
            tournament_id=10,
            stage_key="qf",
            stage_type="knockout",
            primitive=KNOCKOUT_TIE_PRIMITIVE,
            schema_version=SCORING_SCHEMA_VERSION,
            win_points=3,
            draw_points=1,
            loss_points=0,
            steps=LEGACY_KNOCKOUT_BRIDGE_STEPS,
        )
        self.assertEqual(validate_stage_scoring_contract(contract), [])


class KnockoutExtraTimeTests(unittest.TestCase):
    def _order(self, legs: list[dict]) -> list[int]:
        from scripts.amiga.scoring_contract import LEGACY_KNOCKOUT_BRIDGE_STEPS, default_scoring_context
        from scripts.amiga.tournament_standings import PlayerStanding, _knockout_positions

        table = {1: PlayerStanding(), 2: PlayerStanding()}
        for leg in legs:
            for pid, own, other in (
                (leg["player_a_id"], leg["goals_a"], leg["goals_b"]),
                (leg["player_b_id"], leg["goals_b"], leg["goals_a"]),
            ):
                table[pid].goals_for += own
                table[pid].goals_against += other
        contract = default_scoring_context(1).default_knockout
        self.assertEqual(contract.steps, LEGACY_KNOCKOUT_BRIDGE_STEPS)
        return [pid for pid, _st, _pos in sorted(_knockout_positions(table, legs, contract), key=lambda r: r[2])]

    @staticmethod
    def _leg(a: int, ga: int, gb: int, extra=None, et=(None, None), pens=(None, None)) -> dict:
        return {
            "player_a_id": a, "player_b_id": 3 - a, "goals_a": ga, "goals_b": gb, "extra": extra,
            "goals_et_a": et[0], "goals_et_b": et[1], "pens_a": pens[0], "pens_b": pens[1],
        }

    def test_extra_time_beats_the_leg_result(self) -> None:
        # World Cup X 27th: 2 loses the second leg 4-5 in normal time but wins it 6-5 after extra time.
        legs = [self._leg(1, 2, 3), self._leg(2, 4, 5, "6-5 e.t.", et=(2, 0))]
        self.assertEqual(self._order(legs), [2, 1])

    def test_penalties_after_level_extra_time(self) -> None:
        legs = [self._leg(1, 2, 4), self._leg(2, 2, 4, "(3-5) 4-5 p.k.", et=(1, 1), pens=(4, 5))]
        self.assertEqual(self._order(legs), [1, 2])


class FreezeScoringContractTests(unittest.TestCase):
    def _cursor(self, *, fetchone=None, rowcount: int = 0) -> MagicMock:
        cur = MagicMock()
        cur.fetchone.return_value = fetchone
        cur.rowcount = rowcount
        return cur

    def test_sync_unfrozen_stage_scoring_contracts_returns_rowcount(self) -> None:
        conn = MagicMock()
        conn.cursor.return_value.__enter__.return_value = self._cursor(rowcount=3)

        updated = sync_unfrozen_stage_scoring_contracts(conn, 89)

        self.assertEqual(updated, 3)
        sql = conn.cursor.return_value.__enter__.return_value.execute.call_args[0][0]
        self.assertIn("frozen_scoring_primitive IS NULL", sql)

    def test_freeze_new_tournament_sets_marker_and_syncs_stages(self) -> None:
        conn = MagicMock()
        select_cur = self._cursor(fetchone={"scoring_frozen_at": None})
        sync_cur = self._cursor(rowcount=11)
        marker_cur = self._cursor(rowcount=1)
        conn.cursor.return_value.__enter__.side_effect = [select_cur, sync_cur, marker_cur]

        frozen_at = datetime(2026, 7, 11, 12, 0, 0)
        result = freeze_scoring_contracts_for_tournament(conn, 89, frozen_at)

        self.assertEqual(result, {"tournament": 1, "stages": 11, "skipped": False})

    def test_freeze_already_frozen_syncs_stages_only(self) -> None:
        conn = MagicMock()
        frozen_at = datetime(2026, 7, 9, 22, 59, 15)
        select_cur = self._cursor(fetchone={"scoring_frozen_at": frozen_at})
        sync_cur = self._cursor(rowcount=1)
        conn.cursor.return_value.__enter__.side_effect = [select_cur, sync_cur]

        result = freeze_scoring_contracts_for_tournament(conn, 89, datetime(2026, 7, 11, 12, 0, 0))

        self.assertEqual(result, {"tournament": 0, "stages": 1, "skipped": False})
        self.assertEqual(conn.cursor.return_value.__enter__.call_count, 2)


if __name__ == "__main__":
    unittest.main()
