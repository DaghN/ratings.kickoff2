"""Rebuild amiga_generalstats row id=1 (server hall-of-fame, no streak records)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.realm_cutoff import (
    RealmCutoff,
    cutoff_params,
    game_cutoff_sql,
    latest_finalized_tournament_id,
    load_realm_cutoff,
)

log = logging.getLogger(__name__)

ESTABLISHED_MIN_GAMES = 20

_CAREER_HOLDERS: list[tuple[str, str, str]] = [
    ("MostGamesPlayed", "NumberGames", "MostGamesPlayed"),
    ("MostWins", "NumberWins", "MostWins"),
    ("MostGoalsScored", "GoalsFor", "MostGoalsScored"),
    ("MostDoubleDigits", "DoubleDigits", "MostDoubleDigits"),
    ("MostCleanSheets", "CleanSheets", "MostCleanSheets"),
    ("MostDifferentOpponents", "DifferentOpponents", "MostDifferentOpponents"),
    ("MostDifferentVictims", "DifferentVictims", "MostDifferentVictims"),
    ("MostDoubleDigitsVictims", "DoubleDigitsVictims", "MostDoubleDigitsVictims"),
    ("MostCleanSheetsVictims", "CleanSheetsVictims", "MostCleanSheetsVictims"),
    ("BiggestRatingAscent", "BiggestRatingAscent", "BiggestRatingAscent"),
    ("BiggestPeakRating", "PeakRating", "BiggestPeakRating"),
    ("MostGamesInOneYear", "peak_year_games", "MostGamesInOneYear"),
    ("MostTournamentsInOneYear", "peak_year_tournaments", "MostTournamentsInOneYear"),
    ("MostTournamentsPlayed", "tournaments_played", "MostTournamentsPlayed"),
    ("MostTournamentWins", "event_gold", "MostTournamentWins"),
    ("MostWcPlayed", "wc_slice_tournaments_played", "MostWcPlayed"),
    ("MostCountriesPlayedIn", "countries_played_in", "MostCountriesPlayedIn"),
    ("MostOpponentCountriesFaced", "opponent_countries_faced", "MostOpponentCountriesFaced"),
    ("MostOpponentCountriesBeaten", "opponent_countries_beaten", "MostOpponentCountriesBeaten"),
]

_RATIO_LEADERS: list[tuple[str, str, str, str]] = [
    ("BiggestWinRatio", "WinRatio", "DESC", ""),
    ("BiggestGoalsForAverage", "AverageGoalsFor", "DESC", ""),
    ("SmallestGoalsAgainstAverage", "AverageGoalsAgainst", "ASC", ""),
    ("BiggestGoalRatio", "GoalRatio", "DESC", "lp.GoalRatio > -1"),
    ("BiggestDoubleDigitsRatio", "DoubleDigitsRatio", "DESC", ""),
    ("BiggestCleanSheetsRatio", "CleanSheetsRatio", "DESC", ""),
]


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _fmt_date(value: Any) -> str | None:
    if value is None:
        return None
    return str(value)


def _latest_player_snapshots_sql(*, alias: str = "lp") -> str:
    cutoff_clause = game_cutoff_sql("t_cut")
    return f"""
        SELECT s.*, p.name AS player_name
        FROM (
            SELECT s_inner.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s_inner.player_id
                       ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC,
                                s_inner.tournament_id DESC
                   ) AS rn
            FROM amiga_player_event_snapshots s_inner
            INNER JOIN tournaments t_cut ON t_cut.id = s_inner.tournament_id
            WHERE {cutoff_clause}
        ) s
        INNER JOIN amiga_players p ON p.id = s.player_id
        WHERE s.rn = 1
    """


def _load_cutoff_player_rows(
    conn: pymysql.connections.Connection,
    cutoff: RealmCutoff,
) -> list[dict[str, Any]]:
    """One fetch of latest player rows at cutoff (tier-1 oracle path)."""
    params = cutoff_params(cutoff)
    sql = f"""
        WITH latest_lp AS (
            SELECT s_inner.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s_inner.player_id
                       ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC,
                                s_inner.tournament_id DESC
                   ) AS rn
            FROM amiga_player_event_snapshots s_inner
            INNER JOIN tournaments t_cut ON t_cut.id = s_inner.tournament_id
            WHERE {game_cutoff_sql("t_cut")}
        )
        SELECT lp.*, p.name AS player_name,
               COALESCE(wcs.tournaments_played, 0) AS wc_slice_tournaments_played,
               wcs.tournaments_played_last_rise_event_date
                   AS wc_slice_tournaments_played_last_rise_event_date,
               COALESCE(
                   DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'),
                   DATE_FORMAT(g.game_date, '%%Y-%%m-%%d')
               ) AS record_date
        FROM latest_lp lp
        INNER JOIN amiga_players p ON p.id = lp.player_id
        LEFT JOIN (
            SELECT x.player_id, x.tournaments_played, x.tournaments_played_last_rise_event_date
            FROM (
                SELECT s.*,
                       ROW_NUMBER() OVER (
                           PARTITION BY s.player_id
                           ORDER BY s.event_date DESC, s.event_chrono DESC,
                                    s.as_of_tournament_id DESC
                       ) AS rn
                FROM amiga_player_slice_at_event s
                INNER JOIN tournaments t_cut ON t_cut.id = %s
                WHERE s.slice_key = 'world_cup'
                  AND (s.event_date, s.event_chrono, s.as_of_tournament_id)
                      <= (t_cut.event_date, t_cut.chrono, t_cut.id)
            ) x
            WHERE x.rn = 1
        ) wcs ON wcs.player_id = lp.player_id
        LEFT JOIN amiga_games g ON g.id = lp.LastGameGameID
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        WHERE lp.rn = 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params + (cutoff.tournament_id,))
        return list(cur.fetchall())


