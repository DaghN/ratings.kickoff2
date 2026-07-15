"""Finalize one tournament: frozen Elo batch + event snapshots."""

from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.honours_totals import empty_honours_totals, increment_honours_totals
from scripts.amiga.slice_persist import (
    load_prior_world_cup_slices,
    persist_world_cup_slices_at_tournament,
)
from scripts.amiga.country_slice_game_stats import CountryWorldCupSliceTracker
from scripts.amiga.slice_game_stats import WorldCupSliceTracker, apply_world_cup_tournament_games
from scripts.amiga.slice_totals import empty_world_cup_slice, increment_world_cup_slice
from scripts.amiga.wc_slice_awards import apply_wc_slice_awards_and_peaks
from scripts.amiga.tournament_honours import is_world_cup_tournament, tournament_is_world_cup
from scripts.amiga.player_stats_load import load_player_states_before_tournament
from scripts.amiga.performance_rating import performance_rating_from_pairs
from scripts.amiga.player_tournament_participation import build_participation_rows_for_tournament
from scripts.amiga.snapshot_persist import persist_tournament_event_snapshots
from scripts.amiga.replay import (
    _connect,
    _rating_insert_sql,
    _row_to_rating_insert,
)
from scripts.amiga.tournament_catalog_stats import refresh_catalog_stats_for_tournament
from scripts.amiga.tournament_standings import rebuild_standings_for_tournament
from scripts.amiga.scoring_contract import freeze_scoring_contracts_for_tournament
from scripts.k2_rating_core.constants import START_RATING
from scripts.k2_rating_core.apply_game import apply_game_row
from scripts.amiga.matchup_cumulative import (
    MatchupCumulative,
    apply_peak_from_event_rating,
)
from scripts.amiga.matchup_persist import persist_matchup_at_event, upsert_matchup_summary
from scripts.amiga.community_persist import persist_community_for_tournament
from scripts.amiga.world_cup_stats import persist_world_cup_stats_for_tournament
from scripts.amiga.realm_persist import persist_realm_snapshot_for_tournament
from scripts.amiga.generalstats_columns import GENERALSTATS_PAYLOAD_COLUMNS
from scripts.amiga.player_geo_year import PlayerGeoYearTracker, load_player_countries
from scripts.k2_rating_core.player_state import PlayerState

__all__ = [
    "TournamentAlreadyFinalizedError",
    "TournamentNotFoundError",
    "finalize_tournament",
    "run_finalize_tournament",
    "verify_tournament_finalize",
]

log = logging.getLogger(__name__)

GAME_SELECT_FOR_TOURNAMENT = """
    SELECT g.id, g.game_date AS Date, g.player_a_id AS idA, g.player_b_id AS idB,
           g.goals_a AS GoalsA, g.goals_b AS GoalsB
    FROM amiga_games g
    WHERE g.tournament_id = %s
    ORDER BY g.game_date ASC, g.id ASC
"""


class TournamentAlreadyFinalizedError(RuntimeError):
    pass


class TournamentNotFoundError(RuntimeError):
    pass


def _row_to_rating_insert_finalize(game_id: int, row: dict[str, Any]) -> dict[str, Any]:
    out = _row_to_rating_insert(game_id, row)
    out["new_rating_a"] = None
    out["new_rating_b"] = None
    return out


def _load_tournament(conn: pymysql.connections.Connection, tournament_id: int) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, name, rating_finalized, event_date, chrono, country, has_league, has_cup, is_world_cup FROM tournaments WHERE id = %s LIMIT 1",
            (tournament_id,),
        )
        row = cur.fetchone()
    if row is None:
        raise TournamentNotFoundError(f"tournament_id={tournament_id} not found")
    return row


def _participant_ids(games: list[dict[str, Any]]) -> set[int]:
    ids: set[int] = set()
    for game in games:
        ids.add(int(game["idA"]))
        ids.add(int(game["idB"]))
    return ids


def _load_player_names(conn: pymysql.connections.Connection) -> dict[int, str]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM amiga_players")
        return {int(row["id"]): str(row["name"]) for row in cur.fetchall()}


