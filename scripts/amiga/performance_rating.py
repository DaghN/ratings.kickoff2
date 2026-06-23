"""Chess-style performance rating per player per tournament (frozen opponent inputs)."""

from __future__ import annotations

import logging
from collections import defaultdict
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

log = logging.getLogger(__name__)

PERFORMANCE_RATING_MIN_GAMES = 2
_SCORE_TOLERANCE = 1e-9
_SEARCH_LO = -800.0
_SEARCH_HI_START = 4000.0
_SEARCH_ITERATIONS = 64


def elo_expected(player_rating: float, opponent_rating: float) -> float:
    return 1.0 / (1.0 + 10 ** ((opponent_rating - player_rating) / 400.0))


def solve_performance_rating(
    opponent_ratings: list[float],
    scores: list[float],
) -> float | None:
    """
    Find R such that sum(E(R, R_opp)) == sum(scores).

    Returns None when games < PERFORMANCE_RATING_MIN_GAMES or score is all wins / all losses.
    """
    if len(opponent_ratings) != len(scores) or len(opponent_ratings) < PERFORMANCE_RATING_MIN_GAMES:
        return None

    if all(abs(score - 1.0) < _SCORE_TOLERANCE for score in scores):
        return None
    if all(abs(score) < _SCORE_TOLERANCE for score in scores):
        return None

    total_score = sum(scores)

    def sum_expected(rating: float) -> float:
        return sum(elo_expected(rating, opp) for opp in opponent_ratings)

    lo = _SEARCH_LO
    hi = _SEARCH_HI_START
    while sum_expected(hi) < total_score:
        hi += 400.0

    for _ in range(_SEARCH_ITERATIONS):
        mid = (lo + hi) / 2.0
        if sum_expected(mid) < total_score:
            lo = mid
        else:
            hi = mid

    return round((lo + hi) / 2.0, 6)


def performance_rating_from_pairs(pairs: list[tuple[float, float]]) -> float | None:
    if len(pairs) < PERFORMANCE_RATING_MIN_GAMES:
        return None
    opponents = [opp for opp, _ in pairs]
    scores = [score for _, score in pairs]
    return solve_performance_rating(opponents, scores)


def _score_for_player_b(actual_score_a: float) -> float:
    return 1.0 - actual_score_a


def _collect_tournament_performance_pairs(
    game_rows: list[dict[str, Any]],
) -> dict[int, list[tuple[float, float]]]:
    by_player: dict[int, list[tuple[float, float]]] = defaultdict(list)
    for row in game_rows:
        id_a = int(row["idA"])
        id_b = int(row["idB"])
        rating_a = float(row["rating_a"])
        rating_b = float(row["rating_b"])
        score_a = float(row["actual_score"])
        by_player[id_a].append((rating_b, score_a))
        by_player[id_b].append((rating_a, _score_for_player_b(score_a)))
    return by_player


def _load_tournament_game_rating_rows(
    conn: pymysql.connections.Connection,
    tournament_id: int | None = None,
) -> list[dict[str, Any]]:
    sql = """
        SELECT g.tournament_id,
               g.player_a_id AS idA,
               g.player_b_id AS idB,
               r.rating_a,
               r.rating_b,
               r.actual_score
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
    """
    params: tuple[Any, ...] = ()
    if tournament_id is not None:
        sql += " WHERE g.tournament_id = %s"
        params = (tournament_id,)
    sql += " ORDER BY g.tournament_id ASC, g.game_date ASC, g.id ASC"
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.fetchall()


def backfill_performance_ratings(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
    dry_run: bool = False,
) -> int:
    """Recompute amiga_rating_events.performance_rating from stored game rows."""
    rows = _load_tournament_game_rating_rows(conn, tournament_id)
    if not rows:
        return 0

    by_tournament: dict[int, list[dict[str, Any]]] = defaultdict(list)
    for row in rows:
        by_tournament[int(row["tournament_id"])].append(row)

    updates: list[tuple[float | None, int, int]] = []
    for tid, tour_rows in by_tournament.items():
        pairs_by_player = _collect_tournament_performance_pairs(tour_rows)
        for player_id, pairs in pairs_by_player.items():
            perf = performance_rating_from_pairs(pairs)
            updates.append((perf, tid, player_id))

    if dry_run:
        log.info(
            "backfill_performance_ratings dry-run: would update %s rating events",
            len(updates),
        )
        return len(updates)

    sql = """
        UPDATE amiga_rating_events
        SET performance_rating = %s
        WHERE tournament_id = %s AND player_id = %s
    """
    with conn.cursor() as cur:
        for perf, tid, player_id in updates:
            cur.execute(sql, (perf, tid, player_id))
    conn.commit()
    log.info("backfill_performance_ratings: updated %s rating events", len(updates))
    return len(updates)