def _compute_game_aggregates_at_cutoff(
    conn: pymysql.connections.Connection,
    cutoff: RealmCutoff | None,
) -> dict[str, int]:
    if cutoff is None:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT COUNT(*) AS games,
                       SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
                       COALESCE(SUM(r.sum_of_goals), 0) AS goals,
                       COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
                       COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
                FROM amiga_games g
                INNER JOIN amiga_game_ratings r ON r.game_id = g.id
                """
            )
            agg = cur.fetchone()
    else:
        params = cutoff_params(cutoff)
        cutoff_where = game_cutoff_sql("t")
        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT COUNT(*) AS games,
                       SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
                       COALESCE(SUM(r.sum_of_goals), 0) AS goals,
                       COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
                       COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
                FROM amiga_games g
                INNER JOIN amiga_game_ratings r ON r.game_id = g.id
                INNER JOIN tournaments t ON t.id = g.tournament_id
                WHERE {cutoff_where}
                """,
                params,
            )
            agg = cur.fetchone()
    return {
        "games": int(agg["games"] or 0),
        "draws": int(agg["draws"] or 0),
        "goals": int(agg["goals"] or 0),
        "dd": int(agg["dd"] or 0),
        "cs": int(agg["cs"] or 0),
    }


def compute_server_aggregates(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    from scripts.amiga.community_stats import compute_community_headline_aggregates

    return compute_community_headline_aggregates(
        conn, as_of_tournament_id=as_of_tournament_id
    )


def _compute_server_aggregates_present(
    conn: pymysql.connections.Connection,
) -> dict[str, Any]:
    return compute_server_aggregates(conn)


def _aggregate_patch(
    *,
    games: int,
    draws: int,
    decided: int,
    goals: int,
    dd: int,
    cs: int,
    num_players: int,
    diff_opp_avg: Any,
) -> dict[str, Any]:
    from scripts.amiga.community_stats import aggregate_patch

    return aggregate_patch(
        games=games,
        draws=draws,
        decided=decided,
        goals=goals,
        dd=dd,
        cs=cs,
        num_players=num_players,
        diff_opp_avg=diff_opp_avg,
    )


def _career_holder_patch(
    conn: pymysql.connections.Connection,
    *,
    value_col: str,
    prefix: str,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    if cutoff is None:
        sql = f"""
            SELECT s.player_id, p.name,
                   s.{value_col} AS record_value,
                   COALESCE(
                       DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'),
                       DATE_FORMAT(g.game_date, '%%Y-%%m-%%d')
                   ) AS record_date
            FROM amiga_player_current s
            INNER JOIN amiga_players p ON p.id = s.player_id
            LEFT JOIN amiga_games g ON g.id = s.LastGameGameID
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE s.{value_col} IS NOT NULL
              AND s.{value_col} > 0
            ORDER BY s.{value_col} DESC, s.player_id ASC
            LIMIT 1
        """
        params: tuple[Any, ...] = ()
    else:
        params = cutoff_params(cutoff)
        sql = f"""
            SELECT lp.player_id, lp.player_name AS name,
                   lp.{value_col} AS record_value,
                   COALESCE(
                       DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'),
                       DATE_FORMAT(g.game_date, '%%Y-%%m-%%d')
                   ) AS record_date
            FROM ({_latest_player_snapshots_sql()}) lp
            LEFT JOIN amiga_games g ON g.id = lp.LastGameGameID
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE lp.{value_col} IS NOT NULL
              AND lp.{value_col} > 0
            ORDER BY lp.{value_col} DESC, lp.player_id ASC
            LIMIT 1
        """

    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        prefix: row["record_value"],
        f"{prefix}ID": int(row["player_id"]),
        f"{prefix}Name": row["name"],
        f"{prefix}Date": _fmt_date(row["record_date"]),
    }


def _game_event_date_sql() -> str:
    return (
        "COALESCE(DATE_FORMAT(t.event_date, '%%Y-%%m-%%d'), "
        "DATE_FORMAT(g.game_date, '%%Y-%%m-%%d'))"
    )


def _game_cutoff_and_clause(cutoff: RealmCutoff | None) -> tuple[str, tuple[Any, ...]]:
    if cutoff is None:
        return "", ()
    return f" AND {game_cutoff_sql('tg')}", cutoff_params(cutoff)


def _most_goals_one_game_patch(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    extra, params = _game_cutoff_and_clause(cutoff)
    sql = f"""
        SELECT game_id, player_id, player_name, goals, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   g.goals_a AS goals, {date_expr} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE 1=1{extra}
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name, g.goals_b, {date_expr}
            FROM amiga_games g
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE 1=1{extra}
        ) sides
        ORDER BY goals DESC, game_id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, (*params, *params) if params else ())
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "MostGoalsScoredInOneGame": int(row["goals"]),
        "MostGoalsScoredInOneGameID": int(row["player_id"]),
        "MostGoalsScoredInOneGameName": row["player_name"],
        "MostGoalsScoredInOneGameDate": _fmt_date(row["record_date"]),
        "MostGoalsScoredInOneGameGameID": int(row["game_id"]),
    }


def _biggest_win_margin_patch(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    extra, params = _game_cutoff_and_clause(cutoff)
    sql = f"""
        SELECT g.id AS game_id,
               r.goal_difference AS margin,
               CASE
                   WHEN r.actual_score = 1.0 THEN g.player_a_id
                   WHEN r.actual_score = 0.0 THEN g.player_b_id
               END AS player_id,
               CASE
                   WHEN r.actual_score = 1.0 THEN pa.name
                   WHEN r.actual_score = 0.0 THEN pb.name
               END AS player_name,
               {date_expr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score IN (0.0, 1.0)
          AND r.goal_difference IS NOT NULL{extra}
        ORDER BY r.goal_difference DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row or row["player_id"] is None:
        return {}
    return {
        "BiggestWinDifference": int(row["margin"]),
        "BiggestWinDifferenceID": int(row["player_id"]),
        "BiggestWinDifferenceName": row["player_name"],
        "BiggestWinDifferenceDate": _fmt_date(row["record_date"]),
        "BiggestWinDifferenceGameID": int(row["game_id"]),
    }


def _biggest_draw_sum_patch(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    extra, params = _game_cutoff_and_clause(cutoff)
    sql = f"""
        SELECT g.id AS game_id,
               (g.goals_a + g.goals_b) AS draw_sum,
               g.player_a_id, g.player_b_id,
               pa.name AS name_a, pb.name AS name_b,
               {date_expr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE r.actual_score = 0.5{extra}
        ORDER BY draw_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestDrawSum": int(row["draw_sum"]),
        "BiggestDrawSumIDA": int(row["player_a_id"]),
        "BiggestDrawSumIDB": int(row["player_b_id"]),
        "BiggestDrawSumNameA": row["name_a"],
        "BiggestDrawSumNameB": row["name_b"],
        "BiggestDrawSumDate": _fmt_date(row["record_date"]),
        "BiggestDrawSumGameID": int(row["game_id"]),
    }


def _biggest_sum_goals_patch(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    extra, params = _game_cutoff_and_clause(cutoff)
    sql = f"""
        SELECT g.id AS game_id,
               COALESCE(r.sum_of_goals, g.goals_a + g.goals_b) AS goal_sum,
               g.player_a_id, g.player_b_id,
               pa.name AS name_a, pb.name AS name_b,
               {date_expr} AS record_date
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        LEFT JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN tournaments tg ON tg.id = g.tournament_id
        WHERE 1=1{extra}
        ORDER BY goal_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestSumOfGoals": int(row["goal_sum"]),
        "BiggestSumOfGoalsIDA": int(row["player_a_id"]),
        "BiggestSumOfGoalsIDB": int(row["player_b_id"]),
        "BiggestSumOfGoalsNameA": row["name_a"],
        "BiggestSumOfGoalsNameB": row["name_b"],
        "BiggestSumOfGoalsDate": _fmt_date(row["record_date"]),
        "BiggestSumOfGoalsGameID": int(row["game_id"]),
    }


def _biggest_peak_in_game_patch(
    conn: pymysql.connections.Connection,
    *,
    cutoff: RealmCutoff | None = None,
) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    extra, params = _game_cutoff_and_clause(cutoff)
    sql = f"""
        SELECT game_id, player_id, player_name, peak_rating, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   COALESCE(r.new_rating_a, r.rating_a + r.adjustment_a) AS peak_rating,
                   {date_expr} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE r.rating_a IS NOT NULL AND r.adjustment_a IS NOT NULL{extra}
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name,
                   COALESCE(r.new_rating_b, r.rating_b + r.adjustment_b),
                   {date_expr}
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            INNER JOIN tournaments tg ON tg.id = g.tournament_id
            WHERE r.rating_b IS NOT NULL AND r.adjustment_b IS NOT NULL{extra}
        ) peaks
        ORDER BY peak_rating DESC, game_id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, (*params, *params) if params else ())
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestPeakRating": row["peak_rating"],
        "BiggestPeakRatingID": int(row["player_id"]),
        "BiggestPeakRatingName": row["player_name"],
        "BiggestPeakRatingDate": _fmt_date(row["record_date"]),
    }


def compute_record_holder_patch(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    cutoff = (
        load_realm_cutoff(conn, as_of_tournament_id)
        if as_of_tournament_id is not None
        else None
    )
    patch: dict[str, Any] = {}
    for _prefix, value_col, patch_prefix in _CAREER_HOLDERS:
        if patch_prefix == "BiggestPeakRating":
            peak_patch = _biggest_peak_in_game_patch(conn, cutoff=cutoff)
            if peak_patch:
                patch.update(peak_patch)
            continue
        patch.update(
            _career_holder_patch(
                conn,
                value_col=value_col,
                prefix=patch_prefix,
                cutoff=cutoff,
            )
        )
    patch.update(_most_goals_one_game_patch(conn, cutoff=cutoff))
    patch.update(_biggest_win_margin_patch(conn, cutoff=cutoff))
    patch.update(_biggest_draw_sum_patch(conn, cutoff=cutoff))
    patch.update(_biggest_sum_goals_patch(conn, cutoff=cutoff))
    return patch


def compute_ratio_leader_patch(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    cutoff = (
        load_realm_cutoff(conn, as_of_tournament_id)
        if as_of_tournament_id is not None
        else None
    )
    patch: dict[str, Any] = {}
    for prefix, column, direction, extra_where in _RATIO_LEADERS:
        patch.update(
            _ratio_leader_patch(
                conn,
                prefix=prefix,
                column=column,
                direction=direction,
                extra_where=extra_where,
                cutoff=cutoff,
            )
        )
    return patch


def _ratio_leader_patch(
    conn: pymysql.connections.Connection,
    *,
    prefix: str,
    column: str,
    direction: str,
    extra_where: str,
    cutoff: RealmCutoff | None,
) -> dict[str, Any]:
    dir_sql = "DESC" if direction.upper() == "DESC" else "ASC"
    if cutoff is None:
        sql = f"""
            SELECT s.player_id, p.name, s.`{column}` AS metric_value
            FROM amiga_player_current s
            INNER JOIN amiga_players p ON p.id = s.player_id
            WHERE s.NumberGames >= %s
              AND s.`{column}` IS NOT NULL
              {f'AND ({extra_where.replace("lp.", "s.")})' if extra_where else ''}
            ORDER BY s.`{column}` {dir_sql}, s.player_id ASC
            LIMIT 1
        """
        params: tuple[Any, ...] = (ESTABLISHED_MIN_GAMES,)
    else:
        extra = f"AND ({extra_where})" if extra_where else ""
        sql = f"""
            SELECT lp.player_id, lp.player_name AS name, lp.`{column}` AS metric_value
            FROM ({_latest_player_snapshots_sql()}) lp
            WHERE lp.NumberGames >= %s
              AND lp.`{column}` IS NOT NULL
              {extra}
            ORDER BY lp.`{column}` {dir_sql}, lp.player_id ASC
            LIMIT 1
        """
        params = (*cutoff_params(cutoff), ESTABLISHED_MIN_GAMES)

    with conn.cursor() as cur:
        cur.execute(sql, params)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        prefix: row["metric_value"],
        f"{prefix}ID": int(row["player_id"]),
        f"{prefix}Name": row["name"],
    }


def build_generalstats_payload(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    """Full-history rescan oracle (verify / generalstats-rebuild)."""
    from scripts.amiga.realm_incremental import (
        _career_holders_from_player_rows,
        _player_count_stats_sql_cutoff,
        _player_count_stats_sql_present,
        _ratio_leaders_from_player_rows,
        fetch_player_current_rows,
    )

    cutoff = (
        load_realm_cutoff(conn, as_of_tournament_id)
        if as_of_tournament_id is not None
        else None
    )
    if cutoff is None:
        player_rows = fetch_player_current_rows(conn)
    else:
        player_rows = _load_cutoff_player_rows(conn, cutoff)
    patch: dict[str, Any] = {}
    patch.update(_career_holders_from_player_rows(player_rows))
    patch.update(_ratio_leaders_from_player_rows(player_rows))
    patch.update(_most_goals_one_game_patch(conn, cutoff=cutoff))
    patch.update(_biggest_win_margin_patch(conn, cutoff=cutoff))
    patch.update(_biggest_draw_sum_patch(conn, cutoff=cutoff))
    patch.update(_biggest_sum_goals_patch(conn, cutoff=cutoff))
    patch.update(_biggest_peak_in_game_patch(conn, cutoff=cutoff))
    return {col: patch.get(col) for col in GENERALSTATS_PAYLOAD_COLUMNS}


def write_generalstats_row(
    conn: pymysql.connections.Connection,
    patch: dict[str, Any],
) -> None:
    if not patch:
        return
    sets = ", ".join(f"`{k}` = %s" for k in patch)
    with conn.cursor() as cur:
        cur.execute(
            f"UPDATE amiga_generalstats SET {sets} WHERE id = 1",
            list(patch.values()),
        )
    log.info("amiga_generalstats id=1 updated (%s fields)", len(patch))


def clear_generalstats(conn: pymysql.connections.Connection, *, dry_run: bool = False) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_generalstats WHERE id = 1")
        n = int(cur.fetchone()["n"])
    log.info("clear_generalstats: row id=1 present=%s", n > 0)
    if dry_run or n == 0:
        return
    nullables = list(GENERALSTATS_PAYLOAD_COLUMNS)
    sets = ", ".join(f"`{col}` = NULL" for col in nullables)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE amiga_generalstats SET {sets} WHERE id = 1")
    conn.commit()


def rebuild_generalstats(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_generalstats WHERE id = 1")
        if int(cur.fetchone()["n"]) == 0:
            raise RuntimeError("amiga_generalstats has no id=1 row — apply 013_generalstats.sql")

    if dry_run:
        return {"dry_run": True}

    if as_of_tournament_id is None:
        as_of_tournament_id = latest_finalized_tournament_id(conn)

    clear_generalstats(conn, dry_run=False)
    if as_of_tournament_id is None:
        patch: dict[str, Any] = {}
    else:
        patch = build_generalstats_payload(conn, as_of_tournament_id=as_of_tournament_id)
    write_generalstats_row(conn, patch)
    conn.commit()
    log.info(
        "rebuild_generalstats: tournament_id=%s GamesPlayed=%s MostGamesPlayed=%s",
        as_of_tournament_id,
        patch.get("GamesPlayed"),
        patch.get("MostGamesPlayed"),
    )
    return patch


def run_generalstats_rebuild(*, dry_run: bool = False) -> dict[str, Any]:
    conn = _connect()
    try:
        return rebuild_generalstats(conn, dry_run=dry_run)
    finally:
        conn.close()
