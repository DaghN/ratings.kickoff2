"""Finalize one tournament: frozen Elo batch + rating events commit."""

from __future__ import annotations

import logging
from datetime import datetime, timezone
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_stats_load import load_player_states
from scripts.amiga.performance_rating import performance_rating_from_pairs
from scripts.amiga.player_tournament_participation import refresh_tournament_participation_stack
from scripts.amiga.replay import (
    _connect,
    _rating_insert_sql,
    _row_to_rating_insert,
    _stats_row,
)
from scripts.ladder.constants import ESTABLISHED_MIN_GAMES, START_RATING
from scripts.ladder.engine import apply_game_row
from scripts.ladder.finalize_counts import finalize_network_counts_from_rows
from scripts.ladder.player_state import PlayerState

# Re-exported for batch replay / refinalize orchestration.
__all__ = [
    "TournamentAlreadyFinalizedError",
    "TournamentNotFoundError",
    "commit_heavy_player_derived",
    "finalize_tournament",
    "recompute_rating_peaks_from_events",
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


def _stats_upsert_sql() -> str:
    sample = PlayerState().to_db_row(1)
    cols = [k for k in sample if k != "ID"]
    col_list = ", ".join(f"`{c}`" for c in cols)
    val_list = ", ".join(f"%({c})s" for c in cols)
    updates = ", ".join(f"`{c}`=VALUES(`{c}`)" for c in cols)
    return (
        f"INSERT INTO amiga_player_stats (player_id, {col_list}) "
        f"VALUES (%(player_id)s, {val_list}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def _row_to_rating_insert_finalize(game_id: int, row: dict[str, Any]) -> dict[str, Any]:
    out = _row_to_rating_insert(game_id, row)
    out["new_rating_a"] = None
    out["new_rating_b"] = None
    return out


def _load_tournament(conn: pymysql.connections.Connection, tournament_id: int) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, name, rating_finalized FROM tournaments WHERE id = %s LIMIT 1",
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


def _frozen_ratings(
    participant_ids: set[int],
    players: dict[int, PlayerState],
) -> dict[int, float]:
    frozen: dict[int, float] = {}
    for pid in participant_ids:
        st = players.get(pid)
        frozen[pid] = st.rating if st is not None else START_RATING
    return frozen


def _load_rated_game_rows(conn: pymysql.connections.Connection) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT g.id AS id, g.player_a_id AS idA, g.player_b_id AS idB,
                   r.actual_score AS ActualScore,
                   r.dd_player_a AS DDPlayerA, r.dd_player_b AS DDPlayerB,
                   r.cs_player_a AS CSPlayerA, r.cs_player_b AS CSPlayerB
            FROM amiga_games g
            INNER JOIN amiga_game_ratings r ON r.game_id = g.id
            ORDER BY g.game_date ASC, g.id ASC
            """
        )
        return cur.fetchall()


def recompute_rating_peaks_from_events(
    conn: pymysql.connections.Connection,
    players: dict[int, PlayerState],
    player_ids: set[int],
) -> None:
    """Set PeakRating / LowestRating from amiga_rating_events chronology only."""
    sql = """
        SELECT e.rating_before, e.rating_after
        FROM amiga_rating_events e
        INNER JOIN tournaments t ON t.id = e.tournament_id
        WHERE e.player_id = %s
        ORDER BY t.event_date ASC, t.chrono ASC, e.finalized_at ASC, e.id ASC
    """
    with conn.cursor() as cur:
        for pid in player_ids:
            st = players.get(pid)
            if st is None or st.games < ESTABLISHED_MIN_GAMES:
                continue
            cur.execute(sql, (pid,))
            events = cur.fetchall()
            if not events:
                continue
            points = [float(events[0]["rating_before"])]
            points.extend(float(row["rating_after"]) for row in events)
            st.peak_rating = max(points)
            st.lowest_rating = min(points)


def _apply_tournament_stats_batch(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
    names: dict[int, str],
) -> None:
    """Replay career stats for one already-finalized tournament (ratings/events unchanged)."""
    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()
    if not games:
        return

    participant_ids = _participant_ids(games)
    for pid in participant_ids:
        players.setdefault(pid, PlayerState())

    frozen = _frozen_ratings(participant_ids, players)
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
            FROM amiga_rating_events
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        for row in cur.fetchall():
            pid = int(row["player_id"])
            if pid in players:
                players[pid].rating = float(row["rating_after"])


def verify_tournament_finalize(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[str]:
    """Return human-readable errors for contract checks on one tournament."""
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT e.player_id, e.rating_before, e.rating_delta, e.rating_after
            FROM amiga_rating_events e
            WHERE e.tournament_id = %s
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

    return errors


def commit_heavy_player_derived(
    conn: pymysql.connections.Connection,
    players: dict[int, PlayerState] | None = None,
) -> int:
    """
    One-pass network counts + peak/nadir for all players after batch finalize replay.

    Live single-tournament finalize runs this inline; batch jobs defer until the end.
    Pass the shared in-memory ``players`` dict from batch replay (career state accumulated
    across tournaments); when omitted, loads from ``amiga_player_stats``.
    """
    if players is None:
        players = load_player_states(conn)
    if not players:
        log.info("commit_heavy_player_derived: no players to commit")
        return 0

    finalize_network_counts_from_rows(players, _load_rated_game_rows(conn))
    recompute_rating_peaks_from_events(conn, players, set(players.keys()))

    stats_sql = _stats_upsert_sql()
    stat_rows = [_stats_row(pid, st) for pid, st in players.items() if st.games > 0]
    with conn.cursor() as cur:
        if stat_rows:
            cur.executemany(stats_sql, stat_rows)
    conn.commit()
    log.info("commit_heavy_player_derived: wrote %s player stat rows", len(stat_rows))
    return len(stat_rows)


def finalize_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
    defer_heavy_derived: bool = False,
    persist_player_stats: bool = True,
    players: dict[int, PlayerState] | None = None,
    names: dict[int, str] | None = None,
) -> dict[str, Any]:
    """
    Finalize one tournament per amiga-tournament-finalize-rating-contract.md § 5.

    Requires prior tournaments (if any) already finalized when players carry history.

    Batch replay passes a shared ``players`` dict (entry state carried in memory),
    ``names`` loaded once, ``persist_player_stats=False``, and ``defer_heavy_derived=True``;
    then ``commit_heavy_player_derived(conn, players)`` once at the end.

    Live ops use defaults: load entry state from DB, persist stats, run heavy derived inline.
    """
    tour = _load_tournament(conn, tournament_id)
    if int(tour["rating_finalized"]) == 1:
        raise TournamentAlreadyFinalizedError(
            f"tournament_id={tournament_id} ({tour['name']!r}) already rating_finalized"
        )

    with conn.cursor() as cur:
        cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tournament_id,))
        games = cur.fetchall()

    if not games:
        log.info("finalize_tournament: tournament_id=%s has no games; skipping", tournament_id)
        return {"tournament_id": tournament_id, "games": 0, "skipped": True}

    participant_ids = _participant_ids(games)
    if players is None:
        players = load_player_states(conn)
    for pid in participant_ids:
        players.setdefault(pid, PlayerState())

    frozen = _frozen_ratings(participant_ids, players)
    log.info(
        "finalize_tournament: id=%s name=%r games=%s participants=%s",
        tournament_id,
        tour["name"],
        len(games),
        len(participant_ids),
    )

    if names is None:
        names = _load_player_names(conn)

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
    stats_sql = _stats_upsert_sql()
    pending_delta: dict[int, float] = {pid: 0.0 for pid in participant_ids}
    games_in_event: dict[int, int] = {pid: 0 for pid in participant_ids}
    perf_pairs: dict[int, list[tuple[float, float]]] = {pid: [] for pid in participant_ids}
    rating_rows: list[dict[str, Any]] = []

    with conn.cursor() as cur:
        for game in games:
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
            rating_rows.append(_row_to_rating_insert_finalize(game_id, row))

        if rating_rows:
            cur.executemany(rating_sql, rating_rows)

        if not defer_heavy_derived:
            finalize_network_counts_from_rows(players, _load_rated_game_rows(conn))

        finalized_at = datetime.now(timezone.utc).replace(tzinfo=None)
        event_sql = """
            INSERT INTO amiga_rating_events (
                tournament_id, player_id, rating_before, rating_delta,
                rating_after, performance_rating, games_in_event, finalized_at
            ) VALUES (
                %(tournament_id)s, %(player_id)s, %(rating_before)s, %(rating_delta)s,
                %(rating_after)s, %(performance_rating)s, %(games_in_event)s, %(finalized_at)s
            )
        """
        for pid in sorted(participant_ids):
            if games_in_event.get(pid, 0) == 0:
                continue
            rating_before = frozen[pid]
            rating_delta = round(pending_delta.get(pid, 0.0), 6)
            rating_after = round(rating_before + rating_delta, 6)
            performance_rating = performance_rating_from_pairs(perf_pairs.get(pid, []))
            players[pid].rating = rating_after
            cur.execute(
                event_sql,
                {
                    "tournament_id": tournament_id,
                    "player_id": pid,
                    "rating_before": rating_before,
                    "rating_delta": rating_delta,
                    "rating_after": rating_after,
                    "performance_rating": performance_rating,
                    "games_in_event": games_in_event[pid],
                    "finalized_at": finalized_at,
                },
            )

        if not defer_heavy_derived:
            recompute_rating_peaks_from_events(conn, players, participant_ids)

        if persist_player_stats:
            stat_rows = [
                _stats_row(pid, players[pid])
                for pid in participant_ids
                if players[pid].games > 0
            ]
            if stat_rows:
                cur.executemany(stats_sql, stat_rows)

        cur.execute(
            """
            UPDATE tournaments
            SET rating_finalized = 1, rating_finalized_at = %s
            WHERE id = %s
            """,
            (finalized_at, tournament_id),
        )

    conn.commit()

    if not defer_heavy_derived:
        part_rows, totals_players = refresh_tournament_participation_stack(conn, tournament_id)
        log.info(
            "finalize_tournament: participation refresh tournament_id=%s rows=%s totals_players=%s",
            tournament_id,
            part_rows,
            totals_players,
        )
        errors = verify_tournament_finalize(conn, tournament_id)
        if errors:
            raise RuntimeError(
                f"finalize_tournament verification failed for tournament_id={tournament_id}: "
                + "; ".join(errors)
            )

    log.info(
        "finalize_tournament complete: id=%s events=%s",
        tournament_id,
        len([pid for pid in participant_ids if games_in_event.get(pid, 0) > 0]),
    )
    return {
        "tournament_id": tournament_id,
        "name": tour["name"],
        "games": len(games),
        "rating_events": len([pid for pid in participant_ids if games_in_event.get(pid, 0) > 0]),
        "skipped": False,
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
