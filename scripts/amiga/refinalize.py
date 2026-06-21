"""Reopen and rebuild-forward refinalize for post-close corrections."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import (
    TournamentNotFoundError,
    _apply_tournament_matchups_batch,
    _apply_tournament_stats_batch,
    _load_player_names,
    _load_tournament,
    finalize_tournament,
)
from scripts.amiga.matchup_cumulative import MatchupCumulative
from scripts.amiga.player_stats_load import load_player_states
from scripts.amiga.realm_incremental import empty_prior_payload, load_prior_realm_payload
from scripts.amiga.replay import _connect, tournament_ids_for_replay
from scripts.amiga.tournament_standings import clear_standings, rebuild_all_standings

log = logging.getLogger(__name__)


def tournaments_from_split(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> tuple[list[int], list[int], list[int]]:
    """Return (all_ids, before_ids, from_ids) in catalog chronology."""
    all_ids, _ = tournament_ids_for_replay(conn, limit_games=None)
    if tournament_id not in all_ids:
        raise TournamentNotFoundError(
            f"tournament_id={tournament_id} not found or has no games in replay order"
        )
    pos = all_ids.index(tournament_id)
    return all_ids, all_ids[:pos], all_ids[pos:]


def reopen_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> dict[str, Any]:
    """Clear one tournament's finalize markers and derived rows."""
    tour = _load_tournament(conn, tournament_id)
    if int(tour["rating_finalized"]) != 1:
        log.info("reopen_tournament: id=%s not rating_finalized; no-op", tournament_id)
        return {"tournament_id": tournament_id, "reopened": False, "skipped": True}

    log.info("reopen_tournament: id=%s name=%r", tournament_id, tour["name"])
    if dry_run:
        return {"tournament_id": tournament_id, "reopened": True, "dry_run": True}

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT DISTINCT player_id FROM (
                SELECT player_a_id AS player_id FROM amiga_games WHERE tournament_id = %s
                UNION
                SELECT player_b_id AS player_id FROM amiga_games WHERE tournament_id = %s
            ) roster
            """,
            (tournament_id, tournament_id),
        )
        participant_ids = [int(row["player_id"]) for row in cur.fetchall()]

        cur.execute(
            "DELETE FROM amiga_player_event_snapshots WHERE tournament_id = %s",
            (tournament_id,),
        )
        cur.execute(
            "DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id = %s",
            (tournament_id,),
        )
        cur.execute(
            "DELETE FROM amiga_realm_snapshots WHERE tournament_id = %s",
            (tournament_id,),
        )
        cur.execute(
            """
            DELETE r FROM amiga_game_ratings r
            INNER JOIN amiga_games g ON g.id = r.game_id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        if participant_ids:
            placeholders = ", ".join(["%s"] * len(participant_ids))
            cur.execute(
                f"DELETE FROM amiga_player_current WHERE player_id IN ({placeholders})",
                participant_ids,
            )
        cur.execute(
            """
            UPDATE tournaments
            SET rating_finalized = 0, rating_finalized_at = NULL
            WHERE id = %s
            """,
            (tournament_id,),
        )
    conn.commit()
    return {"tournament_id": tournament_id, "name": tour["name"], "reopened": True}


def _reopen_tournaments_batch(
    conn: pymysql.connections.Connection,
    tournament_ids: list[int],
) -> None:
    if not tournament_ids:
        return
    placeholders = ", ".join(["%s"] * len(tournament_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"DELETE FROM amiga_player_event_snapshots WHERE tournament_id IN ({placeholders})",
            tournament_ids,
        )
        cur.execute(
            f"DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id IN ({placeholders})",
            tournament_ids,
        )
        cur.execute(
            f"DELETE FROM amiga_realm_snapshots WHERE tournament_id IN ({placeholders})",
            tournament_ids,
        )
        cur.execute(
            f"""
            DELETE r FROM amiga_game_ratings r
            INNER JOIN amiga_games g ON g.id = r.game_id
            WHERE g.tournament_id IN ({placeholders})
            """,
            tournament_ids,
        )
        cur.execute(
            f"""
            DELETE c FROM amiga_player_current c
            WHERE c.player_id IN (
                SELECT player_id FROM (
                    SELECT player_a_id AS player_id FROM amiga_games
                    WHERE tournament_id IN ({placeholders})
                    UNION
                    SELECT player_b_id AS player_id FROM amiga_games
                    WHERE tournament_id IN ({placeholders})
                ) roster
            )
            """,
            (*tournament_ids, *tournament_ids),
        )
        cur.execute(
            f"""
            UPDATE tournaments
            SET rating_finalized = 0, rating_finalized_at = NULL
            WHERE id IN ({placeholders})
            """,
            tournament_ids,
        )


def refinalize_from(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> dict[str, Any]:
    """
    Rebuild-forward correction path (contract § 6.3).

    Clears derived state from tournament T onward, then finalizes T and later events.
    Prefer full ``prove`` for routine repair; this path is for targeted reopen.
    """
    all_ids, before_ids, from_ids = tournaments_from_split(conn, tournament_id)
    tour = _load_tournament(conn, tournament_id)
    log.info(
        "refinalize_from: id=%s name=%r before=%s from=%s",
        tournament_id,
        tour["name"],
        len(before_ids),
        len(from_ids),
    )
    if dry_run:
        return {
            "tournament_id": tournament_id,
            "before_tournaments": len(before_ids),
            "from_tournaments": len(from_ids),
            "dry_run": True,
        }

    _reopen_tournaments_batch(conn, from_ids)
    conn.commit()

    names = _load_player_names(conn)
    players = load_player_states(conn)
    matchups = MatchupCumulative()
    honours_by_player: dict[int, dict[str, Any]] = {}
    prior_career_best: dict[int, dict[str, Any]] = {}
    event_games: dict[tuple[int, int], int] = {}

    for tid in before_ids:
        _apply_tournament_matchups_batch(conn, tid, matchups)
        _apply_tournament_stats_batch(conn, tid, players, names)

    games_total = 0
    events_total = 0
    prior_realm_payload = (
        load_prior_realm_payload(conn, from_ids[0]) if from_ids else empty_prior_payload()
    )
    for tid in from_ids:
        result = finalize_tournament(
            conn,
            tid,
            dry_run=False,
            players=players,
            names=names,
            honours_by_player=honours_by_player,
            prior_career_best=prior_career_best,
            event_games=event_games,
            matchups=matchups,
            prior_realm_payload=prior_realm_payload,
        )
        if result.get("skipped"):
            continue
        prior_realm_payload = result.get("realm_payload") or prior_realm_payload
        games_total += int(result.get("games", 0))
        events_total += int(result.get("rating_events", 0))

    clear_standings(conn, dry_run=False)
    rebuild_all_standings(conn, dry_run=False)

    log.info(
        "refinalize_from complete: id=%s games=%s events=%s",
        tournament_id,
        games_total,
        events_total,
    )
    return {
        "tournament_id": tournament_id,
        "name": tour["name"],
        "before_tournaments": len(before_ids),
        "from_tournaments": len(from_ids),
        "games_finalized": games_total,
        "rating_events": events_total,
    }


def run_reopen_tournament(*, tournament_id: int, dry_run: bool = False) -> dict[str, Any]:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        return reopen_tournament(conn, tournament_id, dry_run=dry_run)
    finally:
        conn.close()


def run_refinalize_from(*, tournament_id: int, dry_run: bool = False) -> dict[str, Any]:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        return refinalize_from(conn, tournament_id, dry_run=dry_run)
    finally:
        conn.close()
