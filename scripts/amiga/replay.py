"""Chronological Elo replay for Amiga ground-truth games → derived tables."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_standings import rebuild_all_standings
from scripts.ladder.engine import apply_game_row
from scripts.ladder.finalize_counts import finalize_network_counts_from_rows
from scripts.ladder.player_state import PlayerState

log = logging.getLogger(__name__)

# Contract chronology: materialized at import — game_date ASC, id ASC (see amiga-data-contract.md).
GAME_SELECT = """
    SELECT g.id, g.game_date AS Date, g.player_a_id AS idA, g.player_b_id AS idB,
           g.goals_a AS GoalsA, g.goals_b AS GoalsB
    FROM amiga_games g
    ORDER BY g.game_date ASC, g.id ASC
"""


def _connect(cfg) -> pymysql.connections.Connection:
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing replay: expected ko2amiga_db, got {cfg.database!r}")
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
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _rating_insert_sql() -> str:
    return """
        INSERT INTO amiga_game_ratings (
            game_id, rating_a, rating_b, rating_difference,
            expected_score_a, expected_score_b, actual_score,
            adjustment_a, adjustment_b, new_rating_a, new_rating_b,
            sum_of_goals, goal_difference, winner_id,
            home_win, draw, away_win,
            dd_player_a, dd_player_b, cs_player_a, cs_player_b
        ) VALUES (
            %(game_id)s, %(rating_a)s, %(rating_b)s, %(rating_difference)s,
            %(expected_score_a)s, %(expected_score_b)s, %(actual_score)s,
            %(adjustment_a)s, %(adjustment_b)s, %(new_rating_a)s, %(new_rating_b)s,
            %(sum_of_goals)s, %(goal_difference)s, %(winner_id)s,
            %(home_win)s, %(draw)s, %(away_win)s,
            %(dd_player_a)s, %(dd_player_b)s, %(cs_player_a)s, %(cs_player_b)s
        )
    """


def _stats_insert_sql() -> str:
    sample = PlayerState().to_db_row(1)
    cols = [k for k in sample if k != "ID"]
    col_list = ", ".join(f"`{c}`" for c in cols)
    val_list = ", ".join(f"%({c})s" for c in cols)
    return (
        f"INSERT INTO amiga_player_stats (player_id, {col_list}) "
        f"VALUES (%(player_id)s, {val_list})"
    )


def _row_to_rating_insert(game_id: int, row: dict[str, Any]) -> dict[str, Any]:
    return {
        "game_id": game_id,
        "rating_a": row["RatingA"],
        "rating_b": row["RatingB"],
        "rating_difference": row["RatingDifference"],
        "expected_score_a": row["ExpectedScoreA"],
        "expected_score_b": row["ExpectedScoreB"],
        "actual_score": row["ActualScore"],
        "adjustment_a": row["AdjustmentA"],
        "adjustment_b": row["AdjustmentB"],
        "new_rating_a": row["NewRatingA"],
        "new_rating_b": row["NewRatingB"],
        "sum_of_goals": row["SumOfGoals"],
        "goal_difference": row["GoalDifference"],
        "winner_id": row["WinnerID"],
        "home_win": row["HomeWin"],
        "draw": row["Draw"],
        "away_win": row["AwayWin"],
        "dd_player_a": row["DDPlayerA"],
        "dd_player_b": row["DDPlayerB"],
        "cs_player_a": row["CSPlayerA"],
        "cs_player_b": row["CSPlayerB"],
    }


def _stats_row(player_id: int, st: PlayerState) -> dict[str, Any]:
    row = st.to_db_row(player_id)
    row["player_id"] = player_id
    del row["ID"]
    return row


def clear_derived(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = cur.fetchone()["n"]
        cur.execute("SELECT COUNT(*) AS n FROM amiga_players")
        players = cur.fetchone()["n"]
    log.info("clear_derived: amiga_games=%s, amiga_players=%s", games, players)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_tournament_standings")
        cur.execute("DELETE FROM amiga_game_ratings")
        cur.execute("DELETE FROM amiga_player_stats")
    conn.commit()


def replay_all(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
    limit: int | None = None,
    batch_size: int = 500,
) -> None:
    players: dict[int, PlayerState] = {}
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM amiga_players")
        names = {int(row["id"]): str(row["name"]) for row in cur.fetchall()}
        for pid in names:
            players[pid] = PlayerState()

    with conn.cursor() as cur:
        cur.execute(GAME_SELECT)
        games = cur.fetchall()

    if limit is not None:
        games = games[:limit]

    log.info("replay_all: %s games, %s players in memory", len(games), len(players))

    if dry_run:
        if games:
            sample = apply_game_row(games[0], players, names=names)
            log.info(
                "Dry-run sample game id=%s NewRatingA=%.3f NewRatingB=%.3f",
                games[0]["id"],
                sample["NewRatingA"],
                sample["NewRatingB"],
            )
        return

    rating_sql = _rating_insert_sql()
    stats_sql = _stats_insert_sql()
    rating_batch: list[dict[str, Any]] = []
    all_rows: list[dict[str, Any]] = []
    processed = 0

    with conn.cursor() as cur:
        for game in games:
            game_id = int(game["id"])
            row = apply_game_row(game, players, names=names)
            rating_batch.append(_row_to_rating_insert(game_id, row))
            all_rows.append(row)
            processed += 1

            if len(rating_batch) >= batch_size:
                cur.executemany(rating_sql, rating_batch)
                rating_batch.clear()
                if processed % 5000 == 0:
                    log.info("amiga_game_ratings: %s / %s games", processed, len(games))

        if rating_batch:
            cur.executemany(rating_sql, rating_batch)

    log.info("amiga_game_ratings done; finalizing player stats")
    finalize_network_counts_from_rows(players, all_rows)

    stat_rows = [_stats_row(pid, st) for pid, st in players.items() if st.games > 0]
    with conn.cursor() as cur:
        if stat_rows:
            cur.executemany(stats_sql, stat_rows)
        log.info("amiga_player_stats: %s players with at least one game", len(stat_rows))

    log.info("rebuilding tournament standings")
    rebuild_all_standings(conn, dry_run=False)

    conn.commit()
    log.info("replay_all complete: %s games", processed)


def run_replay(*, dry_run: bool = False, limit: int | None = None) -> None:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        clear_derived(conn, dry_run=dry_run)
        replay_all(conn, dry_run=dry_run, limit=limit)
    finally:
        conn.close()
