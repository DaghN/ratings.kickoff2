"""Reopen and rebuild-forward refinalize for post-close corrections."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import (
    TournamentNotFoundError,
    _apply_tournament_stats_batch,
    _load_player_names,
    _load_rated_game_rows,
    _load_tournament,
    _stats_upsert_sql,
    commit_heavy_player_derived,
    finalize_tournament,
    recompute_rating_peaks_from_events,
)
from scripts.amiga.player_stats_load import load_player_states
from scripts.amiga.player_tournament_participation import (
    rebuild_all_participation,
    rebuild_all_participation_totals,
)
from scripts.amiga.replay import _connect, _stats_row, tournament_ids_for_replay
from scripts.amiga.tournament_catalog_stats import rebuild_all_catalog_stats
from scripts.amiga.tournament_standings import clear_standings, rebuild_all_standings
from scripts.ladder.finalize_counts import finalize_network_counts_from_rows
from scripts.ladder.player_state import PlayerState

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
    """Clear one tournament's finalize markers and derived rows (not global stats)."""
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
            SELECT player_id FROM amiga_player_tournament_participation
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        participant_ids = [int(row["player_id"]) for row in cur.fetchall()]

        cur.execute(
            "DELETE FROM amiga_player_event_snapshots WHERE tournament_id = %s",
            (tournament_id,),
        )
        cur.execute(
            "DELETE FROM amiga_rating_events WHERE tournament_id = %s",
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
            f"DELETE FROM amiga_rating_events WHERE tournament_id IN ({placeholders})",
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
            INNER JOIN amiga_player_tournament_participation p
              ON p.player_id = c.player_id
            WHERE p.tournament_id IN ({placeholders})
            """,
            tournament_ids,
        )
        cur.execute(
            f"""
            UPDATE tournaments
            SET rating_finalized = 0, rating_finalized_at = NULL
            WHERE id IN ({placeholders})
            """,
            tournament_ids,
        )


def rebuild_stats_through_finalized(
    conn: pymysql.connections.Connection,
    tournament_ids: list[int],
) -> None:
    """
    Rebuild amiga_player_stats from existing ratings/events for finalized tournaments.

    Used after clearing stats when refinalizing from tournament T forward.
    """
    if not tournament_ids:
        return

    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM amiga_players")
        names = {int(row["id"]): str(row["name"]) for row in cur.fetchall()}

    players: dict[int, PlayerState] = {}
    for tid in tournament_ids:
        _apply_tournament_stats_batch(conn, tid, players, names)

    finalize_network_counts_from_rows(players, _load_rated_game_rows(conn))
    recompute_rating_peaks_from_events(conn, players, set(players.keys()))

    stats_sql = _stats_upsert_sql()
    stat_rows = [_stats_row(pid, st) for pid, st in players.items() if st.games > 0]
    with conn.cursor() as cur:
        if stat_rows:
            cur.executemany(stats_sql, stat_rows)
    conn.commit()
    log.info(
        "rebuild_stats_through_finalized: tournaments=%s stat_rows=%s",
        len(tournament_ids),
        len(stat_rows),
    )


def refinalize_from(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> dict[str, Any]:
    """
    Rebuild-forward correction path (contract § 6.3).

    Clears derived state from tournament T onward, rebuilds global stats through T-1,
    then finalizes T and every later tournament in catalog order.
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
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_player_stats")
    conn.commit()

    rebuild_stats_through_finalized(conn, before_ids)
    names = _load_player_names(conn)
    players: dict[int, PlayerState] = {}
    for tid in before_ids:
        _apply_tournament_stats_batch(conn, tid, players, names)

    games_total = 0
    events_total = 0
    for tid in from_ids:
        result = finalize_tournament(
            conn,
            tid,
            dry_run=False,
            defer_heavy_derived=True,
            persist_player_stats=False,
            players=players,
            names=names,
        )
        if result.get("skipped"):
            continue
        games_total += int(result.get("games", 0))
        events_total += int(result.get("rating_events", 0))

    if from_ids:
        commit_heavy_player_derived(conn, players=players)

    clear_standings(conn, dry_run=False)
    rebuild_all_standings(conn, dry_run=False)
    rebuild_all_catalog_stats(conn, dry_run=False)

    log.info("refinalize_from: rebuilding participation + totals for affected tournaments")
    rebuild_all_participation(conn, dry_run=False)
    rebuild_all_participation_totals(conn, dry_run=False)

    from scripts.amiga.rebuild_event_snapshots import rebuild_all_event_snapshots

    log.info("refinalize_from: rebuilding event snapshots + current")
    rebuild_all_event_snapshots(conn, dry_run=False)

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
