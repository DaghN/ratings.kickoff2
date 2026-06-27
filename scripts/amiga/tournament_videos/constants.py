"""Paths and source registry for tournament video harvest (TV-1)."""

from __future__ import annotations

from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[3]
DATA_DIR = REPO_ROOT / "data" / "amiga" / "tournament_videos"
RAW_DIR = DATA_DIR / "raw"
REVIEW_CSV = DATA_DIR / "review.csv"
MANIFEST_JSON = REPO_ROOT / "site" / "public_html" / "data" / "amiga" / "tournament_videos.json"

FORUM_URL = "https://ko-gathering.com/forum/viewtopic.php?t=15358"
WC_FINALS_PLAYLIST_ID = "PL_BZxDPPd88YKbQHLod_1EFHPa2iJSIQY"

USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

SOURCE_PRIORITY = {
    "wc_finals_playlist": 0,
    "forum_index": 1,
    "ko2cv_channel": 2,
    "alkelele_channel": 3,
    "costas_channel": 4,
    "manual": 5,
}

YOUTUBE_SOURCES: list[dict[str, str | None]] = [
    {
        "url": "https://www.youtube.com/@KO2CV_TV/videos",
        "source": "ko2cv_channel",
        "source_channel": "ko2cv",
        "source_playlist": None,
    },
    {
        "url": "https://www.youtube.com/@Alkelele/videos",
        "source": "alkelele_channel",
        "source_channel": "alkelele",
        "source_playlist": None,
    },
    {
        "url": "https://www.youtube.com/@11costas11/videos",
        "source": "costas_channel",
        "source_channel": "costas",
        "source_playlist": None,
    },
    {
        "url": f"https://www.youtube.com/playlist?list={WC_FINALS_PLAYLIST_ID}",
        "source": "wc_finals_playlist",
        "source_channel": None,
        "source_playlist": WC_FINALS_PLAYLIST_ID,
    },
]

CSV_COLUMNS = [
    "youtube_id",
    "title",
    "duration_sec",
    "guessed_tournament_id",
    "tournament_guess_label",
    "year",
    "kind",
    "stage",
    "leg",
    "score",
    "player_a_guess",
    "player_a_id_guess",
    "player_b_guess",
    "player_b_id_guess",
    "game_id_guess",
    "source",
    "source_channel",
    "source_playlist",
    "relation_group",
    "relation",
    "featured_final",
    "confidence",
    "verified",
    "notes",
    "external_url",
    "wc_video_slot",
]

PLAYLIST_OFFLINE_WC_MAX_YEAR = 2023