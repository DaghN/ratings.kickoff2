"""Rebuild generalstatstable row id=1 after replay.

Hall-of-fame holders: chronological ServerRecordState (PG-004, non-ratio rows).
Ratio/average player leaders: read from playertable on Records page (not stored here).
"""

from __future__ import annotations

import logging
from typing import Any, TYPE_CHECKING

import pymysql

from .schema import _table_exists, ensure_generalstatstable
from .server_records import ServerRecordState, holder_patch

if TYPE_CHECKING:
    pass

log = logging.getLogger(__name__)


def rebuild_generalstats_if_present(
    conn: pymysql.connections.Connection,
    server_state: ServerRecordState | None = None,
) -> None:
    ensure_generalstatstable(conn)
    if not _table_exists(conn):
        log.info("generalstatstable not present — skip server stats rebuild")
        return

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM generalstatstable WHERE id = 1")
        if int(cur.fetchone()["n"]) == 0:
            log.warning("generalstatstable has no id=1 row — skip")
            return

    patch: dict = compute_server_aggregates(conn)

    if server_state is None:
        log.warning(
            "rebuild_generalstats_if_present: no ServerRecordState — "
            "non-ratio holders unchanged; run full replay for PG-004 holders"
        )
    else:
        patch.update(holder_patch(server_state))

    write_generalstats_row(conn, patch)


def compute_server_aggregates(conn: pymysql.connections.Connection) -> dict[str, Any]:
    with conn.cursor() as cur:
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


def write_generalstats_row(
    conn: pymysql.connections.Connection,
    patch: dict[str, Any],
) -> None:
    sets = ", ".join(f"`{k}` = %s" for k in patch)
    with conn.cursor() as cur:
        cur.execute(f"UPDATE generalstatstable SET {sets} WHERE id = 1", list(patch.values()))
    log.info("generalstatstable id=1 updated (%s fields)", len(patch))
