"""Prepare platform v2 — shared constants (see docs/work-db-prepare.md §4)."""

from __future__ import annotations

# Never migrate / zero-derived / replay on these (clone sources only).
PROTECTED_BASELINE_DATABASES = frozenset({"ko2unity_baseline", "kooldb2"})

# Never refresh or prepare-mutate dev browser DB.
PROTECTED_DEV_DATABASE = "ko2unity_db"

# §4.5 — truncate at zero-derived (unlock rows / aggregates, not catalog).
AGGREGATE_TABLES_TRUNCATE: tuple[str, ...] = (
    "player_period_games",
    "player_peak_period_games",
    "server_daily_activity",
    "player_period_league",
    "player_matchup_summary",
    "server_period_game_totals",
    "server_period_matchups",
    "player_monthly_league",
    "player_milestones",
    "player_play_streaks",
    "player_league_award",
    "player_league_totals",
    "player_league_slice_totals",
    "league_period",
)

# Do not truncate — static catalog from migrations (seed separately if needed).
CATALOG_TABLES_NEVER_TRUNCATE: frozenset[str] = frozenset({"milestone_definitions"})

DEFAULT_PROFILES: dict[str, dict[str, str | int]] = {
    "local-work": {
        "work_database": "ko2unity_work",
        "baseline_database": "ko2unity_baseline",
        "host": "127.0.0.1",
        "port": 3306,
        "user": "root",
        "password": "",
    },
    "staging-work": {
        "work_database": "kooldb1",
        "baseline_database": "kooldb2",
        "host": "127.0.0.1",
        "port": 3306,
        "user": "root",
        "password": "",
    },
}
