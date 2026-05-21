"""Rebuild generalstatstable row id=1 from replayed ratedresults + playertable (batch, not per-game)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from .player_state import PlayerState
from .schema import _table_exists, ensure_generalstatstable

log = logging.getLogger(__name__)

MIN_GAMES_FOR_RECORD = 30


def _fmt_date(value: Any) -> str | None:
    if value is None:
        return None
    return str(value)


def _apply_leader(patch: dict[str, Any], prefix: str, row: dict[str, Any] | None, value_col: str) -> None:
    if not row:
        return
    patch[prefix] = row[value_col]
    patch[f"{prefix}ID"] = row["ID"]
    patch[f"{prefix}Name"] = row["Name"]
    patch[f"{prefix}Date"] = _fmt_date(row.get("LastGame"))


def _leader(
    conn: pymysql.connections.Connection,
    order_col: str,
    *,
    desc: bool = True,
    min_games: int = MIN_GAMES_FOR_RECORD,
    extra_where: str = "",
) -> dict[str, Any] | None:
    direction = "DESC" if desc else "ASC"
    where = f"NumberGames >= %s AND {order_col} IS NOT NULL"
    if extra_where:
        where += f" AND ({extra_where})"
    sql = (
        f"SELECT ID, Name, {order_col}, LastGame FROM playertable "
        f"WHERE {where} "
        f"ORDER BY {order_col} {direction}, ID ASC LIMIT 1"
    )
    with conn.cursor() as cur:
        cur.execute(sql, (min_games,))
        return cur.fetchone()


def _leader_active(
    conn: pymysql.connections.Connection,
    order_col: str,
    *,
    desc: bool = True,
    extra_where: str = "",
) -> dict[str, Any] | None:
    return _leader(conn, order_col, desc=desc, min_games=1, extra_where=extra_where)


def _game_most_goals_one_side(conn: pymysql.connections.Connection) -> dict[str, Any] | None:
    sql = """
        SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB
        FROM ratedresults
        ORDER BY GREATEST(GoalsA, GoalsB) DESC, id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()
    if not row:
        return None
    if int(row["GoalsA"]) >= int(row["GoalsB"]):
        holder_id, holder_name, value = row["idA"], row["NameA"], int(row["GoalsA"])
    else:
        holder_id, holder_name, value = row["idB"], row["NameB"], int(row["GoalsB"])
    return {
        "value": value,
        "holder_id": holder_id,
        "holder_name": holder_name,
        "date": row["Date"],
        "game_id": row["id"],
    }


def _game_biggest_win(conn: pymysql.connections.Connection) -> dict[str, Any] | None:
    sql = """
        SELECT id, Date, idA, idB, NameA, NameB, GoalDifference, ActualScore
        FROM ratedresults
        WHERE ActualScore IN (0, 1)
        ORDER BY GoalDifference DESC, id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()
    if not row:
        return None
    if float(row["ActualScore"]) == 1.0:
        holder_id, holder_name = row["idA"], row["NameA"]
    else:
        holder_id, holder_name = row["idB"], row["NameB"]
    return {
        "value": int(row["GoalDifference"]),
        "holder_id": holder_id,
        "holder_name": holder_name,
        "date": row["Date"],
        "game_id": row["id"],
    }


def _game_row(
    conn: pymysql.connections.Connection,
    *,
    where: str,
    order_col: str = "SumOfGoals",
) -> dict[str, Any] | None:
    sql = f"""
        SELECT id, Date, idA, idB, NameA, NameB, SumOfGoals
        FROM ratedresults
        WHERE {where}
        ORDER BY {order_col} DESC, id ASC
        LIMIT 1
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return cur.fetchone()


def _apply_single_game_holder(
    patch: dict[str, Any],
    prefix: str,
    rec: dict[str, Any],
) -> None:
    patch[prefix] = rec["value"]
    patch[f"{prefix}ID"] = rec["holder_id"]
    patch[f"{prefix}Name"] = rec["holder_name"]
    patch[f"{prefix}Date"] = _fmt_date(rec["date"])
    patch[f"{prefix}GameID"] = rec["game_id"]


def _apply_pair_game_row(patch: dict[str, Any], prefix: str, row: dict[str, Any]) -> None:
    patch[prefix] = int(row["SumOfGoals"])
    patch[f"{prefix}IDA"] = row["idA"]
    patch[f"{prefix}IDB"] = row["idB"]
    patch[f"{prefix}NameA"] = row["NameA"]
    patch[f"{prefix}NameB"] = row["NameB"]
    patch[f"{prefix}Date"] = _fmt_date(row["Date"])
    patch[f"{prefix}GameID"] = row["id"]