def _entry_ratings_before_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    participant_ids: set[int],
) -> dict[int, float]:
    """Entry Elo: last committed ``rating_after`` before this event (from snapshots)."""
    if not participant_ids:
        return {}

    with conn.cursor() as cur:
        cur.execute(
            "SELECT event_date, chrono FROM tournaments WHERE id = %s LIMIT 1",
            (tournament_id,),
        )
        tour = cur.fetchone()
        if tour is None:
            return {pid: START_RATING for pid in participant_ids}

        placeholders = ", ".join(["%s"] * len(participant_ids))
        sql = f"""
            SELECT player_id, rating_after
            FROM (
                SELECT s.player_id, s.rating_after,
                       ROW_NUMBER() OVER (
                           PARTITION BY s.player_id
                           ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots s
                INNER JOIN tournaments t ON t.id = s.tournament_id
                WHERE s.player_id IN ({placeholders})
                  AND (t.event_date, t.chrono, t.id) < (%s, %s, %s)
            ) ranked
            WHERE rn = 1
        """
        cur.execute(
            sql,
            (
                *sorted(participant_ids),
                tour["event_date"],
                tour["chrono"],
                tournament_id,
            ),
        )
        rows = cur.fetchall()

    frozen = {pid: START_RATING for pid in participant_ids}
    for row in rows:
        frozen[int(row["player_id"])] = float(row["rating_after"])
    return frozen


def _apply_tournament_matchups_batch(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    matchups: MatchupCumulative,
) -> None:
    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()
    for game in games:
        matchups.apply_game(game)


def _apply_tournament_stats_batch(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
    names: dict[int, str],
) -> None:
    """Replay career stats for one finalized tournament (ratings unchanged)."""
    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()
    if not games:
        return

    participant_ids = _participant_ids(games)
    for pid in participant_ids:
        players.setdefault(pid, PlayerState())

    frozen = _entry_ratings_before_tournament(conn, tournament_id, participant_ids)
    for game in games:
        apply_game_row(
            game,
            players,
            names=names,
            frozen_ratings=frozen,
            commit_rating=False,
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, rating_after
            FROM amiga_player_event_snapshots
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            pid = int(row["player_id"])
            if pid in players:
                players[pid].rating = float(row["rating_after"])


def _persist_event_snapshots(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
    participant_ids: set[int],
    event_commits: dict[int, dict[str, Any]],
    *,
    honours_by_player: dict[int, dict[str, Any]] | None = None,
    slice_by_player: dict[int, dict[str, Any]] | None = None,
    slice_trackers: dict[int, WorldCupSliceTracker] | None = None,
    tournament_games: list[dict[str, Any]] | None = None,
    tournament_name: str | None = None,
    prior_career_best: dict[int, dict[str, Any]] | None = None,
    event_games: dict[tuple[int, int], int] | None = None,
    geo_year: PlayerGeoYearTracker | None = None,
    player_countries: dict[int, str | None] | None = None,
    inverse_changelog_prev: dict[tuple[int, str], int] | None = None,
) -> int:
    rebuild_standings_for_tournament(conn, tournament_id)

    part_rows = build_participation_rows_for_tournament(
        conn,
        tournament_id,
        rating_events_by_player=event_commits,
    )
    participation_by_player = {int(row["player_id"]): row for row in part_rows}

    honours_for_event: dict[int, dict[str, Any]] | None = None
    if honours_by_player is not None:
        honours_for_event = {}
        for row in part_rows:
            pid = int(row["player_id"])
            tid = int(row["tournament_id"])
            if event_games is not None:
                event_games[(pid, tid)] = int(row.get("games") or 0)
            if pid not in honours_by_player:
                honours_by_player[pid] = empty_honours_totals()
            increment_honours_totals(honours_by_player[pid], row)
            honours_for_event[pid] = dict(honours_by_player[pid])

    participant_pids = [int(row["player_id"]) for row in part_rows]
    if slice_by_player is not None:
        slice_accum: dict[int, dict[str, Any]] = slice_by_player
    else:
        slice_accum = load_prior_world_cup_slices(conn, tournament_id, participant_pids)
    slice_for_event: dict[int, dict[str, Any]] = {}
    for row in part_rows:
        pid = int(row["player_id"])
        tid = int(row["tournament_id"])
        if event_games is not None and honours_by_player is None:
            event_games[(pid, tid)] = int(row.get("games") or 0)
        if pid not in slice_accum:
            slice_accum[pid] = empty_world_cup_slice()
        increment_world_cup_slice(slice_accum[pid], row)
        slice_for_event[pid] = dict(slice_accum[pid])

    wc_event = bool(part_rows) and tournament_is_world_cup(part_rows[0])

    if (
        tournament_games
        and wc_event
        and slice_trackers is not None
        and player_countries is not None
    ):
        participating = {int(row["player_id"]) for row in part_rows}
        apply_world_cup_tournament_games(
            slice_accum,
            slice_trackers,
            tournament_games,
            player_countries,
            participating,
        )
        # §4.7 awards + §4.8 single-WC peaks (must precede slice persist below).
        apply_wc_slice_awards_and_peaks(slice_accum, part_rows, tournament_id)
        for pid in participating:
            if pid in slice_accum:
                slice_for_event[pid] = dict(slice_accum[pid])

    snapshot_rows = persist_tournament_event_snapshots(
        conn,
        tournament_id,
        players,
        participant_ids,
        participation_by_player=participation_by_player,
        honours_by_player=honours_for_event,
        prior_career_best=prior_career_best,
        event_games_by_player_tournament=event_games,
        geo_year=geo_year,
        player_countries=player_countries,
        inverse_changelog_prev=inverse_changelog_prev,
    )
    refresh_catalog_stats_for_tournament(conn, tournament_id)

    if slice_for_event:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT event_date, chrono FROM tournaments WHERE id = %s LIMIT 1",
                (tournament_id,),
            )
            tour = cur.fetchone()
        event_date = tour.get("event_date") if tour else None
        event_chrono = float(tour.get("chrono") or 0.0) if tour else 0.0
        persist_world_cup_slices_at_tournament(
            conn,
            tournament_id,
            event_date,
            event_chrono,
            slice_for_event,
            participant_ids=participant_ids,
        )

    return snapshot_rows


