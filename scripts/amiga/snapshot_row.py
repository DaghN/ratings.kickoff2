"""Build full player event snapshot rows and present projection rows."""

from __future__ import annotations

from datetime import datetime
from typing import Any

from scripts.amiga.career_rise import (
    CAREER_RISE_PLAYER_COLUMNS,
    apply_career_rise_fields,
    career_rise_from_row,
    empty_career_rise_state,
    prior_career_values_from_row,
)
from scripts.amiga.generalstats_columns import (
    GEO_RISE_PLAYER_COLUMNS,
    GEO_YEAR_PLAYER_COLUMNS,
    HONOURS_RISE_PLAYER_COLUMNS,
    RECORD_RISE_PLAYER_COLUMNS,
)
from scripts.k2_rating_core.player_state import PlayerState

# Catalog + keys (snapshot only).
_SNAPSHOT_KEY_COLUMNS: tuple[str, ...] = (
    "player_id",
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "is_cup",
    "country",
    "has_league",
    "has_cup",
    "finalized_at",
)

# Event-local block (mirrors amiga_player_tournament_participation).
EVENT_LOCAL_COLUMNS: tuple[str, ...] = (
    "event_finish_position",
    "event_points",
    "games",
    "wins",
    "draws",
    "losses",
    "goals_for",
    "goals_against",
    "avg_goals_for",
    "avg_goals_against",
    "rating_before",
    "rating_delta",
    "rating_after",
    "performance_rating",
    "games_in_event",
    "is_winner",
    "best_knockout_phase",
)

# Honours cumulative on snapshots (honours_last_* avoids catalog key collision).
HONOURS_SNAPSHOT_COLUMNS: tuple[str, ...] = (
    "tournaments_played",
    "tournaments_won",
    "event_gold",
    "event_silver",
    "event_bronze",
    "event_podiums",
    "honours_last_event_date",
    "honours_last_tournament_id",
)

HONOURS_CURRENT_COLUMNS: tuple[str, ...] = (
    "tournaments_played",
    "tournaments_won",
    "event_gold",
    "event_silver",
    "event_bronze",
    "event_podiums",
)

_AMIGA_DROP_CAREER_GAME_IDS: frozenset[str] = frozenset(
    {"PeakRatingGameID", "LowestRatingGameID"}
)

_CAREER_SAMPLE = PlayerState().to_db_row(0)
CAREER_COLUMNS: tuple[str, ...] = tuple(
    key
    for key in _CAREER_SAMPLE
    if key != "ID" and key not in _AMIGA_DROP_CAREER_GAME_IDS
)

_CAREER_BEST_COLUMNS: tuple[str, ...] = (
    "career_best_performance_rating",
    "career_best_performance_tournament_id",
)

_RATING_EVENT_ANCHOR_COLUMNS: tuple[str, ...] = (
    "peak_rating_tournament_id",
    "lowest_rating_tournament_id",
)

_ELO_RANK_COLUMNS: tuple[str, ...] = ("elo_rank",)

SNAPSHOT_COLUMNS: tuple[str, ...] = (
    _SNAPSHOT_KEY_COLUMNS
    + EVENT_LOCAL_COLUMNS
    + CAREER_COLUMNS
    + _ELO_RANK_COLUMNS
    + HONOURS_SNAPSHOT_COLUMNS
    + _CAREER_BEST_COLUMNS
    + _RATING_EVENT_ANCHOR_COLUMNS
    + GEO_YEAR_PLAYER_COLUMNS
    + RECORD_RISE_PLAYER_COLUMNS
)

CURRENT_META_COLUMNS: tuple[str, ...] = (
    "last_tournament_id",
    "last_event_date",
    "last_finalized_at",
)

CURRENT_COLUMNS: tuple[str, ...] = (
    ("player_id",)
    + CURRENT_META_COLUMNS
    + CAREER_COLUMNS
    + _ELO_RANK_COLUMNS
    + HONOURS_CURRENT_COLUMNS
    + _CAREER_BEST_COLUMNS
    + _RATING_EVENT_ANCHOR_COLUMNS
    + GEO_YEAR_PLAYER_COLUMNS
    + RECORD_RISE_PLAYER_COLUMNS
)

_PARTICIPATION_TO_SNAPSHOT_EVENT: tuple[str, ...] = (
    "player_id",
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "is_cup",
    "country",
    "has_league",
    "has_cup",
    "finalized_at",
    *EVENT_LOCAL_COLUMNS,
)


