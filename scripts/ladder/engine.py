"""Reset and chronological replay for online ladder v1."""

from __future__ import annotations

import logging
from dataclasses import dataclass, field
from datetime import datetime
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from .config import DbConfig
from .constants import (
    ALLOWED_DATABASES,
    PLAYERTABLE_LASTGAME_RESET,
    PLAYERTABLE_NULL_ON_RESET,
    PLAYERTABLE_SENTINELS_ON_RESET,
    RATEDRESULTS_CLEAR,
    START_RATING,
)
from .elo import compute_elo
from .outcome import outcome_from_goals

log = logging.getLogger(__name__)

GAME_SELECT = """
    SELECT id, Date, idA, idB, GoalsA, GoalsB
    FROM ratedresults
    ORDER BY Date ASC, id ASC
"""


@dataclass
class PlayerState:
    rating: float = START_RATING
    games: int = 0
    wins: int = 0
    draws: int = 0
    losses: int = 0
    goals_for: int = 0
    goals_against: int = 0
    last_game: datetime | None = None
    last_game_id: int | None = None


def connect(cfg: DbConfig, *, dry_run: bool) -> pymysql.connections.Connection:
    if cfg.database not in ALLOWED_DATABASES:
        raise SystemExit(
            f"Refusing to connect: database {cfg.database!r} not in {sorted(ALLOWED_DATABASES)}"
        )
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SELECT DATABASE() AS db")
        row = cur.fetchone()
        assert row is not None
        if row["db"] != cfg.database:
            raise SystemExit(f"DATABASE()={row['db']!r} != configured {cfg.database!r}")
    if dry_run:
        log.info("Dry-run: connected to %s (no commits)", cfg.database)
    return conn


def _sql_set_null(columns: tuple[str, ...]) -> str:
    return ", ".join(f"`{c}` = NULL" for c in columns)


