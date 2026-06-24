"""Recompute World Cup country slice rows through a tournament cutoff."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.country_slice_columns import COUNTRY_UNKNOWN_TOKEN
from scripts.amiga.country_slice_game_stats import (
    CountryWorldCupSliceTracker,
    apply_wc_games_to_country_trackers,
)
from scripts.amiga.country_slice_totals import (
    empty_country_world_cup_slice,
    finalize_country_slice_row,
)
from scripts.amiga.slice_columns import SLICE_KEY_WORLD_CUP

log = logging.getLogger(__name__)

_WC_NAME_RE = r"^World Cup[[:space:]]+[^[:space:]]"

_SUM_PLAYER_COLS: tuple[str, ...] = (
    "gold",
    "silver",
    "bronze",
    "games",
    "wins",
    "draws",
    "losses",
    "goals_for",
    "goals_against",
    "points",
    "double_digits",
    "clean_sheets",
    "double_digits_conceded",
    "clean_sheets_conceded",
)

_MAX_PLAYER_COLS: tuple[str, ...] = (
    "most_goals_scored",
    "most_goals_conceded",
    "biggest_win_difference",
    "biggest_loss_difference",
    "biggest_sum_of_goals",
    "biggest_draw_sum",
)


def _country_token_sql(player_alias: str = "p") -> str:
    return (
        f"CASE WHEN TRIM({player_alias}.country) IS NULL OR TRIM({player_alias}.country) = '' "
        f"THEN '{COUNTRY_UNKNOWN_TOKEN}' ELSE TRIM({player_alias}.country) END"
    )


def _tournament_cutoff_sql(t_alias: str = "t") -> str:
    return f"""
        (
            {t_alias}.event_date < %s
            OR ({t_alias}.event_date = %s AND {t_alias}.chrono < %s)
            OR (
                {t_alias}.event_date = %s
                AND {t_alias}.chrono = %s
                AND {t_alias}.id <= %s
            )
        )
    """


def _cutoff_params(event_date: Any, event_chrono: float, tournament_id: int) -> tuple[Any, ...]:
    return (event_date, event_date, event_chrono, event_date, event_chrono, tournament_id)


def _load_player_rollups(conn: pymysql.connections.Connection) -> dict[str, dict[str, Any]]:
    token_sql = _country_token_sql("p")
    sum_cols = ", ".join(f"COALESCE(SUM(s.{c}), 0) AS {c}" for c in _SUM_PLAYER_COLS)
    max_cols = ", ".join(f"COALESCE(MAX(s.{c}), 0) AS {c}" for c in _MAX_PLAYER_COLS)
    sql = f"""
        SELECT {token_sql} AS country_token,
               COUNT(DISTINCT s.player_id) AS players,
               {sum_cols},
               {max_cols}
        FROM amiga_player_slice_totals s
        INNER JOIN amiga_players p ON p.id = s.player_id
        WHERE s.slice_key = %s
          AND s.tournaments_played >= 1
        GROUP BY country_token
    """
    with conn.cursor() as cur:
        cur.execute(sql, (SLICE_KEY_WORLD_CUP,))
        rows = cur.fetchall()
    return {str(r["country_token"]): dict(r) for r in rows}


def _load_participation_counts(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
) -> dict[str, dict[str, int]]:
    token_sql = _country_token_sql("p")
    cutoff = _tournament_cutoff_sql("t")
    cp = _cutoff_params(event_date, event_chrono, tournament_id)
    sql = f"""
        SELECT {token_sql} AS country_token,
               COUNT(DISTINCT s.tournament_id) AS tournaments_with_nation,
               COUNT(*) AS wc_participations
        FROM amiga_player_event_snapshots s
        INNER JOIN amiga_players p ON p.id = s.player_id
        INNER JOIN tournaments t ON t.id = s.tournament_id
        WHERE t.name REGEXP %s
          AND {cutoff}
        GROUP BY country_token
    """
    with conn.cursor() as cur:
        cur.execute(sql, (_WC_NAME_RE, *cp))
        rows = cur.fetchall()
    return {
        str(r["country_token"]): {
            "tournaments_with_nation": int(r["tournaments_with_nation"] or 0),
            "wc_participations": int(r["wc_participations"] or 0),
        }
        for r in rows
    }


def _load_realm_scalars(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
) -> dict[str, int]:
    cutoff = _tournament_cutoff_sql("t")
    cp = _cutoff_params(event_date, event_chrono, tournament_id)
    wc_sql = f"""
        SELECT COUNT(DISTINCT t.id) AS realm_wc_tournament_count
        FROM tournaments t
        WHERE t.name REGEXP %s
          AND {cutoff}
    """
    games_sql = f"""
        SELECT COUNT(*) AS realm_wc_player_games
        FROM (
            SELECT g.player_a_id AS player_id
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE t.name REGEXP %s AND {cutoff}
            UNION ALL
            SELECT g.player_b_id AS player_id
            FROM amiga_games g
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE t.name REGEXP %s AND {cutoff}
        ) sides
    """
    goals_sql = f"""
        SELECT COALESCE(SUM(g.goals_a + g.goals_b), 0) AS realm_wc_goals_for
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE t.name REGEXP %s AND {cutoff}
    """
    with conn.cursor() as cur:
        cur.execute(wc_sql, (_WC_NAME_RE, *cp))
        wc_count = int(cur.fetchone()["realm_wc_tournament_count"] or 0)
        cur.execute(games_sql, (_WC_NAME_RE, *cp, _WC_NAME_RE, *cp))
        player_games = int(cur.fetchone()["realm_wc_player_games"] or 0)
        cur.execute(goals_sql, (_WC_NAME_RE, *cp))
        goals_for = int(cur.fetchone()["realm_wc_goals_for"] or 0)
    return {
        "realm_wc_tournament_count": wc_count,
        "realm_wc_player_games": player_games,
        "realm_wc_goals_for": goals_for,
    }


def _load_wc_games_through_cutoff(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
) -> list[dict[str, Any]]:
    cutoff = _tournament_cutoff_sql("t")
    cp = _cutoff_params(event_date, event_chrono, tournament_id)
    sql = f"""
        SELECT g.player_a_id AS idA,
               g.player_b_id AS idB,
               g.goals_a AS GoalsA,
               g.goals_b AS GoalsB,
               gr.rating_a,
               gr.rating_b,
               gr.actual_score
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id
        WHERE t.name REGEXP %s
          AND {cutoff}
        ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, g.id ASC
    """
    with conn.cursor() as cur:
        cur.execute(sql, (_WC_NAME_RE, *cp))
        return list(cur.fetchall())


def _prepare_games_for_country_trackers(
    games: list[dict[str, Any]],
    ratings_by_game_id: dict[int, dict[str, Any]],
) -> list[dict[str, Any]]:
    prepared: list[dict[str, Any]] = []
    for game in games:
        game_id = int(game.get("id") or game.get("game_id") or 0)
        rating = ratings_by_game_id.get(game_id, {})
        prepared.append(
            {
                "idA": int(game.get("idA") if "idA" in game else game["player_a_id"]),
                "idB": int(game.get("idB") if "idB" in game else game["player_b_id"]),
                "GoalsA": int(game.get("GoalsA") if "GoalsA" in game else game["goals_a"]),
                "GoalsB": int(game.get("GoalsB") if "GoalsB" in game else game["goals_b"]),
                "rating_a": float(rating.get("rating_a") or 0.0),
                "rating_b": float(rating.get("rating_b") or 0.0),
            }
        )
    return prepared


def _assemble_country_rows(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    country_trackers: dict[str, CountryWorldCupSliceTracker],
) -> dict[str, dict[str, Any]]:
    rollups = _load_player_rollups(conn)
    participation = _load_participation_counts(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
    )
    realm = _load_realm_scalars(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
    )

    country_tokens = set(rollups) | set(participation)
    out: dict[str, dict[str, Any]] = {}

    for token in sorted(country_tokens):
        row = empty_country_world_cup_slice()
        rollup = rollups.get(token, {})
        part = participation.get(token, {})

        row["players"] = int(rollup.get("players") or 0)
        if row["players"] < 1:
            continue

        for col in _SUM_PLAYER_COLS:
            row[col] = int(rollup.get(col) or 0)
        for col in _MAX_PLAYER_COLS:
            row[col] = int(rollup.get(col) or 0)

        row["tournaments_with_nation"] = int(part.get("tournaments_with_nation") or 0)
        row["wc_participations"] = int(part.get("wc_participations") or 0)
        row["realm_wc_tournament_count"] = realm["realm_wc_tournament_count"]
        row["realm_wc_player_games"] = realm["realm_wc_player_games"]
        row["realm_wc_goals_for"] = realm["realm_wc_goals_for"]

        tracker = country_trackers.get(token)
        if tracker is not None:
            tracker.flush_into(row)
        else:
            finalize_country_slice_row(row)

        out[token] = row

    return out


def compute_country_slices_through_tournament(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    player_countries: dict[int, str | None],
) -> dict[str, dict[str, Any]]:
    """Full oracle rebuild — scans all WC games through cutoff (verify only)."""
    games = _load_wc_games_through_cutoff(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
    )
    trackers: dict[str, CountryWorldCupSliceTracker] = {}
    apply_wc_games_to_country_trackers(games, player_countries, trackers)
    return _assemble_country_rows(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
        country_trackers=trackers,
    )


def rebuild_country_slices_at_world_cup_finalize(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    player_countries: dict[int, str | None],
    tournament_games: list[dict[str, Any]],
    ratings_by_game_id: dict[int, dict[str, Any]],
    country_trackers: dict[str, CountryWorldCupSliceTracker],
    commit: bool = True,
) -> int:
    from scripts.amiga.country_slice_persist import persist_country_slices_at_tournament

    prepared = _prepare_games_for_country_trackers(tournament_games, ratings_by_game_id)
    apply_wc_games_to_country_trackers(prepared, player_countries, country_trackers)
    rows = _assemble_country_rows(
        conn,
        tournament_id=tournament_id,
        event_date=event_date,
        event_chrono=event_chrono,
        country_trackers=country_trackers,
    )
    count = persist_country_slices_at_tournament(
        conn,
        tournament_id,
        event_date,
        event_chrono,
        rows,
        commit=commit,
    )
    log.info(
        "rebuild_country_slices_at_world_cup_finalize: tournament_id=%s countries=%s",
        tournament_id,
        count,
    )
    return count
