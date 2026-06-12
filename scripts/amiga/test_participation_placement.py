"""Unit tests for participation placement derivation."""

from __future__ import annotations

import unittest

from scripts.amiga.participation_placement import (
    compute_tier_a_knockout_finish,
    compute_tier_b_league_cup_finish,
    derive_best_knockout_phase,
    derive_event_finish_position,
    derive_wc_group_positions,
    is_main_bracket_knockout_label,
    is_main_final_label,
    participation_is_winner,
    placement_final_winner_loser_ranks,
    resolve_primary_league_standings,
)


class ParticipationPlacementTests(unittest.TestCase):
    def test_league_scope_wins_over_knockout(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 3},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
        ]
        positions = derive_event_finish_position(
            rows,
            tournament_name="London XXIII",
            has_league=True,
            has_cup=False,
        )
        self.assertEqual(positions[1], 3)

    def test_wc_event_finish_is_null(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Group B", "player_id": 9, "position": 2},
            {"scope_type": "league", "scope_key": "Group A", "player_id": 9, "position": 4},
            {"scope_type": "knockout", "scope_key": "Final|9-10", "player_id": 9, "position": 1},
        ]
        positions = derive_event_finish_position(rows, tournament_name="World Cup XII")
        self.assertIsNone(positions.get(9))

    def test_wc_winner_flag_uses_medal(self) -> None:
        self.assertTrue(
            participation_is_winner(
                tournament_name="World Cup XII",
                wc_medal="gold",
            )
        )
        self.assertFalse(
            participation_is_winner(
                tournament_name="World Cup XII",
                wc_medal="none",
            )
        )

    def test_non_wc_winner_requires_finish_one(self) -> None:
        self.assertFalse(
            participation_is_winner(
                tournament_name="World Cup XIV (Copenhagen)",
                event_finish_position=None,
                wc_medal="none",
            )
        )
        self.assertTrue(
            participation_is_winner(
                tournament_name="Bournemouth II",
                event_finish_position=1,
            )
        )
        self.assertFalse(
            participation_is_winner(
                tournament_name="Bournemouth II",
                event_finish_position=2,
            )
        )

    def test_derive_wc_group_positions_picks_lexicographic_group(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Group B", "player_id": 5, "position": 1},
            {"scope_type": "league", "scope_key": "Group A", "player_id": 5, "position": 3},
        ]
        self.assertEqual(derive_wc_group_positions(rows)[5], 3)


class ResolvePrimaryLeagueStandingsTests(unittest.TestCase):
    def test_null_phase_implicit_league(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 1},
            {"scope_type": "league", "scope_key": "", "player_id": 2, "position": 2},
        ]
        self.assertEqual(resolve_primary_league_standings(rows), {1: 1, 2: 2})

    def test_single_league_stage_scope(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 10, "position": 1},
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 20, "position": 2},
        ]
        self.assertEqual(resolve_primary_league_standings(rows), {10: 1, 20: 2})

    def test_empty_key_wins_over_labeled_scopes(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 5},
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 1, "position": 1},
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 2, "position": 2},
        ]
        self.assertEqual(resolve_primary_league_standings(rows), {1: 5})

    def test_multi_scope_picks_largest_player_count(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Round 1 - Group A", "player_id": 1, "position": 1},
            {"scope_type": "league", "scope_key": "Round 1 - Group B", "player_id": 2, "position": 1},
            {"scope_type": "league", "scope_key": "Round 1 - Group B", "player_id": 3, "position": 2},
        ]
        self.assertEqual(resolve_primary_league_standings(rows), {2: 1, 3: 2})

    def test_athens_xci_style_tier_b_league_stage_only(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 10, "position": 3},
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 20, "position": 1},
            {"scope_type": "league", "scope_key": "League Stage", "player_id": 30, "position": 2},
            {"scope_type": "knockout", "scope_key": "Final|10-20", "player_id": 20, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|10-20", "player_id": 10, "position": 2},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="Athens XCI",
            has_league=True,
            has_cup=True,
        )
        self.assertEqual(finish[20], 1)
        self.assertEqual(finish[10], 2)
        self.assertEqual(finish[30], 2)