def career_columns_from_player_state(player_id: int, state: PlayerState) -> dict[str, Any]:
    """Career block — Amiga snapshot/current columns from PlayerState."""
    row = state.to_db_row(player_id)
    row.pop("ID", None)
    for key in _AMIGA_DROP_CAREER_GAME_IDS:
        row.pop(key, None)
    row["peak_rating_tournament_id"] = state.peak_rating_tournament_id
    row["lowest_rating_tournament_id"] = state.lowest_rating_tournament_id
    return row


def honours_columns_from_totals_row(totals: dict[str, Any]) -> dict[str, Any]:
    """Map honours totals dict (increment_honours_totals shape) to snapshot honours block."""
    row: dict[str, Any] = {
        "tournaments_played": int(totals.get("tournaments_played") or 0),
        "tournaments_won": int(totals.get("tournaments_won") or 0),
        "event_gold": int(totals.get("event_gold") or 0),
        "event_silver": int(totals.get("event_silver") or 0),
        "event_bronze": int(totals.get("event_bronze") or 0),
        "event_podiums": int(totals.get("event_podiums") or 0),
        "honours_last_event_date": totals.get("last_event_date"),
        "honours_last_tournament_id": totals.get("last_tournament_id"),
    }
    for key in HONOURS_RISE_PLAYER_COLUMNS:
        row[key] = totals.get(key)
    return row


def _perf_qualifies(performance_rating: float | None, games: int) -> bool:
    return performance_rating is not None and games >= 2


def _perf_rank_key(
    performance_rating: float,
    games: int,
    tournament_id: int,
) -> tuple[float, int, int]:
    return (float(performance_rating), int(games), int(tournament_id))


def career_best_performance_fields(
    *,
    performance_rating: float | None,
    tournament_id: int,
    games: int,
    prior_rating: float | None = None,
    prior_tournament_id: int | None = None,
    prior_games: int = 0,
) -> tuple[float | None, int | None]:
    """
    Running career-best performance rating (LB tie-break: perf, event games, tournament_id).

    Matches ``amiga_lb_performance_rating_rows`` ordering for a single player timeline.
    """
    if not _perf_qualifies(performance_rating, games):
        return prior_rating, prior_tournament_id

    assert performance_rating is not None
    candidate = _perf_rank_key(performance_rating, games, tournament_id)
    if prior_rating is None or prior_tournament_id is None:
        return performance_rating, tournament_id

    prior = _perf_rank_key(prior_rating, prior_games, prior_tournament_id)
    if candidate > prior:
        return performance_rating, tournament_id
    return prior_rating, prior_tournament_id