def rebuild_generalstats_if_present(
    conn: pymysql.connections.Connection,
    players: dict[int, PlayerState],
) -> None:
    del players
    ensure_generalstatstable(conn)
    if not _table_exists(conn):
        log.info("generalstatstable not present — skip server stats rebuild")
        return

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM generalstatstable WHERE id = 1")
        if int(cur.fetchone()["n"]) == 0:
            log.warning("generalstatstable has no id=1 row — skip")
            return

        cur.execute(
            "SELECT COUNT(*) AS games, "
            "SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws, "
            "SUM(SumOfGoals) AS goals, "
            "SUM(DDPlayerA + DDPlayerB) AS dd, "
            "SUM(CSPlayerA + CSPlayerB) AS cs "
            "FROM ratedresults"
        )
        agg = cur.fetchone()
        games = int(agg["games"] or 0)
        draws = int(agg["draws"] or 0)
        decided = games - draws
        goals = int(agg["goals"] or 0)
        dd = int(agg["dd"] or 0)
        cs = int(agg["cs"] or 0)

        cur.execute("SELECT COUNT(*) AS n FROM playertable WHERE NumberGames >= 1")
        num_players = int(cur.fetchone()["n"])

        cur.execute(
            "SELECT AVG(DifferentOpponents) AS a FROM playertable WHERE DifferentOpponents >= 1"
        )
        diff_opp_avg = cur.fetchone()["a"]

    patch: dict[str, Any] = {
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

    for prefix, col, desc, extra in (
        ("MostGamesPlayed", "NumberGames", True, ""),
        ("MostWins", "NumberWins", True, ""),
        ("MostGoalsScored", "GoalsFor", True, ""),
        ("MostDoubleDigits", "DoubleDigits", True, "DoubleDigits > 0"),
        ("MostCleanSheets", "CleanSheets", True, "CleanSheets > 0"),
        ("MostDifferentOpponents", "DifferentOpponents", True, ""),
        ("MostDifferentVictims", "DifferentVictims", True, ""),
        ("MostDoubleDigitsVictims", "DoubleDigitsVictims", True, ""),
        ("MostCleanSheetsVictims", "CleanSheetsVictims", True, ""),
    ):
        _apply_leader(patch, prefix, _leader_active(conn, col, desc=desc, extra_where=extra), col)

    for prefix, col, desc, extra in (
        ("BiggestWinRatio", "WinRatio", True, ""),
        ("BiggestGoalsForAverage", "AverageGoalsFor", True, ""),
        ("SmallestGoalsAgainstAverage", "AverageGoalsAgainst", False, ""),
        ("BiggestGoalRatio", "GoalRatio", True, "GoalRatio > -1"),
        ("BiggestDoubleDigitsRatio", "DoubleDigitsRatio", True, ""),
        ("BiggestCleanSheetsRatio", "CleanSheetsRatio", True, ""),
        ("BiggestAverageOpponentRating", "AverageOpponentRating", True, ""),
        ("BiggestRatingAscent", "BiggestRatingAscent", True, ""),
        ("BiggestPeakRating", "PeakRating", True, ""),
        ("LongestWinningStreak", "LongestWinningStreak", True, ""),
        ("LongestDrawingStreak", "LongestDrawingStreak", True, ""),
        ("LongestNonLossStreak", "LongestNonLossStreak", True, ""),
    ):
        _apply_leader(patch, prefix, _leader(conn, col, desc=desc, extra_where=extra), col)

    mg = _game_most_goals_one_side(conn)
    if mg:
        _apply_single_game_holder(patch, "MostGoalsScoredInOneGame", mg)

    bw = _game_biggest_win(conn)
    if bw:
        _apply_single_game_holder(patch, "BiggestWinDifference", bw)

    draw_row = _game_row(conn, where="ActualScore = 0.5")
    if draw_row:
        _apply_pair_game_row(patch, "BiggestDrawSum", draw_row)

    sum_row = _game_row(conn, where="1=1")
    if sum_row:
        _apply_pair_game_row(patch, "BiggestSumOfGoals", sum_row)

    sets = ", ".join(f"`{k}` = %s" for k in patch)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE generalstatstable SET {sets} WHERE id = 1", list(patch.values()))
    log.info("generalstatstable id=1 updated (%s fields)", len(patch))