class EventFinishPositionTests(unittest.TestCase):
    def _bournemouth_ii_rows(self) -> list[dict]:
        return [
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 73, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 286, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 30, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 422, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 421, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 30, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 405, "position": 2},
        ]

    def test_tier_a_bournemouth_ii_shared_semi_bronze(self) -> None:
        finish = derive_event_finish_position(
            self._bournemouth_ii_rows(),
            tournament_name="Bournemouth II",
        )
        self.assertEqual(finish[134], 1)
        self.assertEqual(finish[73], 2)
        self.assertEqual(finish[286], 3)
        self.assertEqual(finish[30], 3)
        self.assertGreaterEqual(finish[422], 5)
        self.assertGreaterEqual(finish[421], 5)
        self.assertGreaterEqual(finish[405], 5)

    def test_tier_a_matches_compute_tier_a_helper(self) -> None:
        rows = self._bournemouth_ii_rows()
        self.assertEqual(
            derive_event_finish_position(rows, tournament_name="Bournemouth II"),
            compute_tier_a_knockout_finish(rows),
        )

    def test_tier_e_override_beats_generic_ko_finish(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
        ]
        generic = derive_event_finish_position(rows, tournament_name="Kitchen Cup")
        self.assertEqual(generic[1], 1)
        self.assertEqual(generic[2], 2)

        overridden = derive_event_finish_position(
            rows,
            tournament_name="Kitchen Cup",
            overrides={2: 1, 1: 2},
        )
        self.assertEqual(overridden[1], 2)
        self.assertEqual(overridden[2], 1)

    def test_tier_e_override_wins_over_primary_league(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Group A", "player_id": 9, "position": 2},
        ]
        self.assertEqual(
            derive_event_finish_position(rows, tournament_name="Exotic Format").get(9),
            2,
        )
        self.assertEqual(
            derive_event_finish_position(
                rows,
                tournament_name="Exotic Format",
                overrides={9: 5},
            )[9],
            5,
        )

    def test_tier_c_london_xxiii_primary_league(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 73, "position": 1},
            {"scope_type": "league", "scope_key": "", "player_id": 88, "position": 2},
            {"scope_type": "league", "scope_key": "", "player_id": 99, "position": 3},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="London XXIII",
            has_league=True,
            has_cup=False,
        )
        self.assertEqual(finish[73], 1)
        self.assertEqual(finish[88], 2)
        self.assertEqual(finish[99], 3)

    def test_tier_a_third_place_final_assigns_three_and_four(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|3-4", "player_id": 3, "position": 1},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|3-4", "player_id": 4, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|1-3", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|1-3", "player_id": 3, "position": 2},
        ]
        finish = derive_event_finish_position(rows, tournament_name="Reading Cup")
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)
        self.assertEqual(finish[3], 3)
        self.assertEqual(finish[4], 4)

    def test_tier_a_ignores_subsidiary_cup_final(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "Silver Cup Final|5-6", "player_id": 5, "position": 1},
            {"scope_type": "knockout", "scope_key": "Silver Cup Final|5-6", "player_id": 6, "position": 2},
        ]
        finish = derive_event_finish_position(rows, tournament_name="Birmingham XXI")
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)
        self.assertGreaterEqual(finish[5], 5)
        self.assertGreaterEqual(finish[6], 5)

    def test_is_main_final_label_rejects_subsidiary_cups(self) -> None:
        self.assertTrue(is_main_final_label("Final"))
        self.assertFalse(is_main_final_label("Silver Cup Final"))
        self.assertFalse(is_main_final_label("3rd Place Final"))

    def test_tier_d_wc_returns_null_finish(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Group A", "player_id": 9, "position": 4},
            {"scope_type": "knockout", "scope_key": "Final|9-10", "player_id": 9, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|9-10", "player_id": 10, "position": 2},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="World Cup XII",
            has_league=True,
            has_cup=True,
        )
        self.assertEqual(finish, {})
        self.assertIsNone(
            derive_event_finish_position(
                rows,
                tournament_name="World Cup XII",
                player_ids=[9],
            )[9],
        )

    def test_tier_b_final_only_league_third_is_third(self) -> None:
        """Minimal league+cup: title match only; 3rd = league 3rd among non-finalists."""
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 1},
            {"scope_type": "league", "scope_key": "", "player_id": 2, "position": 2},
            {"scope_type": "league", "scope_key": "", "player_id": 3, "position": 3},
            {"scope_type": "league", "scope_key": "", "player_id": 4, "position": 4},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="Athens LXXXV",
            has_league=True,
            has_cup=True,
        )
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)
        self.assertEqual(finish[3], 3)
        self.assertEqual(finish[4], 4)

    def test_tier_b_cup_overrides_league_for_finalists(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 10, "position": 1},
            {"scope_type": "league", "scope_key": "", "player_id": 20, "position": 2},
            {"scope_type": "league", "scope_key": "", "player_id": 30, "position": 3},
            {"scope_type": "knockout", "scope_key": "Final|10-30", "player_id": 30, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|10-30", "player_id": 10, "position": 2},
        ]
        finish = compute_tier_b_league_cup_finish(rows)
        self.assertEqual(finish[30], 1)
        self.assertEqual(finish[10], 2)
        self.assertEqual(finish[20], 2)

    def test_tier_b_shared_semi_bronze_overrides_league(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 1},
            {"scope_type": "league", "scope_key": "", "player_id": 2, "position": 2},
            {"scope_type": "league", "scope_key": "", "player_id": 3, "position": 3},
            {"scope_type": "league", "scope_key": "", "player_id": 4, "position": 4},
            {"scope_type": "league", "scope_key": "", "player_id": 5, "position": 5},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Finals|1-3", "player_id": 3, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 2, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Finals|2-4", "player_id": 4, "position": 2},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="Milan X",
            has_league=True,
            has_cup=True,
        )
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)
        self.assertEqual(finish[3], 3)
        self.assertEqual(finish[4], 3)
        self.assertEqual(finish[5], 5)

    def test_placement_final_fifth_place_ranks(self) -> None:
        self.assertEqual(placement_final_winner_loser_ranks("5th Place Final"), (5, 6))
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
            {"scope_type": "knockout", "scope_key": "5th Place Final|5-6", "player_id": 5, "position": 1},
            {"scope_type": "knockout", "scope_key": "5th Place Final|5-6", "player_id": 6, "position": 2},
        ]
        finish = compute_tier_a_knockout_finish(rows)
        self.assertEqual(finish[5], 5)
        self.assertEqual(finish[6], 6)

    def test_tier_b_flags_without_primary_league_falls_back_to_tier_a(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|1-2", "player_id": 2, "position": 2},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="Bournemouth II",
            has_league=True,
            has_cup=True,
        )
        self.assertEqual(finish[1], 1)
        self.assertEqual(finish[2], 2)

    def test_player_ids_filter_returns_none_for_missing(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "", "player_id": 1, "position": 1},
        ]
        finish = derive_event_finish_position(
            rows,
            tournament_name="London XXIII",
            player_ids=[1, 999],
        )
        self.assertEqual(finish[1], 1)
        self.assertIsNone(finish[999])


