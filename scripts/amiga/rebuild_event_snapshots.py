"""Rebuild amiga_player_event_snapshots + amiga_player_current from history."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import (
    _apply_tournament_matchups_batch,
    _apply_tournament_stats_batch,
    _load_player_names,
    _persist_event_snapshots,
)
from scripts.amiga.matchup_cumulative import MatchupCumulative, apply_peak_from_event_rating
from scripts.ladder.player_state import PlayerState

log = logging.getLogger(__name__)

_RATED_GAMES_THROUGH_SQL = """
    SELECT g.id AS id, g.player_a_id AS idA, g.player_b_id AS idB,
           r.actual_score AS ActualScore,
           r.dd_player_a AS DDPlayerA, r.dd_player_b AS DDPlayerB,
           r.cs_player_a AS CSPlayerA, r.cs_player_b AS CSPlayerB
    FROM amiga_games g
    INNER JOIN amiga_game_ratings r ON r.game_id = g.id
    WHERE g.tournament_id IN ({placeholders})
    ORDER BY g.game_date ASC, g.id ASC
"""


def _load_rated_games_for_tournaments(
    conn: pymysql.connections.Connection,
    tournament_ids: list[int],
) -> list[dict[str, Any]]:
    if not tournament_ids:
        return []
    placeholders = ", ".join(["%s"] * len(tournament_ids))
    sql = _RATED_GAMES_THROUGH_SQL.format(placeholders=placeholders)
    with conn.cursor() as cur:
        cur.execute(sql, tournament_ids)
        return cur.fetchall()


def _load_event_commits_from_snapshots(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[int, dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, rating_before, rating_delta, rating_after,
                   performance_rating, games_in_event, finalized_at
            FROM amiga_player_event_snapshots
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        rows = cur.fetchall()
    return {int(row["player_id"]): row for row in rows}


def clear_event_snapshots(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
        snapshots = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_current")
        current = int(cur.fetchone()["n"])
    log.info(
        "clear_event_snapshots: snapshots=%s current=%s",
        snapshots,
        current,
    )
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_player_event_snapshots")
        cur.execute("DELETE FROM amiga_player_current")
    conn.commit()


def rebuild_all_event_snapshots(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> dict[str, int]:
    """
    Walk finalized tournaments in catalog order; rewrite snapshot timeline + current.

    Requires: ``amiga_game_ratings`` and snapshot rating blocks per finalized event.
    """
    tournament_ids, _games = tournament_ids_for_replay(conn, limit_games=None)
    log.info(
        "rebuild_all_event_snapshots: %s tournaments with games",
        len(tournament_ids),
    )
    if dry_run:
        return {"tournaments": len(tournament_ids), "snapshots": 0}

    clear_event_snapshots(conn, dry_run=False)

    names = _load_player_names(conn)
    players: dict[int, PlayerState] = {}
    matchups = MatchupCumulative()
    honours_by_player: dict[int, dict[str, Any]] = {}
    prior_career_best: dict[int, dict[str, Any]] = {}
    event_games: dict[tuple[int, int], int] = {}
    snapshots_written = 0

    for idx, tournament_id in enumerate(tournament_ids, start=1):
        _apply_tournament_matchups_batch(conn, tournament_id, matchups)
        _apply_tournament_stats_batch(conn, tournament_id, players, names)

        event_commits = _load_event_commits_from_snapshots(conn, tournament_id)
        if not event_commits:
            from scripts.amiga.player_tournament_participation import (
                build_participation_rows_for_tournament,
            )

            part_rows = build_participation_rows_for_tournament(conn, tournament_id)
            participant_ids = {int(r["player_id"]) for r in part_rows}
        else:
            participant_ids = set(event_commits.keys())

        if not participant_ids:
            continue

        for pid in participant_ids:
            matchups.apply_network_to_player_state(pid, players[pid])
            if pid in event_commits:
                apply_peak_from_event_rating(
                    players[pid], float(event_commits[pid]["rating_after"])
                )

        snapshots_written += _persist_event_snapshots(
            conn,
            tournament_id,
            players,
            participant_ids,
            event_commits,
            honours_by_player=honours_by_player,
            prior_career_best=prior_career_best,
            event_games=event_games,
        )

        if idx % 50 == 0 or idx == len(tournament_ids):
            log.info(
                "rebuild_all_event_snapshots progress: %s / %s tournaments, %s snapshots",
                idx,
                len(tournament_ids),
                snapshots_written,
            )

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
        snapshot_count = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_current")
        current_count = int(cur.fetchone()["n"])

    log.info(
        "rebuild_all_event_snapshots complete: snapshots=%s current=%s",
        snapshot_count,
        current_count,
    )
    return {
        "tournaments": len(tournament_ids),
        "snapshots": snapshot_count,
        "current": current_count,
    }


def run_rebuild_event_snapshots(*, dry_run: bool = False) -> dict[str, int]:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        return rebuild_all_event_snapshots(conn, dry_run=dry_run)
    finally:
        conn.close()