def build_event_snapshot_row(
    *,
    participation: dict[str, Any],
    career: dict[str, Any],
    honours: dict[str, Any],
    career_best_performance_rating: float | None,
    career_best_performance_tournament_id: int | None,
    geo_year_scalars: dict[str, Any] | None = None,
    career_rise: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """
    Assemble one ``amiga_player_event_snapshots`` insert dict.

    ``participation`` should match ``participation_row_from_parts`` output (catalog + event-local).
    ``career`` from ``career_columns_from_player_state``; ``honours`` from ``honours_columns_from_totals_row``.
    """
    row: dict[str, Any] = {}
    for key in _PARTICIPATION_TO_SNAPSHOT_EVENT:
        if key in participation:
            row[key] = participation[key]

    for key in CAREER_COLUMNS + _RATING_EVENT_ANCHOR_COLUMNS:
        if key in career:
            row[key] = career[key]

    for key in HONOURS_SNAPSHOT_COLUMNS:
        if key in honours:
            row[key] = honours[key]

    row["career_best_performance_rating"] = career_best_performance_rating
    row["career_best_performance_tournament_id"] = career_best_performance_tournament_id

    if geo_year_scalars is None:
        geo_year_scalars = {
            "peak_year_games": 0,
            "peak_year_games_year": None,
            "peak_year_tournaments": 0,
            "peak_year_tournaments_year": None,
            "countries_played_in": 0,
            "opponent_countries_faced": 0,
            "opponent_countries_beaten": 0,
        }
        for key in GEO_RISE_PLAYER_COLUMNS:
            geo_year_scalars[key] = None
    for key in GEO_YEAR_PLAYER_COLUMNS:
        row[key] = geo_year_scalars.get(key)
    for key in GEO_RISE_PLAYER_COLUMNS:
        row[key] = geo_year_scalars.get(key)
    for key in HONOURS_RISE_PLAYER_COLUMNS:
        row[key] = honours.get(key)
    rise = career_rise if career_rise is not None else empty_career_rise_state()
    for key in CAREER_RISE_PLAYER_COLUMNS:
        row[key] = rise.get(key)

    row["elo_rank"] = None

    missing = [col for col in SNAPSHOT_COLUMNS if col not in row]
    if missing:
        raise ValueError(f"snapshot row missing columns: {missing}")

    return row


def current_row_from_snapshot(snapshot: dict[str, Any]) -> dict[str, Any]:
    """Project one snapshot row to ``amiga_player_current`` shape."""
    row: dict[str, Any] = {
        "player_id": int(snapshot["player_id"]),
        "last_tournament_id": int(snapshot["tournament_id"]),
        "last_event_date": snapshot.get("event_date"),
        "last_finalized_at": snapshot.get("finalized_at"),
    }

    for key in CAREER_COLUMNS + _RATING_EVENT_ANCHOR_COLUMNS:
        row[key] = snapshot[key]

    row["elo_rank"] = snapshot.get("elo_rank")

    for key in HONOURS_CURRENT_COLUMNS:
        row[key] = snapshot[key]

    row["career_best_performance_rating"] = snapshot.get("career_best_performance_rating")
    row["career_best_performance_tournament_id"] = snapshot.get(
        "career_best_performance_tournament_id"
    )

    for key in GEO_YEAR_PLAYER_COLUMNS:
        row[key] = snapshot.get(key)

    for key in RECORD_RISE_PLAYER_COLUMNS:
        row[key] = snapshot.get(key)

    missing = [col for col in CURRENT_COLUMNS if col not in row]
    if missing:
        raise ValueError(f"current row missing columns: {missing}")

    return row


def _upsert_sql(
    table: str,
    columns: tuple[str, ...],
    *,
    key_columns: tuple[str, ...],
) -> str:
    col_list = ", ".join(f"`{c}`" for c in columns)
    val_list = ", ".join(f"%({c})s" for c in columns)
    key_set = set(key_columns)
    updates = ", ".join(
        f"`{c}`=VALUES(`{c}`)" for c in columns if c not in key_set
    )
    return (
        f"INSERT INTO `{table}` ({col_list}) VALUES ({val_list}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def snapshot_insert_sql() -> str:
    """INSERT … ON DUPLICATE KEY UPDATE for amiga_player_event_snapshots."""
    return _upsert_sql(
        "amiga_player_event_snapshots",
        SNAPSHOT_COLUMNS,
        key_columns=("player_id", "tournament_id"),
    )


def current_upsert_sql() -> str:
    """INSERT … ON DUPLICATE KEY UPDATE for amiga_player_current."""
    return _upsert_sql(
        "amiga_player_current",
        CURRENT_COLUMNS,
        key_columns=("player_id",),
    )


def build_snapshot_from_finalize_parts(
    *,
    participation: dict[str, Any],
    player_state: PlayerState,
    honours_totals: dict[str, Any],
    prior_career_best_performance_rating: float | None = None,
    prior_career_best_performance_tournament_id: int | None = None,
    prior_career_best_games: int = 0,
    geo_year_scalars: dict[str, Any] | None = None,
    prior_career_row: dict[str, Any] | None = None,
) -> tuple[dict[str, Any], dict[str, Any]]:
    """
  Convenience for finalize: participation + PlayerState + totals → (snapshot, current).
    """
    player_id = int(participation["player_id"])
    tournament_id = int(participation["tournament_id"])
    games = int(participation.get("games") or 0)
    perf = participation.get("performance_rating")
    if perf is not None:
        perf = float(perf)

    career = career_columns_from_player_state(player_id, player_state)
    honours = honours_columns_from_totals_row(honours_totals)
    prior_career = prior_career_values_from_row(prior_career_row or {})
    rise_state = career_rise_from_row(prior_career_row or {})
    career_rise = apply_career_rise_fields(
        rise_state,
        prior_career,
        career,
        tournament_id=tournament_id,
        event_date=participation.get("event_date"),
    )
    best_rating, best_tid = career_best_performance_fields(
        performance_rating=perf,
        tournament_id=tournament_id,
        games=games,
        prior_rating=prior_career_best_performance_rating,
        prior_tournament_id=prior_career_best_performance_tournament_id,
        prior_games=prior_career_best_games,
    )
    snapshot = build_event_snapshot_row(
        participation=participation,
        career=career,
        honours=honours,
        career_best_performance_rating=best_rating,
        career_best_performance_tournament_id=best_tid,
        geo_year_scalars=geo_year_scalars,
        career_rise=career_rise,
    )
    return snapshot, current_row_from_snapshot(snapshot)
