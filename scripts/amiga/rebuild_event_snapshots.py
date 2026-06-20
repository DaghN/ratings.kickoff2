"""Rebuild amiga_player_event_snapshots + amiga_player_current from history."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import (
    _apply_tournament_stats_batch,
    _load_player_names,
)
from scripts.amiga.replay import _connect, tournament_ids_for_replay
from scripts.amiga.snapshot_persist import persist_tournament_event_snapshots
from scripts.amiga.tournament_honours import is_world_cup_tournament
from scripts.ladder.finalize_counts import finalize_network_counts_from_rows
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


def _empty_honours_totals() -> dict[str, Any]:
    return {
        "tournaments_played": 0,
        "tournaments_won": 0,
        "event_gold": 0,
        "event_silver": 0,
        "event_bronze": 0,
        "event_podiums": 0,
        "wc_played": 0,
        "wc_gold": 0,
        "wc_silver": 0,
        "wc_bronze": 0,
        "wc_podiums": 0,
        "last_event_date": None,
        "last_tournament_id": None,
    }


def _increment_honours_totals(totals: dict[str, Any], participation: dict[str, Any]) -> None:
    """Apply one participation row to running career honours (as-of that event)."""
    totals["tournaments_played"] = int(totals["tournaments_played"]) + 1

    pos = participation.get("event_finish_position")
    if pos is not None:
        pos = int(pos)

    if int(participation.get("is_winner") or 0) == 1 or pos == 1:
        totals["tournaments_won"] = int(totals["tournaments_won"]) + 1

    if pos == 1:
        totals["event_gold"] = int(totals["event_gold"]) + 1
    elif pos == 2:
        totals["event_silver"] = int(totals["event_silver"]) + 1
    elif pos == 3:
        totals["event_bronze"] = int(totals["event_bronze"]) + 1

    if pos is not None and pos <= 3:
        totals["event_podiums"] = int(totals["event_podiums"]) + 1

    if is_world_cup_tournament(str(participation.get("tournament_name") or "")):
        totals["wc_played"] = int(totals["wc_played"]) + 1
        if pos == 1:
            totals["wc_gold"] = int(totals["wc_gold"]) + 1
        elif pos == 2:
            totals["wc_silver"] = int(totals["wc_silver"]) + 1
        elif pos == 3:
            totals["wc_bronze"] = int(totals["wc_bronze"]) + 1
        if pos is not None and pos <= 3:
            totals["wc_podiums"] = int(totals["wc_podiums"]) + 1

    totals["last_event_date"] = participation.get("event_date")
    totals["last_tournament_id"] = int(participation["tournament_id"])


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


def _apply_tournament_ratings_from_events(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, rating_after
            FROM amiga_rating_events
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            pid = int(row["player_id"])
            if pid in players:
                players[pid].rating = float(row["rating_after"])


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
    Walk finalized tournaments in catalog order; write sparse snapshot timeline + current.

    Requires: ``amiga_game_ratings``, ``amiga_rating_events``, ``amiga_player_tournament_participation``.
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
    honours_by_player: dict[int, dict[str, Any]] = {}
    prior_career_best: dict[int, dict[str, Any]] = {}
    event_games: dict[tuple[int, int], int] = {}
    tournaments_done: list[int] = []
    snapshots_written = 0

    for idx, tournament_id in enumerate(tournament_ids, start=1):
        _apply_tournament_stats_batch(conn, tournament_id, players, names)
        _apply_tournament_ratings_from_events(conn, tournament_id, players)
        tournaments_done.append(tournament_id)

        game_rows = _load_rated_games_for_tournaments(conn, tournaments_done)
        finalize_network_counts_from_rows(players, game_rows)

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT *
                FROM amiga_player_tournament_participation
                WHERE tournament_id = %s
                """,
                (tournament_id,),
            )
            participation_rows = cur.fetchall()

        participant_ids: set[int] = set()
        honours_for_event: dict[int, dict[str, Any]] = {}
        for row in participation_rows:
            pid = int(row["player_id"])
            tid = int(row["tournament_id"])
            participant_ids.add(pid)
            event_games[(pid, tid)] = int(row.get("games") or 0)

            if pid not in honours_by_player:
                honours_by_player[pid] = _empty_honours_totals()
            _increment_honours_totals(honours_by_player[pid], row)
            honours_for_event[pid] = dict(honours_by_player[pid])

        if not participant_ids:
            continue

        snapshots_written += persist_tournament_event_snapshots(
            conn,
            tournament_id,
            players,
            participant_ids,
            honours_by_player=honours_for_event,
            prior_career_best=prior_career_best,
            event_games_by_player_tournament=event_games,
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