class BestKnockoutPhaseTests(unittest.TestCase):
    def _bournemouth_ii_rows(self) -> list[dict]:
        return [
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Final|73-134", "player_id": 73, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|73-286", "player_id": 286, "position": 2},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Final|30-134", "player_id": 30, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 73, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|73-422", "player_id": 422, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 134, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|134-421", "player_id": 421, "position": 2},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 30, "position": 1},
            {"scope_type": "knockout", "scope_key": "Quarter Final|30-405", "player_id": 405, "position": 2},
        ]

    def test_finalist_gets_final_label(self) -> None:
        rows = self._bournemouth_ii_rows()
        self.assertEqual(derive_best_knockout_phase(rows, 134), "Final")
        self.assertEqual(derive_best_knockout_phase(rows, 73), "Final")

    def test_semi_final_exit_label(self) -> None:
        rows = self._bournemouth_ii_rows()
        self.assertEqual(derive_best_knockout_phase(rows, 286), "Semi Final")

    def test_quarter_final_exit_label(self) -> None:
        rows = self._bournemouth_ii_rows()
        self.assertEqual(derive_best_knockout_phase(rows, 422), "Quarter Final")

    def test_no_knockout_returns_none(self) -> None:
        rows = [{"scope_type": "league", "scope_key": "", "player_id": 1, "position": 1}]
        self.assertIsNone(derive_best_knockout_phase(rows, 1))

    def test_subsidiary_cup_final_ignored(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Quarter Final|1-2", "player_id": 1, "position": 2},
            {"scope_type": "knockout", "scope_key": "Silver Cup Final|1-9", "player_id": 1, "position": 1},
        ]
        self.assertEqual(derive_best_knockout_phase(rows, 1), "Quarter Final")
        self.assertFalse(is_main_bracket_knockout_label("Silver Cup Final"))

    def test_wc_semi_final_label(self) -> None:
        rows = [
            {"scope_type": "league", "scope_key": "Round 1 - Group A", "player_id": 9, "position": 1},
            {"scope_type": "knockout", "scope_key": "Semi Finals|9-10", "player_id": 9, "position": 2},
        ]
        self.assertEqual(derive_best_knockout_phase(rows, 9), "Semi Finals")

    def test_placement_final_deeper_than_quarter(self) -> None:
        rows = [
            {"scope_type": "knockout", "scope_key": "Quarter Final|1-2", "player_id": 1, "position": 1},
            {"scope_type": "knockout", "scope_key": "3rd Place Final|1-3", "player_id": 1, "position": 1},
        ]
        self.assertEqual(derive_best_knockout_phase(rows, 1), "3rd Place Final")