def _rated_games_through_tournament_sql() -> str:
    return """
        SELECT sides.player_id, COUNT(*) AS rated_games
        FROM (
            SELECT g.id, g.player_a_id AS player_id, g.tournament_id
            FROM amiga_games g
            UNION ALL
            SELECT g.id, g.player_b_id AS player_id, g.tournament_id
            FROM amiga_games g
        ) sides
        INNER JOIN tournaments t ON t.id = sides.tournament_id
        INNER JOIN tournaments tc ON tc.id = %s
        WHERE (
            t.event_date < tc.event_date
            OR (t.event_date = tc.event_date AND t.chrono < tc.chrono)
            OR (
                t.event_date = tc.event_date
                AND t.chrono = tc.chrono
                AND t.id <= tc.id
            )
        )
        GROUP BY sides.player_id
    """


def _career_games_snapshot_mismatch_errors(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[str]:
    """Snapshot cumulative games must match rated games through this event."""
    errors: list[str] = []
    games_through = _rated_games_through_tournament_sql()
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT s.player_id, s.NumberGames AS snapshot_games, g.rated_games
            FROM amiga_player_event_snapshots s
            INNER JOIN ({games_through}) g ON g.player_id = s.player_id
            WHERE s.tournament_id = %s
              AND s.NumberGames <> g.rated_games
            LIMIT 5
            """,
            (tournament_id, tournament_id),
        )
        for row in cur.fetchall():
            errors.append(
                "player_id="
                f"{int(row['player_id'])} snapshot.NumberGames="
                f"{int(row['snapshot_games'])} != rated_games={int(row['rated_games'])}"
            )
    return errors


def _career_games_current_mismatch_errors(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[str]:
    """Present row must match rated games through this event (live finalize only)."""
    errors: list[str] = []
    games_through = _rated_games_through_tournament_sql()
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT c.player_id, c.NumberGames AS current_games, g.rated_games
            FROM amiga_player_current c
            INNER JOIN amiga_player_event_snapshots s
                ON s.player_id = c.player_id AND s.tournament_id = %s
            INNER JOIN ({games_through}) g ON g.player_id = c.player_id
            WHERE c.NumberGames <> g.rated_games
            LIMIT 5
            """,
            (tournament_id, tournament_id),
        )
        for row in cur.fetchall():
            errors.append(
                "player_id="
                f"{int(row['player_id'])} current.NumberGames="
                f"{int(row['current_games'])} != rated_games={int(row['rated_games'])}"
            )
    return errors


