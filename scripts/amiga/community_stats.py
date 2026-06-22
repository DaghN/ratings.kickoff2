"""Community headline aggregates (realm-wide cumulative scalars)."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

import pymysql

from scripts.amiga.community_stats_columns import (
    COMMUNITY_HEADLINE_COLUMNS,
    COMMUNITY_SNAPSHOT_KEY_COLUMNS,
)
from scripts.amiga.generalstats_columns import GENERALSTATS_AGGREGATE_COLUMNS
from scripts.amiga.realm_cutoff import (
    RealmCutoff,
    cutoff_params,
    game_cutoff_sql,
    load_realm_cutoff,
)


def aggregate_patch(
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
    return {
        "NumberOfPlayers": num_players,
        "DifferentOpponentsAverage": diff_opp_avg,
        "GamesPlayed": games,
        "GamesPlayedAverage": round(2 * games / num_players, 3) if num_players else None,
        "NumberOfDecidedGames": decided,
        "NumberOfDraws": draws,
        "DecidedGamesRatio": round(decided / games, 8) if games else None,
        "DrawsRatio": round(draws / games, 8) if games else None,
        "GoalsScored": goals,
        "GoalsPerGameAverage": round(goals / games, 7) if games else None,
        "DoubleDigits": dd,
        "CleanSheets": cs,
        "DoubleDigitsRatio": round(dd / games, 8) if games else None,
        "CleanSheetsRatio": round(cs / games, 8) if games else None,
    }


def _player_count_stats_present(conn: pymysql.connections.Connection) -> tuple[int, Any]:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_current WHERE NumberGames >= 1")
        num_players = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT AVG(DifferentOpponents) AS a
            FROM amiga_player_current
            WHERE DifferentOpponents >= 1
            """
        )
        diff_opp_avg = cur.fetchone()["a"]
    return num_players, diff_opp_avg


def _player_count_stats_cutoff(
    conn: pymysql.connections.Connection,
    cutoff: RealmCutoff,
) -> tuple[int, Any]:
    from scripts.amiga.server_records import _latest_player_snapshots_sql

    params = cutoff_params(cutoff)
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM ({_latest_player_snapshots_sql()}) lp
            WHERE lp.NumberGames >= 1
            """,
            params,
        )
        num_players = int(cur.fetchone()["n"])
        cur.execute(
            f"""
            SELECT AVG(lp.DifferentOpponents) AS a
            FROM ({_latest_player_snapshots_sql()}) lp
            WHERE lp.DifferentOpponents >= 1
            """,
            params,
        )
        diff_opp_avg = cur.fetchone()["a"]
    return num_players, diff_opp_avg


def _game_totals_at_cutoff(
    conn: pymysql.connections.Connection,
    cutoff: RealmCutoff | None,
) -> dict[str, int]:
    if cutoff is None:
        sql = """
            SELECT COUNT(*) AS games,
                   SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
                   COALESCE(SUM(r.sum_of_goals), 0) AS goals,
                   COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
                   COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            """
        params: tuple[Any, ...] = ()
    else:
        params = cutoff_params(cutoff)
        cutoff_where = game_cutoff_sql("t")
        sql = f"""
            SELECT COUNT(*) AS games,
                   SUM(CASE WHEN r.actual_score = 0.5 THEN 1 ELSE 0 END) AS draws,
                   COALESCE(SUM(r.sum_of_goals), 0) AS goals,
                   COALESCE(SUM(r.dd_player_a + r.dd_player_b), 0) AS dd,
                   COALESCE(SUM(r.cs_player_a + r.cs_player_b), 0) AS cs
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            INNER JOIN tournaments t ON t.id = g.tournament_id
            WHERE {cutoff_where}
            """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        agg = cur.fetchone()
    games = int(agg["games"] or 0)
    draws = int(agg["draws"] or 0)
    return {
        "games": games,
        "draws": draws,
        "goals": int(agg["goals"] or 0),
        "dd": int(agg["dd"] or 0),
        "cs": int(agg["cs"] or 0),
        "decided": games - draws,
    }


def compute_community_headline_aggregates(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int | None = None,
) -> dict[str, Any]:
    """Return the 14 headline cumulative columns."""
    if as_of_tournament_id is None:
        totals = _game_totals_at_cutoff(conn, None)
        num_players, diff_opp_avg = _player_count_stats_present(conn)
    else:
        cutoff = load_realm_cutoff(conn, as_of_tournament_id)
        totals = _game_totals_at_cutoff(conn, cutoff)
        num_players, diff_opp_avg = _player_count_stats_cutoff(conn, cutoff)
    return aggregate_patch(
        games=totals["games"],
        draws=totals["draws"],
        decided=totals["decided"],
        goals=totals["goals"],
        dd=totals["dd"],
        cs=totals["cs"],
        num_players=num_players,
        diff_opp_avg=diff_opp_avg,
    )


def build_community_headline_row(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int,
    finalized_at: datetime | None = None,
) -> dict[str, Any]:
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    headline = compute_community_headline_aggregates(
        conn, as_of_tournament_id=as_of_tournament_id
    )
    if finalized_at is None:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT rating_finalized_at FROM tournaments WHERE id = %s LIMIT 1",
                (as_of_tournament_id,),
            )
            tour = cur.fetchone()
        finalized_at = tour.get("rating_finalized_at") if tour else None
    if finalized_at is None:
        finalized_at = datetime.now(timezone.utc).replace(tzinfo=None)

    row: dict[str, Any] = {
        "tournament_id": cutoff.tournament_id,
        "event_date": cutoff.event_date,
        "event_chrono": cutoff.chrono,
        "tournament_name": cutoff.tournament_name,
        "finalized_at": finalized_at,
    }
    row.update(headline)
    missing = [col for col in COMMUNITY_HEADLINE_COLUMNS if col not in row]
    if missing:
        raise RuntimeError(f"community headline missing columns: {missing}")
    return row


def headline_only_patch(patch: dict[str, Any]) -> dict[str, Any]:
    return {col: patch.get(col) for col in GENERALSTATS_AGGREGATE_COLUMNS}


def assert_headline_columns(row: dict[str, Any]) -> None:
    missing = [c for c in COMMUNITY_SNAPSHOT_KEY_COLUMNS + COMMUNITY_HEADLINE_COLUMNS if c not in row]
    if missing:
        raise RuntimeError(f"community snapshot row missing: {missing[:5]}")
