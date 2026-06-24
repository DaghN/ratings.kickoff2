"""In-memory World Cup country slice row helpers."""

from __future__ import annotations

from typing import Any

from scripts.amiga.country_slice_columns import (
    COUNTRY_SLICE_STAT_COLUMNS,
    COUNTRY_UNKNOWN_TOKEN,
    SLICE_KEY_WORLD_CUP,
)
from scripts.amiga.player_geo_year import normalize_country


def country_token_for_player(
    player_countries: dict[int, str | None],
    player_id: int,
) -> str:
    own = normalize_country(player_countries.get(player_id))
    return own if own else COUNTRY_UNKNOWN_TOKEN


def empty_country_world_cup_slice() -> dict[str, Any]:
    row: dict[str, Any] = {"slice_key": SLICE_KEY_WORLD_CUP}
    for col in COUNTRY_SLICE_STAT_COLUMNS:
        if col in {
            "wc_participations_per_player",
            "games_per_player",
            "domestic_game_share",
            "international_game_share",
            "games_share",
            "goals_share",
            "points_per_realm_wc",
            "win_rate",
            "average_opponent_rating",
            "performance_rating",
            "goal_ratio",
            "double_digits_ratio",
            "clean_sheets_ratio",
            "double_digits_conceded_ratio",
            "clean_sheets_conceded_ratio",
        }:
            row[col] = None
        else:
            row[col] = 0
    return row


def _ratio_db(num: int, den: int, *, precision: int = 4) -> float | None:
    if den <= 0:
        return None
    return round(num / den, precision)


def _goal_ratio(goals_for: int, goals_against: int) -> float | None:
    if goals_against <= 0:
        return None
    return round(goals_for / goals_against, 8)


def finalize_country_slice_row(row: dict[str, Any]) -> None:
    """Derive ratios, shares, and per-player averages on a completed row."""
    players = int(row.get("players") or 0)
    games = int(row.get("games") or 0)
    participations = int(row.get("wc_participations") or 0)
    gf = int(row.get("goals_for") or 0)
    ga = int(row.get("goals_against") or 0)
    wins = int(row.get("wins") or 0)
    draws = int(row.get("draws") or 0)
    points = int(row.get("points") or 0)
    domestic = int(row.get("domestic_games") or 0)
    international = int(row.get("international_games") or 0)
    realm_games = int(row.get("realm_wc_player_games") or 0)
    realm_gf = int(row.get("realm_wc_goals_for") or 0)
    realm_wcs = int(row.get("realm_wc_tournament_count") or 0)

    row["podiums"] = int(row.get("gold") or 0) + int(row.get("silver") or 0) + int(row.get("bronze") or 0)
    row["wc_participations_per_player"] = _ratio_db(participations, players, precision=4)
    row["games_per_player"] = _ratio_db(games, players, precision=4)
    row["domestic_game_share"] = _ratio_db(domestic, games, precision=6)
    row["international_game_share"] = _ratio_db(international, games, precision=6)
    row["games_share"] = _ratio_db(games, realm_games, precision=6) if realm_games > 0 else None
    row["goals_share"] = _ratio_db(gf, realm_gf, precision=6) if realm_gf > 0 else None
    row["goal_ratio"] = _goal_ratio(gf, ga)
    row["points_per_realm_wc"] = _ratio_db(points, realm_wcs, precision=4) if realm_wcs > 0 else None
    row["win_rate"] = _ratio_db(wins * 2 + draws, games * 2, precision=6) if games > 0 else None

    dd = int(row.get("double_digits") or 0)
    cs = int(row.get("clean_sheets") or 0)
    ddc = int(row.get("double_digits_conceded") or 0)
    csc = int(row.get("clean_sheets_conceded") or 0)
    row["double_digits_ratio"] = _ratio_db(dd, games)
    row["clean_sheets_ratio"] = _ratio_db(cs, games)
    row["double_digits_conceded_ratio"] = _ratio_db(ddc, games)
    row["clean_sheets_conceded_ratio"] = _ratio_db(csc, games)


def country_slice_from_db_row(row: dict[str, Any]) -> dict[str, Any]:
    out = empty_country_world_cup_slice()
    for key in COUNTRY_SLICE_STAT_COLUMNS:
        if key in row:
            out[key] = row[key]
    return out