def verify_tournament_finalize(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    check_current_career_games: bool = True,
) -> list[str]:
    """Return human-readable errors for contract checks on one tournament."""
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, rating_before, rating_delta, rating_after
            FROM amiga_player_event_snapshots
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        events = {int(row["player_id"]): row for row in cur.fetchall()}

        cur.execute(
            """
            SELECT g.player_a_id AS idA, g.player_b_id AS idB,
                   r.adjustment_a, r.adjustment_b
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        game_rows = cur.fetchall()

        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s",
            (tournament_id,),
        )
        game_count = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            WHERE g.tournament_id = %s
            """,
            (tournament_id,),
        )
        rated_count = int(cur.fetchone()["n"])

    if game_count != rated_count:
        errors.append(
            f"games={game_count} rated_rows={rated_count} for tournament_id={tournament_id}"
        )

    delta_by_player: dict[int, float] = {}
    for row in game_rows:
        id_a = int(row["idA"])
        id_b = int(row["idB"])
        delta_by_player[id_a] = delta_by_player.get(id_a, 0.0) + float(row["adjustment_a"])
        delta_by_player[id_b] = delta_by_player.get(id_b, 0.0) + float(row["adjustment_b"])

    for pid, event in events.items():
        rb = float(event["rating_before"])
        rd = float(event["rating_delta"])
        ra = float(event["rating_after"])
        if abs(ra - (rb + rd)) > 1e-5:
            errors.append(f"player_id={pid} rating_after != rating_before + rating_delta")
        summed = round(delta_by_player.get(pid, 0.0), 6)
        if abs(summed - rd) > 1e-5:
            errors.append(
                f"player_id={pid} sum(adjustments)={summed} != rating_delta={rd}"
            )

    errors.extend(_career_games_snapshot_mismatch_errors(conn, tournament_id))
    if check_current_career_games:
        errors.extend(_career_games_current_mismatch_errors(conn, tournament_id))

    return errors