class EventFinishDbIntegrationTests(unittest.TestCase):
    """Optional ko2amiga_db dry-run (skipped when local config missing)."""

    @classmethod
    def setUpClass(cls) -> None:
        try:
            import pymysql
            from pymysql.cursors import DictCursor

            from scripts.amiga.config import load_amiga_db_config

            cfg = load_amiga_db_config()
            cls._conn = pymysql.connect(
                host=cfg.host,
                user=cfg.user,
                password=cfg.password,
                database=cfg.database,
                port=cfg.port,
                cursorclass=DictCursor,
            )
        except Exception as exc:  # pragma: no cover - environment dependent
            cls._conn = None
            cls._skip_reason = str(exc)

    @classmethod
    def tearDownClass(cls) -> None:
        if cls._conn is not None:
            cls._conn.close()

    def _load_tournament(self, tournament_id: int) -> tuple[dict, list[dict]]:
        if self._conn is None:
            self.skipTest(self._skip_reason)
        cur = self._conn.cursor()
        cur.execute(
            "SELECT id, name, has_league, has_cup FROM tournaments WHERE id = %s",
            (tournament_id,),
        )
        tournament = cur.fetchone()
        cur.execute(
            """
            SELECT scope_type, scope_key, player_id, position
            FROM amiga_tournament_standings
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        return tournament, list(cur.fetchall())

    def test_bournemouth_ii_best_knockout_phase_from_db(self) -> None:
        tournament, rows = self._load_tournament(544)
        self.assertEqual(derive_best_knockout_phase(rows, 134), "Final")
        self.assertEqual(derive_best_knockout_phase(rows, 286), "Semi Final")

    def test_athens_lxxxv_league_cup_from_db(self) -> None:
        tournament, rows = self._load_tournament(592)
        finish = derive_event_finish_position(
            rows,
            tournament_name=tournament["name"],
            has_league=bool(tournament["has_league"]),
            has_cup=bool(tournament["has_cup"]),
        )
        self.assertEqual(finish[14], 1)
        self.assertEqual(finish[30], 2)
        self.assertEqual(finish[410], 3)
        self.assertEqual(finish[338], 4)
        self.assertEqual(finish[100], 5)


if __name__ == "__main__":
    unittest.main()
