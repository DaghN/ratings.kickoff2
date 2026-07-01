from __future__ import annotations

import unittest

from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    lookup_player_id,
    player_pair_matches,
    sync_csv_row,
)


class ManifestDbTest(unittest.TestCase):
    def test_player_pair_matches_swap(self) -> None:
        self.assertTrue(player_pair_matches(1, 2, 1, 2))
        self.assertTrue(player_pair_matches(1, 2, 2, 1))
        self.assertFalse(player_pair_matches(1, 2, 3, 2))

    def test_sync_player_id_from_name(self) -> None:
        snap = DbSnapshot(
            players_by_id={440: "Thor S", 444: "Tobias B"},
            players_by_name_key={"thor s": [440], "tobias b": [444]},
            tournaments_by_id={9: "World Cup XIX (Bremen)"},
            tournaments_by_name={"World Cup XIX (Bremen)": 9},
            games_by_id={
                25458: {
                    "id": 25458,
                    "tournament_id": 9,
                    "player_a_id": 440,
                    "player_b_id": 30,
                    "source_scores_id": 1,
                }
            },
        )
        row = {
            "youtube_id": "test",
            "kind": "match",
            "player_a_guess": "Thor S",
            "player_a_id_guess": "444",
            "player_b_guess": "Andy G",
            "player_b_id_guess": "30",
            "game_id_guess": "25458",
            "guessed_tournament_id": "9",
            "tournament_guess_label": "World Cup XIX (Bremen)",
        }
        changes = sync_csv_row(row, snap)
        self.assertEqual(row["player_a_id_guess"], "440")
        self.assertTrue(any("440" in c for c in changes))

    def test_lookup_player_id(self) -> None:
        snap = DbSnapshot(
            players_by_name_key={"oliver st": [341]},
        )
        self.assertEqual(lookup_player_id("Oliver St", snap), 341)


if __name__ == "__main__":
    unittest.main()