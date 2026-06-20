"""Rebuild amiga_generalstats row id=1 (server hall-of-fame, no streak records)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

log = logging.getLogger(__name__)

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


def compute_server_aggregates(conn: pymysql.connections.Connection) -> dict[str, Any]:
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
        games = int(agg["games"] or 0)
        draws = int(agg["draws"] or 0)
        decided = games - draws
        goals = int(agg["goals"] or 0)
        dd = int(agg["dd"] or 0)
        cs = int(agg["cs"] or 0)

        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_player_current WHERE NumberGames >= 1"
        )
        num_players = int(cur.fetchone()["n"])

        cur.execute(
            """
            SELECT AVG(DifferentOpponents) AS a
            FROM amiga_player_current
            WHERE DifferentOpponents >= 1
            """
        )
        diff_opp_avg = cur.fetchone()["a"]

    return {
        "NumberOfPlayers": num_players,
        "DifferentOpponentsAverage": diff_opp_avg,
        "GamesPlayed": games,
        "GamesPlayedAverage": (2 * games / num_players) if num_players else None,
        "NumberOfDecidedGames": decided,
        "NumberOfDraws": draws,
        "DecidedGamesRatio": (decided / games) if games else None,
        "DrawsRatio": (draws / games) if games else None,
        "GoalsScored": goals,
        "GoalsPerGameAverage": (goals / games) if games else None,
        "DoubleDigits": dd,
        "CleanSheets": cs,
        "DoubleDigitsRatio": (dd / games) if games else None,
        "CleanSheetsRatio": (cs / games) if games else None,
    }


def _career_holder_patch(
    conn: pymysql.connections.Connection,
    *,
    value_col: str,
    prefix: str,
) -> dict[str, Any]:
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
    with conn.cursor() as cur:
        cur.execute(sql)
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
    return "COALESCE(DATE_FORMAT(t.event_date, '%Y-%m-%d'), DATE_FORMAT(g.game_date, '%Y-%m-%d'))"


def _most_goals_one_game_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
    sql = f"""
        SELECT game_id, player_id, player_name, goals, record_date
        FROM (
            SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name,
                   g.goals_a AS goals, {date_expr} AS record_date
            FROM amiga_games g
            INNER JOIN amiga_players pa ON pa.id = g.player_a_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name, g.goals_b, {date_expr}
            FROM amiga_games g
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
        ) sides
        ORDER BY goals DESC, game_id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
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


def _biggest_win_margin_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
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
        WHERE r.actual_score IN (0.0, 1.0)
          AND r.goal_difference IS NOT NULL
        ORDER BY r.goal_difference DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
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


def _biggest_draw_sum_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
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
        WHERE r.actual_score = 0.5
        ORDER BY draw_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
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


def _biggest_sum_goals_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    date_expr = _game_event_date_sql()
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
        ORDER BY goal_sum DESC, g.id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
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


def _biggest_peak_in_game_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    """Highest post-game rating seen in any single game (both players)."""
    date_expr = _game_event_date_sql()
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
            WHERE r.rating_a IS NOT NULL AND r.adjustment_a IS NOT NULL
            UNION ALL
            SELECT g.id, g.player_b_id, pb.name,
                   COALESCE(r.new_rating_b, r.rating_b + r.adjustment_b),
                   {date_expr}
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN amiga_players pb ON pb.id = g.player_b_id
            LEFT JOIN tournaments t ON t.id = g.tournament_id
            WHERE r.rating_b IS NOT NULL AND r.adjustment_b IS NOT NULL
        ) peaks
        ORDER BY peak_rating DESC, game_id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()
    if not row:
        return {}
    return {
        "BiggestPeakRating": row["peak_rating"],
        "BiggestPeakRatingID": int(row["player_id"]),
        "BiggestPeakRatingName": row["player_name"],
        "BiggestPeakRatingDate": _fmt_date(row["record_date"]),
    }