def reset_universe(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    rated_clear = _sql_set_null(RATEDRESULTS_CLEAR)
    sql_rated = f"UPDATE ratedresults SET {rated_clear}"

    player_parts = [f"`Rating` = {START_RATING}"]
    player_parts.append(_sql_set_null(PLAYERTABLE_NULL_ON_RESET))
    for col, val in PLAYERTABLE_SENTINELS_ON_RESET.items():
        player_parts.append(f"`{col}` = {val}")
    player_parts.append(f"`LastGame` = '{PLAYERTABLE_LASTGAME_RESET}'")
    sql_player = f"UPDATE playertable SET {', '.join(player_parts)}"

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM ratedresults")
        games = cur.fetchone()["n"]
        cur.execute("SELECT COUNT(*) AS n FROM playertable")
        players = cur.fetchone()["n"]

    log.info("reset_universe: ratedresults rows=%s, playertable rows=%s", games, players)
    log.info("SQL ratedresults: %s", sql_rated)
    log.info("SQL playertable: %s ... (%s derived columns)", sql_player[:120], len(PLAYERTABLE_NULL_ON_RESET))

    if dry_run:
        return

    with conn.cursor() as cur:
        cur.execute(sql_rated)
        log.info("ratedresults cleared: %s rows affected", cur.rowcount)
        cur.execute(sql_player)
        log.info("playertable reset: %s rows affected", cur.rowcount)
    conn.commit()


def _ratio(numerator: int, denominator: int) -> float | None:
    if denominator <= 0:
        return None
    return round(numerator / denominator, 4)


def _apply_to_player(
    state: PlayerState,
    *,
    won: bool,
    drew: bool,
    goals_for: int,
    goals_against: int,
    new_rating: float,
    game_date: datetime,
    game_id: int,
) -> None:
    state.games += 1
    if won:
        state.wins += 1
    elif drew:
        state.draws += 1
    else:
        state.losses += 1
    state.goals_for += goals_for
    state.goals_against += goals_against
    state.rating = new_rating
    state.last_game = game_date
    state.last_game_id = game_id


def apply_game_row(
    game: dict[str, Any],
    players: dict[int, PlayerState],
) -> dict[str, Any]:
    """Compute v1 row + player deltas from in-memory state (does not hit DB)."""
    id_a = int(game["idA"])
    id_b = int(game["idB"])
    goals_a = int(game["GoalsA"])
    goals_b = int(game["GoalsB"])

    pa = players.setdefault(id_a, PlayerState())
    pb = players.setdefault(id_b, PlayerState())

    outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
    elo = compute_elo(pa.rating, pb.rating, outcome.actual_score)

    _apply_to_player(
        pa,
        won=outcome.actual_score == 1.0,
        drew=outcome.actual_score == 0.5,
        goals_for=goals_a,
        goals_against=goals_b,
        new_rating=elo.new_rating_a,
        game_date=game["Date"],
        game_id=int(game["id"]),
    )
    _apply_to_player(
        pb,
        won=outcome.actual_score == 0.0,
        drew=outcome.actual_score == 0.5,
        goals_for=goals_b,
        goals_against=goals_a,
        new_rating=elo.new_rating_b,
        game_date=game["Date"],
        game_id=int(game["id"]),
    )

    return {
        "id": int(game["id"]),
        "RatingA": elo.rating_a,
        "RatingB": elo.rating_b,
        "ExpectedScoreA": elo.expected_a,
        "ExpectedScoreB": elo.expected_b,
        "AdjustmentA": elo.adjustment_a,
        "AdjustmentB": elo.adjustment_b,
        "NewRatingA": elo.new_rating_a,
        "NewRatingB": elo.new_rating_b,
        "RatingDifference": elo.rating_difference,
        "ActualScore": outcome.actual_score,
        "WinnerID": outcome.winner_id,
        "SumOfGoals": outcome.sum_of_goals,
        "GoalDifference": outcome.goal_difference,
        "HomeWin": outcome.home_win,
        "Draw": outcome.draw,
        "AwayWin": outcome.away_win,
        "DDPlayerA": outcome.dd_player_a,
        "DDPlayerB": outcome.dd_player_b,
        "CSPlayerA": outcome.cs_player_a,
        "CSPlayerB": outcome.cs_player_b,
    }


RATEDRESULTS_UPDATE = """
    UPDATE ratedresults SET
        RatingA = %(RatingA)s,
        RatingB = %(RatingB)s,
        ExpectedScoreA = %(ExpectedScoreA)s,
        ExpectedScoreB = %(ExpectedScoreB)s,
        AdjustmentA = %(AdjustmentA)s,
        AdjustmentB = %(AdjustmentB)s,
        NewRatingA = %(NewRatingA)s,
        NewRatingB = %(NewRatingB)s,
        RatingDifference = %(RatingDifference)s,
        ActualScore = %(ActualScore)s,
        WinnerID = %(WinnerID)s,
        SumOfGoals = %(SumOfGoals)s,
        GoalDifference = %(GoalDifference)s,
        HomeWin = %(HomeWin)s,
        Draw = %(Draw)s,
        AwayWin = %(AwayWin)s,
        DDPlayerA = %(DDPlayerA)s,
        DDPlayerB = %(DDPlayerB)s,
        CSPlayerA = %(CSPlayerA)s,
        CSPlayerB = %(CSPlayerB)s
    WHERE id = %(id)s
"""

PLAYERTABLE_UPDATE = """
    UPDATE playertable SET
        Rating = %(Rating)s,
        NumberGames = %(NumberGames)s,
        NumberWins = %(NumberWins)s,
        NumberDraws = %(NumberDraws)s,
        NumberLosses = %(NumberLosses)s,
        WinRatio = %(WinRatio)s,
        DrawRatio = %(DrawRatio)s,
        LossRatio = %(LossRatio)s,
        GoalsFor = %(GoalsFor)s,
        GoalsAgainst = %(GoalsAgainst)s,
        LastGame = %(LastGame)s,
        LastGameGameID = %(LastGameGameID)s
    WHERE ID = %(ID)s
"""


def _player_row(player_id: int, state: PlayerState) -> dict[str, Any]:
    return {
        "ID": player_id,
        "Rating": state.rating,
        "NumberGames": state.games,
        "NumberWins": state.wins,
        "NumberDraws": state.draws,
        "NumberLosses": state.losses,
        "WinRatio": _ratio(state.wins, state.games),
        "DrawRatio": _ratio(state.draws, state.games),
        "LossRatio": _ratio(state.losses, state.games),
        "GoalsFor": state.goals_for,
        "GoalsAgainst": state.goals_against,
        "LastGame": state.last_game,
        "LastGameGameID": state.last_game_id,
    }


def replay_all(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    limit: int | None = None,
    batch_size: int = 500,
) -> None:
    players: dict[int, PlayerState] = {}

    with conn.cursor() as cur:
        cur.execute("SELECT ID FROM playertable")
        for row in cur.fetchall():
            players[int(row["ID"])] = PlayerState()

    with conn.cursor() as cur:
        cur.execute(GAME_SELECT)
        games = cur.fetchall()

    if limit is not None:
        games = games[:limit]

    log.info("replay_all: %s games, %s players in memory", len(games), len(players))

    if dry_run:
        if games:
            sample = apply_game_row(games[0], players)
            log.info("Dry-run sample game id=%s NewRatingA=%.3f NewRatingB=%.3f", sample["id"], sample["NewRatingA"], sample["NewRatingB"])
        if len(games) > 1:
            last_players = {pid: PlayerState() for pid in players}
            for g in games:
                apply_game_row(g, last_players)
            top = sorted(last_players.items(), key=lambda x: x[1].rating, reverse=True)[:5]
            log.info("Dry-run after full pass, top ratings: %s", [(i, round(s.rating, 2)) for i, s in top])
        return

    rated_batch: list[dict[str, Any]] = []
    processed = 0

    with conn.cursor() as cur:
        for game in games:
            row = apply_game_row(game, players)
            rated_batch.append(row)
            processed += 1

            if len(rated_batch) >= batch_size:
                cur.executemany(RATEDRESULTS_UPDATE, rated_batch)
                rated_batch.clear()
                if processed % 5000 == 0:
                    log.info("ratedresults: %s / %s games", processed, len(games))

        if rated_batch:
            cur.executemany(RATEDRESULTS_UPDATE, rated_batch)

        player_rows = [_player_row(pid, st) for pid, st in players.items() if st.games > 0]
        if player_rows:
            cur.executemany(PLAYERTABLE_UPDATE, player_rows)
        log.info("playertable updated: %s players with at least one game", len(player_rows))

    conn.commit()
    log.info("replay_all complete: %s games", processed)


def run_full(cfg: DbConfig, *, dry_run: bool, limit: int | None) -> None:
    conn = connect(cfg, dry_run=dry_run)
    try:
        reset_universe(conn, dry_run=dry_run)
        replay_all(conn, dry_run=dry_run, limit=limit)
    finally:
        conn.close()
