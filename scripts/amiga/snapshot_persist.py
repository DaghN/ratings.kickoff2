"""Persist event snapshot + current rows after tournament finalize."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.honours_totals import (
    empty_honours_totals,
    honours_from_snapshot_row,
)
from scripts.amiga.player_geo_year import PlayerGeoYearTracker, load_player_countries
from scripts.amiga.player_stats_load import load_prior_snapshot_rows_before_tournament
from scripts.amiga.snapshot_row import (
    build_snapshot_from_finalize_parts,
    current_upsert_sql,
    snapshot_insert_sql,
)
from scripts.ladder.player_state import PlayerState

log = logging.getLogger(__name__)


def _prior_career_best_context(
    conn: pymysql.connections.Connection,
    player_ids: list[int],
    tournament_id: int,
) -> dict[int, dict[str, Any]]:
    """Latest per-player snapshot strictly before ``tournament_id``."""
    if not player_ids:
        return {}

    placeholders = ", ".join(["%s"] * len(player_ids))
    sql = f"""
        SELECT
            ranked.player_id,
            ranked.career_best_performance_rating,
            ranked.career_best_performance_tournament_id,
            pg.games AS prior_games
        FROM (
            SELECT
                s.player_id,
                s.career_best_performance_rating,
                s.career_best_performance_tournament_id,
                ROW_NUMBER() OVER (
                    PARTITION BY s.player_id
                    ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
                ) AS rn
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments tc ON tc.id = %s
            WHERE s.player_id IN ({placeholders})
              AND (
                  s.event_date < tc.event_date
                  OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono)
                  OR (
                      s.event_date = tc.event_date
                      AND s.event_chrono = tc.chrono
                      AND s.tournament_id < tc.id
                  )
              )
        ) ranked
        LEFT JOIN amiga_player_event_snapshots pg
            ON pg.player_id = ranked.player_id
           AND pg.tournament_id = ranked.career_best_performance_tournament_id
        WHERE ranked.rn = 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, [tournament_id, *player_ids])
        rows = cur.fetchall()

    out: dict[int, dict[str, Any]] = {}
    for row in rows:
        pid = int(row["player_id"])
        prior_rating = row.get("career_best_performance_rating")
        prior_tid = row.get("career_best_performance_tournament_id")
        out[pid] = {
            "prior_rating": float(prior_rating) if prior_rating is not None else None,
            "prior_tournament_id": int(prior_tid) if prior_tid is not None else None,
            "prior_games": int(row.get("prior_games") or 0),
        }
    return out


def persist_tournament_event_snapshots(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
    participant_ids: set[int],
    *,
    participation_by_player: dict[int, dict[str, Any]] | None = None,
    honours_by_player: dict[int, dict[str, Any]] | None = None,
    prior_career_best: dict[int, dict[str, Any]] | None = None,
    event_games_by_player_tournament: dict[tuple[int, int], int] | None = None,
    geo_year: PlayerGeoYearTracker | None = None,
    player_countries: dict[int, str | None] | None = None,
) -> int:
    """
    Write amiga_player_event_snapshots + amiga_player_current for one finalized event.

    Requires in-memory participation rows (slice 8 — legacy participation table retired).
    When ``honours_by_player`` is omitted, loads honours from the latest prior snapshot per player.
    """
    active_ids = sorted(
        pid for pid in participant_ids if players.get(pid) is not None and players[pid].games > 0
    )
    if not active_ids:
        return 0

    if participation_by_player is None:
        raise ValueError(
            "persist_tournament_event_snapshots requires participation_by_player "
            f"(tournament_id={tournament_id})"
        )

    prior_rows = load_prior_snapshot_rows_before_tournament(conn, tournament_id, active_ids)
    carry_by_player = prior_rows
    if honours_by_player is not None:
        totals_by_player = honours_by_player
    else:
        totals_by_player = {
            pid: honours_from_snapshot_row(row) for pid, row in prior_rows.items()
        }

    if prior_career_best is None:
        prior_best = _prior_career_best_context(conn, active_ids, tournament_id)
    else:
        prior_best = prior_career_best

    if player_countries is None:
        player_countries = load_player_countries(conn)

    snapshot_sql = snapshot_insert_sql()
    current_sql = current_upsert_sql()
    snapshot_rows: list[dict[str, Any]] = []
    current_rows: list[dict[str, Any]] = []

    for pid in active_ids:
        participation = participation_by_player.get(pid)
        if participation is None:
            log.warning(
                "persist_tournament_event_snapshots: missing participation "
                "tournament_id=%s player_id=%s",
                tournament_id,
                pid,
            )
            continue

        totals = totals_by_player.get(pid)
        if totals is None:
            totals = empty_honours_totals()
            totals["last_event_date"] = participation.get("event_date")
            totals["last_tournament_id"] = tournament_id

        prior = prior_best.get(pid, {})
        prior_tid = prior.get("prior_tournament_id")
        prior_games = int(prior.get("prior_games") or 0)
        if (
            event_games_by_player_tournament is not None
            and prior_tid is not None
            and (pid, int(prior_tid)) in event_games_by_player_tournament
        ):
            prior_games = int(event_games_by_player_tournament[(pid, int(prior_tid))])

        snapshot, current = build_snapshot_from_finalize_parts(
            participation=participation,
            player_state=players[pid],
            honours_totals=totals,
            prior_career_best_performance_rating=prior.get("prior_rating"),
            prior_career_best_performance_tournament_id=prior_tid,
            prior_career_best_games=prior_games,
            geo_year_scalars=(
                geo_year.scalars_for(pid, (player_countries or {}).get(pid))
                if geo_year is not None
                else None
            ),
            prior_career_row=carry_by_player.get(pid),
        )
        snapshot_rows.append(snapshot)
        current_rows.append(current)

        if prior_career_best is not None:
            best_tid = snapshot.get("career_best_performance_tournament_id")
            games_lookup = int(participation.get("games") or 0)
            if event_games_by_player_tournament is not None and best_tid is not None:
                games_lookup = int(
                    event_games_by_player_tournament.get((pid, int(best_tid)), games_lookup)
                )
            prior_career_best[pid] = {
                "prior_rating": snapshot.get("career_best_performance_rating"),
                "prior_tournament_id": best_tid,
                "prior_games": games_lookup,
            }

    if not snapshot_rows:
        return 0

    with conn.cursor() as cur:
        cur.executemany(snapshot_sql, snapshot_rows)
        cur.executemany(current_sql, current_rows)
    conn.commit()

    log.info(
        "persist_tournament_event_snapshots: tournament_id=%s snapshots=%s",
        tournament_id,
        len(snapshot_rows),
    )
    return len(snapshot_rows)