def compute_record_holder_patch(conn: pymysql.connections.Connection) -> dict[str, Any]:
    patch: dict[str, Any] = {}
    for prefix, value_col, patch_prefix in _CAREER_HOLDERS:
        if patch_prefix == "BiggestPeakRating":
            peak_patch = _biggest_peak_in_game_patch(conn)
            if peak_patch:
                patch.update(peak_patch)
            continue
        patch.update(_career_holder_patch(conn, value_col=value_col, prefix=patch_prefix))
    patch.update(_most_goals_one_game_patch(conn))
    patch.update(_biggest_win_margin_patch(conn))
    patch.update(_biggest_draw_sum_patch(conn))
    patch.update(_biggest_sum_goals_patch(conn))
    return patch


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
    nullables = [
        "NumberOfPlayers",
        "DifferentOpponentsAverage",
        "GamesPlayed",
        "GamesPlayedAverage",
        "NumberOfDecidedGames",
        "NumberOfDraws",
        "DecidedGamesRatio",
        "DrawsRatio",
        "GoalsScored",
        "GoalsPerGameAverage",
        "DoubleDigits",
        "CleanSheets",
        "DoubleDigitsRatio",
        "CleanSheetsRatio",
        "MostGamesPlayed",
        "MostWins",
        "MostGoalsScored",
        "MostGoalsScoredInOneGame",
        "BiggestWinDifference",
        "BiggestDrawSum",
        "BiggestSumOfGoals",
        "MostDoubleDigits",
        "MostCleanSheets",
        "MostDifferentOpponents",
        "MostDifferentVictims",
        "MostDoubleDigitsVictims",
        "MostCleanSheetsVictims",
        "BiggestRatingAscent",
        "BiggestPeakRating",
        "MostGamesPlayedID",
        "MostWinsID",
        "MostGoalsScoredID",
        "MostGoalsScoredInOneGameID",
        "BiggestWinDifferenceID",
        "BiggestDrawSumIDA",
        "BiggestDrawSumIDB",
        "BiggestSumOfGoalsIDA",
        "BiggestSumOfGoalsIDB",
        "MostDoubleDigitsID",
        "MostCleanSheetsID",
        "MostDifferentOpponentsID",
        "MostDifferentVictimsID",
        "MostDoubleDigitsVictimsID",
        "MostCleanSheetsVictimsID",
        "BiggestRatingAscentID",
        "BiggestPeakRatingID",
        "MostGamesPlayedName",
        "MostWinsName",
        "MostGoalsScoredName",
        "MostGoalsScoredInOneGameName",
        "BiggestWinDifferenceName",
        "BiggestDrawSumNameA",
        "BiggestDrawSumNameB",
        "BiggestSumOfGoalsNameA",
        "BiggestSumOfGoalsNameB",
        "MostDoubleDigitsName",
        "MostCleanSheetsName",
        "MostDifferentOpponentsName",
        "MostDifferentVictimsName",
        "MostDoubleDigitsVictimsName",
        "MostCleanSheetsVictimsName",
        "BiggestRatingAscentName",
        "BiggestPeakRatingName",
        "MostGamesPlayedDate",
        "MostWinsDate",
        "MostGoalsScoredDate",
        "MostGoalsScoredInOneGameDate",
        "BiggestWinDifferenceDate",
        "BiggestDrawSumDate",
        "BiggestSumOfGoalsDate",
        "MostDoubleDigitsDate",
        "MostCleanSheetsDate",
        "MostDifferentOpponentsDate",
        "MostDifferentVictimsDate",
        "MostDoubleDigitsVictimsDate",
        "MostCleanSheetsVictimsDate",
        "BiggestRatingAscentDate",
        "BiggestPeakRatingDate",
        "MostGoalsScoredInOneGameGameID",
        "BiggestWinDifferenceGameID",
        "BiggestDrawSumGameID",
        "BiggestSumOfGoalsGameID",
    ]
    sets = ", ".join(f"`{col}` = NULL" for col in nullables)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE amiga_generalstats SET {sets} WHERE id = 1")
    conn.commit()


def rebuild_generalstats(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_generalstats WHERE id = 1")
        if int(cur.fetchone()["n"]) == 0:
            raise RuntimeError("amiga_generalstats has no id=1 row — apply 013_generalstats.sql")

    if dry_run:
        return {"dry_run": True}

    clear_generalstats(conn, dry_run=False)
    patch = compute_server_aggregates(conn)
    patch.update(compute_record_holder_patch(conn))
    write_generalstats_row(conn, patch)
    conn.commit()
    log.info(
        "rebuild_generalstats: GamesPlayed=%s MostGamesPlayed=%s",
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
