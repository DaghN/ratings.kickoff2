"""Chronological Elo replay on ko2amiga_work (fork of legacy replay)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.modern.clear_derived import clear_derived
from scripts.amiga.modern.work_db import connect_work
from scripts.amiga.matchup_cumulative import MatchupCumulative
from scripts.amiga.realm_incremental import empty_prior_payload
from scripts.k2_rating_core.player_state import PlayerState

log = logging.getLogger(__name__)

TOURNAMENT_REPLAY_ORDER = """
    SELECT t.id, COUNT(g.id) AS game_count
    FROM tournaments t
    INNER JOIN amiga_games g ON g.tournament_id = t.id
    GROUP BY t.id, t.event_date, t.chrono
    ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC
"""


def tournament_ids_for_replay(
    conn: pymysql.connections.Connection,
    *,
    limit_games: int | None = None,
) -> tuple[list[int], int]:
    with conn.cursor() as cur:
        cur.execute(TOURNAMENT_REPLAY_ORDER)
        rows = cur.fetchall()

    ids: list[int] = []
    games_total = 0
    for row in rows:
        tid = int(row["id"])
        count = int(row["game_count"])
        ids.append(tid)
        games_total += count
        if limit_games is not None and games_total >= limit_games:
            break
    return ids, games_total


def _replay_post_checks(
    conn: pymysql.connections.Connection,
    *,
    full_rebuild: bool,
) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_game_ratings")
        ratings = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournaments t
            WHERE t.rating_finalized = 0
              AND EXISTS (
                SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id
              )
            """
        )
        unfinalized = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_event_snapshots")
        snapshots = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_at_event")
        matchup_at_event = int(cur.fetchone()["n"])

    if full_rebuild:
        if games != ratings:
            raise SystemExit(
                f"replay post-check failed: amiga_games={games} amiga_game_ratings={ratings}"
            )
        if unfinalized:
            raise SystemExit(
                f"replay post-check failed: {unfinalized} tournament(s) with games not rating_finalized"
            )
    elif ratings > games:
        raise SystemExit(
            f"replay post-check failed: more ratings ({ratings}) than games ({games})"
        )

    log.info(
        "replay post-checks OK: games=%s ratings=%s snapshots=%s matchup_at_event=%s "
        "unfinalized_with_games=%s",
        games,
        ratings,
        snapshots,
        matchup_at_event,
        unfinalized,
    )


def replay_all(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    limit: int | None = None,
) -> None:
    from scripts.amiga.finalize_tournament import (
        _load_player_names,
        finalize_tournament,
    )
    from scripts.amiga.player_geo_year import PlayerGeoYearTracker, load_player_countries
    from scripts.amiga.country_slice_game_stats import CountryWorldCupSliceTracker
    from scripts.amiga.slice_game_stats import WorldCupSliceTracker

    tournament_ids, games_in_scope = tournament_ids_for_replay(conn, limit_games=limit)
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        total_games = int(cur.fetchone()["n"])

    log.info(
        "replay_all: %s tournaments to finalize (%s games in scope, %s total in DB)",
        len(tournament_ids),
        games_in_scope,
        total_games,
    )

    if dry_run:
        if tournament_ids:
            result = finalize_tournament(conn, tournament_ids[0], dry_run=True)
            log.info("Dry-run first tournament: %s", result)
        return

    players: dict[int, PlayerState] = {}
    matchups = MatchupCumulative()
    names = _load_player_names(conn)
    player_countries = load_player_countries(conn)
    geo_year = PlayerGeoYearTracker()
    honours_by_player: dict[int, dict[str, Any]] = {}
    slice_by_player: dict[int, dict[str, Any]] = {}
    slice_trackers: dict[int, WorldCupSliceTracker] = {}
    country_trackers: dict[str, CountryWorldCupSliceTracker] = {}
    prior_career_best: dict[int, dict[str, Any]] = {}
    event_games: dict[tuple[int, int], int] = {}
    prior_realm_payload: dict[str, Any] = empty_prior_payload()
    inverse_changelog_prev: dict[tuple[int, str], int] = {}
    games_processed = 0
    events_total = 0
    for idx, tournament_id in enumerate(tournament_ids, start=1):
        result = finalize_tournament(
            conn,
            tournament_id,
            dry_run=False,
            players=players,
            names=names,
            honours_by_player=honours_by_player,
            slice_by_player=slice_by_player,
            slice_trackers=slice_trackers,
            country_trackers=country_trackers,
            prior_career_best=prior_career_best,
            event_games=event_games,
            matchups=matchups,
            prior_realm_payload=prior_realm_payload,
            geo_year=geo_year,
            player_countries=player_countries,
            inverse_changelog_prev=inverse_changelog_prev,
        )
        if result.get("skipped"):
            continue
        prior_realm_payload = result.get("realm_payload") or prior_realm_payload
        games_processed += int(result.get("games", 0))
        events_total += int(result.get("rating_events", 0))
        if idx % 50 == 0 or idx == len(tournament_ids):
            log.info(
                "replay progress: %s / %s tournaments, %s games finalized",
                idx,
                len(tournament_ids),
                games_processed,
            )

    conn.commit()
    _replay_post_checks(conn, full_rebuild=limit is None)
    log.info(
        "replay_all complete: tournaments=%s games=%s rating_events=%s",
        len(tournament_ids),
        games_processed,
        events_total,
    )


def run_replay_work(*, dry_run: bool = False, limit: int | None = None) -> None:
    if limit is not None:
        log.warning("run_replay_work --limit=%s: smoke only; not simul sign-off", limit)
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
        clear_derived(conn, dry_run=dry_run)
        replay_all(conn, dry_run=dry_run, limit=limit)
    finally:
        conn.close()