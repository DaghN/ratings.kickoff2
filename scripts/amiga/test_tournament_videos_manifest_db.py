from __future__ import annotations

import unittest

from scripts.amiga.tournament_videos.game_match import GameRow, match_game
from scripts.amiga.tournament_videos.game_links import (
    MatchFactLink,
    _parse_start_sec,
    is_dual_leg_video_row,
    is_game_link_locked,
    is_stream_map_row,
    manifest_game_start_sec,
    row_needs_game_link_audit,
    validate_sidecar_schema,
    verify_manifest_start_sec_parity,
)
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

    def test_match_game_reversed_score_same_orientation(self) -> None:
        games = [
            GameRow(17268, 31, 156, 4, 1, "Final"),
        ]
        gid, note = match_game(games, player_a_id=31, player_b_id=156, score="1 - 4")
        self.assertEqual(gid, 17268)
        self.assertIsNone(note)

    def test_is_dual_leg_video_row(self) -> None:
        self.assertTrue(
            is_dual_leg_video_row({"notes": "dual-leg video; WC 2025 final", "game_link_mode": ""})
        )
        self.assertTrue(is_dual_leg_video_row({"game_link_mode": "multi", "notes": ""}))
        self.assertFalse(is_dual_leg_video_row({"notes": "not dual-leg", "game_link_mode": ""}))

    def test_is_game_link_locked(self) -> None:
        self.assertTrue(is_game_link_locked({"verified": "Y"}))
        self.assertFalse(is_game_link_locked({"verified": ""}))

    def test_is_stream_map_row(self) -> None:
        self.assertTrue(is_stream_map_row({"game_link_mode": "stream_map"}))
        self.assertFalse(is_stream_map_row({"game_link_mode": "multi"}))

    def test_row_needs_game_link_audit(self) -> None:
        self.assertTrue(row_needs_game_link_audit({"kind": "match", "youtube_id": "x"}))
        self.assertFalse(row_needs_game_link_audit({"kind": "stream", "youtube_id": "x"}))

    def test_parse_start_sec_hm(self) -> None:
        self.assertEqual(_parse_start_sec("0:13"), 780)
        self.assertEqual(_parse_start_sec("1:26"), 5160)
        self.assertEqual(_parse_start_sec("5:36"), 20160)
        self.assertEqual(_parse_start_sec("5160"), 5160)

    def test_manifest_game_start_sec(self) -> None:
        from scripts.amiga.tournament_videos import game_links

        game_links._load_sidecar_index.cache_clear()
        links = [
            MatchFactLink("yt1", 1, "T", "A", "B", "1-0", "final", 1, 120),
            MatchFactLink("yt1", 2, "T", "B", "A", "0-1", "final", 2, 900),
        ]
        original = game_links.sidecar_links_for_video

        def fake_sidecar(youtube_id: str) -> list[MatchFactLink]:
            return links if youtube_id == "yt1" else []

        game_links.sidecar_links_for_video = fake_sidecar  # type: ignore[assignment]
        try:
            starts = manifest_game_start_sec("yt1", [10, 11])
            self.assertEqual(starts, [120, 900])
        finally:
            game_links.sidecar_links_for_video = original  # type: ignore[assignment]
            game_links._load_sidecar_index.cache_clear()

    def test_validate_sidecar_schema_duplicate_ordinal(self) -> None:
        from scripts.amiga.tournament_videos import game_links

        game_links._load_sidecar_index.cache_clear()
        issues = validate_sidecar_schema([{"youtube_id": "abc"}])
        self.assertFalse(any("duplicate" in i for i in issues))

    def test_verify_manifest_start_sec_parity_mismatch(self) -> None:
        from scripts.amiga.tournament_videos import game_links

        game_links._load_sidecar_index.cache_clear()
        links = [
            MatchFactLink("yt1", 1, "T", "A", "B", "1-0", "stream", None, 780),
        ]
        original = game_links.sidecar_links_for_video

        def fake_sidecar(youtube_id: str) -> list[MatchFactLink]:
            return links if youtube_id == "yt1" else []

        game_links.sidecar_links_for_video = fake_sidecar  # type: ignore[assignment]
        try:
            manifest = [
                {
                    "youtube_id": "yt1",
                    "game_ids": [10],
                    "game_start_sec": [13],
                }
            ]
            errors = verify_manifest_start_sec_parity(manifest)
            self.assertTrue(any("780" in e and "13" in e for e in errors))
        finally:
            game_links.sidecar_links_for_video = original  # type: ignore[assignment]
            game_links._load_sidecar_index.cache_clear()


if __name__ == "__main__":
    unittest.main()