def finalize_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
    players: dict[int, PlayerState] | None = None,
    names: dict[int, str] | None = None,
    honours_by_player: dict[int, dict[str, Any]] | None = None,
    slice_by_player: dict[int, dict[str, Any]] | None = None,
    slice_trackers: dict[int, WorldCupSliceTracker] | None = None,
    country_trackers: dict[str, CountryWorldCupSliceTracker] | None = None,
    prior_career_best: dict[int, dict[str, Any]] | None = None,
    event_games: dict[tuple[int, int], int] | None = None,
    matchups: MatchupCumulative | None = None,
    prior_realm_payload: dict[str, Any] | None = None,
    geo_year: PlayerGeoYearTracker | None = None,
    player_countries: dict[int, str | None] | None = None,
    inverse_changelog_prev: dict[tuple[int, str], int] | None = None,
) -> dict[str, Any]:
    """
    Finalize one tournament: game ratings + event snapshots + current projection.

    Batch replay passes shared ``players``, ``matchups``, honours dicts, and prior context.
    """
    tour = _load_tournament(conn, tournament_id)
    if int(tour["rating_finalized"]) == 1:
        raise TournamentAlreadyFinalizedError(
            f"tournament_id={tournament_id} ({tour['name']!r}) already rating_finalized"
        )

    from scripts.amiga.promote_running_tournament import promote_running_tournament

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        existing_games = int(cur.fetchone()["n"])
    if existing_games == 0:
        promote_running_tournament(conn, tournament_id, dry_run=dry_run)

    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()

    if not games:
        log.info("finalize_tournament: tournament_id=%s has no games; skipping", tournament_id)
        return {"tournament_id": tournament_id, "games": 0, "skipped": True}

    participant_ids = _participant_ids(games)
    if players is None:
        players = load_player_states_before_tournament(conn, tournament_id, participant_ids)
    if matchups is None:
        matchups = MatchupCumulative()
        matchups.load_from_summary(conn, participant_ids)
    for pid in participant_ids:
        players.setdefault(pid, PlayerState())

    if names is None:
        names = _load_player_names(conn)

    frozen = _entry_ratings_before_tournament(conn, tournament_id, participant_ids)
    log.info(
        "finalize_tournament: id=%s name=%r games=%s participants=%s",
        tournament_id,
        tour["name"],
        len(games),
        len(participant_ids),
    )

    if player_countries is None:
        player_countries = load_player_countries(conn)
    if geo_year is None:
        geo_year = PlayerGeoYearTracker()

    if dry_run:
        sample = apply_game_row(
            games[0],
            players,
            names=names,
            frozen_ratings=frozen,
            commit_rating=False,
        )
        log.info(
            "Dry-run sample game id=%s frozen RatingA=%.3f AdjustmentA=%.3f",
            games[0]["id"],
            sample["RatingA"],
            sample["AdjustmentA"],
        )
        return {
            "tournament_id": tournament_id,
            "games": len(games),
            "dry_run": True,
        }

    rating_sql = _rating_insert_sql()
    pending_delta: dict[int, float] = {pid: 0.0 for pid in participant_ids}
    games_in_event: dict[int, int] = {pid: 0 for pid in participant_ids}
    perf_pairs: dict[int, list[tuple[float, float]]] = {pid: [] for pid in participant_ids}
    touched_matchup_pairs: set[tuple[int, int]] = set()
    rating_rows: list[dict[str, Any]] = []
    ratings_by_game_id: dict[int, dict[str, Any]] = {}
    event_commits: dict[int, dict[str, Any]] = {}

    finalized_at = datetime.now(timezone.utc).replace(tzinfo=None)

    with conn.cursor() as cur:
        for game in games:
            matchups.apply_game(game)
            game_id = int(game["id"])
            row = apply_game_row(
                game,
                players,
                names=names,
                frozen_ratings=frozen,
                commit_rating=False,
            )
            id_a = int(row["idA"])
            id_b = int(row["idB"])
            score_a = float(row["ActualScore"])
            rating_a = float(row["RatingA"])
            rating_b = float(row["RatingB"])
            pending_delta[id_a] = pending_delta.get(id_a, 0.0) + float(row["AdjustmentA"])
            pending_delta[id_b] = pending_delta.get(id_b, 0.0) + float(row["AdjustmentB"])
            games_in_event[id_a] = games_in_event.get(id_a, 0) + 1
            games_in_event[id_b] = games_in_event.get(id_b, 0) + 1
            perf_pairs[id_a].append((rating_b, score_a))
            perf_pairs[id_b].append((rating_a, 1.0 - score_a))
            matchups.apply_pair_perf_sample(id_a, id_b, rating_a, rating_b, score_a)
            touched_matchup_pairs.add((id_a, id_b))
            touched_matchup_pairs.add((id_b, id_a))
            rating_rows.append(_row_to_rating_insert_finalize(game_id, row))
            ratings_by_game_id[game_id] = _row_to_rating_insert(game_id, row)

        if rating_rows:
            cur.executemany(rating_sql, rating_rows)

        for pid in sorted(participant_ids):
            if games_in_event.get(pid, 0) == 0:
                continue
            rating_before = frozen[pid]
            rating_delta = round(pending_delta.get(pid, 0.0), 6)
            rating_after = round(rating_before + rating_delta, 6)
            performance_rating = performance_rating_from_pairs(perf_pairs.get(pid, []))
            players[pid].rating = rating_after
            event_commits[pid] = {
                "rating_before": rating_before,
                "rating_delta": rating_delta,
                "rating_after": rating_after,
                "performance_rating": performance_rating,
                "games_in_event": games_in_event[pid],
                "finalized_at": finalized_at,
            }

        cur.execute(
            """
            UPDATE tournaments
            SET rating_finalized = 1, rating_finalized_at = %s
            WHERE id = %s
            """,
            (finalized_at, tournament_id),
        )
        freeze_scoring_contracts_for_tournament(conn, tournament_id, finalized_at)

    conn.commit()

    # Per-opponent cumulative TPR: replay solves from in-memory samples; the
    # warm/live path reseeds touched pairs from the now-committed game ratings.
    matchups.recompute_touched_perf(conn, touched_matchup_pairs)

    for pid in sorted(participant_ids):
        if pid not in event_commits:
            continue
        matchups.apply_network_to_player_state(pid, players[pid])
        apply_peak_from_event_rating(
            players[pid], float(event_commits[pid]["rating_after"]), tournament_id
        )

    geo_year.apply_tournament(
        tournament_id=tournament_id,
        event_date=tour.get("event_date"),
        host_country=tour.get("country"),
        games=games,
        games_in_event=games_in_event,
        participant_ids=participant_ids,
        player_countries=player_countries,
    )

    snapshot_rows = _persist_event_snapshots(
        conn,
        tournament_id,
        players,
        participant_ids,
        event_commits,
        honours_by_player=honours_by_player,
        slice_by_player=slice_by_player,
        slice_trackers=slice_trackers,
        tournament_games=games,
        tournament_name=str(tour.get("name") or ""),
        prior_career_best=prior_career_best,
        event_games=event_games,
        geo_year=geo_year,
        player_countries=player_countries,
        inverse_changelog_prev=inverse_changelog_prev,
    )
    log.info(
        "finalize_tournament: event snapshots tournament_id=%s rows=%s",
        tournament_id,
        snapshot_rows,
    )

    matchup_at_event_rows = persist_matchup_at_event(
        conn,
        tournament_id,
        tour["event_date"],
        int(tour["chrono"]),
        matchups,
        participant_ids,
    )
    summary_rows = upsert_matchup_summary(conn, matchups, participant_ids)
    log.info(
        "finalize_tournament: matchup at_event=%s summary_upserts=%s",
        matchup_at_event_rows,
        summary_rows,
    )

    persist_community_for_tournament(
        conn,
        tournament_id,
        finalized_at=finalized_at,
        commit=False,
    )
    log.info("finalize_tournament: community stats tournament_id=%s", tournament_id)

    if persist_world_cup_stats_for_tournament(
        conn,
        tournament_id,
        finalized_at=finalized_at,
        commit=False,
    ):
        log.info("finalize_tournament: world cup stats tournament_id=%s", tournament_id)

    if tournament_is_world_cup(tour):
        from scripts.amiga.country_slice_compute import rebuild_country_slices_at_world_cup_finalize
        if country_trackers is None:
            country_trackers = {}
        rebuild_country_slices_at_world_cup_finalize(
            conn,
            tournament_id=tournament_id,
            event_date=tour.get("event_date"),
            event_chrono=float(tour.get("chrono") or 0.0),
            player_countries=player_countries,
            tournament_games=games,
            ratings_by_game_id=ratings_by_game_id,
            country_trackers=country_trackers,
            commit=False,
        )
        log.info("finalize_tournament: country slice tournament_id=%s", tournament_id)

    persist_realm_snapshot_for_tournament(
        conn,
        tournament_id,
        finalized_at=finalized_at,
        commit=True,
        prior_payload=prior_realm_payload,
        players=players,
        names=names,
        games=games,
        ratings_by_game_id=ratings_by_game_id,
        event_date=tour["event_date"],
    )
    log.info("finalize_tournament: realm snapshot tournament_id=%s", tournament_id)

    # WC Hall of Fame (sparse): compute + persist only on World Cup finalize, after
    # the WC slice (incl. awards/peaks) for this event has been written above.
    if tournament_is_world_cup(tour):
        from scripts.amiga.wc_hof_persist import persist_wc_hof_for_tournament

        persist_wc_hof_for_tournament(
            conn,
            tournament_id,
            finalized_at=finalized_at,
            commit=True,
        )
        log.info("finalize_tournament: wc hof snapshot tournament_id=%s", tournament_id)

    with conn.cursor() as cur:
        cur.execute(
            "SELECT * FROM amiga_realm_snapshots WHERE tournament_id = %s LIMIT 1",
            (tournament_id,),
        )
        realm_row = cur.fetchone()
    realm_payload = (
        {col: realm_row.get(col) for col in GENERALSTATS_PAYLOAD_COLUMNS}
        if realm_row
        else {}
    )

    errors = verify_tournament_finalize(conn, tournament_id)
    if errors:
        raise RuntimeError(
            f"finalize_tournament verification failed for tournament_id={tournament_id}: "
            + "; ".join(errors)
        )

    event_count = len(event_commits)
    log.info("finalize_tournament complete: id=%s events=%s", tournament_id, event_count)
    return {
        "tournament_id": tournament_id,
        "name": tour["name"],
        "games": len(games),
        "rating_events": event_count,
        "skipped": False,
        "realm_payload": realm_payload,
    }


def run_finalize_tournament(
    *,
    tournament_id: int,
    dry_run: bool = False,
) -> dict[str, Any]:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        return finalize_tournament(conn, tournament_id, dry_run=dry_run)
    finally:
        conn.close()
