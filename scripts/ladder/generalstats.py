"""Rebuild generalstatstable row id=1 when the table exists (optional on local dump)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from .player_state import PlayerState

log = logging.getLogger(__name__)

MIN_GAMES_FOR_RECORD = 30


def _table_exists(conn: pymysql.connections.Connection) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = 'generalstatstable'"
        )
        return int(cur.fetchone()["n"]) > 0


def _leader(
    conn: pymysql.connections.Connection,
    order_col: str,
    *,
    desc: bool = True,
) -> dict[str, Any] | None:
    direction = "DESC" if desc else "ASC"
    sql = (
        f"SELECT ID, Name, {order_col}, LastGame FROM playertable "
        f"WHERE NumberGames >= %s AND {order_col} IS NOT NULL "
        f"ORDER BY {order_col} {direction}, ID ASC LIMIT 1"
    )
    with conn.cursor() as cur:
        cur.execute(sql, (MIN_GAMES_FOR_RECORD,))
        return cur.fetchone()


def rebuild_generalstats_if_present(
    conn: pymysql.connections.Connection,
    players: dict[int, PlayerState],
) -> None:
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

        cur.execute(
            "SELECT COUNT(*) AS n FROM playertable WHERE NumberGames >= 1"
        )
        num_players = int(cur.fetchone()["n"])

        cur.execute(
            "SELECT AVG(DifferentOpponents) AS a FROM playertable WHERE DifferentOpponents >= 1"
        )
        diff_opp_avg = cur.fetchone()["a"]

    games_avg = (2 * games / num_players) if num_players else None
    decided_ratio = (decided / games) if games else None
    draws_ratio = (draws / games) if games else None
    goals_per_game = (goals / games) if games else None
    dd_ratio = (dd / (2 * games)) if games else None
    cs_ratio = (cs / (2 * games)) if games else None

    patch: dict[str, Any] = {
        "NumberOfPlayers": num_players,
        "DifferentOpponentsAverage": diff_opp_avg,
        "GamesPlayed": games,
        "GamesPlayedAverage": games_avg,
        "NumberOfDecidedGames": decided,
        "NumberOfDraws": draws,
        "DecidedGamesRatio": decided_ratio,
        "DrawsRatio": draws_ratio,
        "GoalsScored": goals,
        "GoalsPerGameAverage": goals_per_game,
        "DoubleDigits": dd,
        "CleanSheets": cs,
        "DoubleDigitsRatio": dd_ratio,
        "CleanSheetsRatio": cs_ratio,
    }

    leader_map = {
        "MostGamesPlayed": ("NumberGames", True),
        "MostWins": ("NumberWins", True),
        "BiggestWinRatio": ("WinRatio", True),
        "MostGoalsScored": ("GoalsFor", True),
        "BiggestGoalsForAverage": ("AverageGoalsFor", True),
        "SmallestGoalsAgainstAverage": ("AverageGoalsAgainst", False),
        "BiggestGoalRatio": ("GoalRatio", True),
        "MostDoubleDigits": ("DoubleDigits", True),
        "MostCleanSheets": ("CleanSheets", True),
        "BiggestDoubleDigitsRatio": ("DoubleDigitsRatio", True),
        "BiggestCleanSheetsRatio": ("CleanSheetsRatio", True),
        "MostDifferentOpponents": ("DifferentOpponents", True),
        "MostDifferentVictims": ("DifferentVictims", True),
        "MostDoubleDigitsVictims": ("DoubleDigitsVictims", True),
        "MostCleanSheetsVictims": ("CleanSheetsVictims", True),
        "BiggestAverageOpponentRating": ("AverageOpponentRating", True),
        "BiggestRatingAscent": ("BiggestRatingAscent", True),
        "BiggestPeakRating": ("PeakRating", True),
        "LongestWinningStreak": ("LongestWinningStreak", True),
        "LongestDrawingStreak": ("LongestDrawingStreak", True),
        "LongestNonLossStreak": ("LongestNonLossStreak", True),
    }

    for prefix, (col, desc) in leader_map.items():
        row = _leader(conn, col, desc=desc)
        if not row:
            continue
        patch[prefix] = row[col]
        patch[f"{prefix}ID"] = row["ID"]
        patch[f"{prefix}Name"] = row["Name"]
        if row.get("LastGame") is not None:
            patch[f"{prefix}Date"] = str(row["LastGame"])

    sets = ", ".join(f"`{k}` = %s" for k in patch)
    values = list(patch.values())
    with conn.cursor() as cur:
        cur.execute(f"UPDATE generalstatstable SET {sets} WHERE id = 1", values)
    log.info("generalstatstable id=1 updated (%s headline + leader fields)", len(patch))
