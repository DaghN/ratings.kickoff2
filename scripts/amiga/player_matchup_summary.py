"""Derived directed head-to-head totals from amiga_games."""

from __future__ import annotations

import logging

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

log = logging.getLogger(__name__)

_REBUILD_INSERT_SQL = """
INSERT INTO amiga_player_matchup_summary (
    player_id,
    opponent_id,
    games,
    wins,
    draws,
    losses,
    goals_for,
    goals_against
)
SELECT
    pid,
    oid,
    COUNT(*) AS games,
    SUM(w) AS wins,
    SUM(d) AS draws,
    SUM(l) AS losses,
    SUM(gf) AS goals_for,
    SUM(ga) AS goals_against
FROM (
    SELECT
        g.player_a_id AS pid,
        g.player_b_id AS oid,
        CASE WHEN g.goals_a > g.goals_b THEN 1 ELSE 0 END AS w,
        CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END AS d,
        CASE WHEN g.goals_a < g.goals_b THEN 1 ELSE 0 END AS l,
        g.goals_a AS gf,
        g.goals_b AS ga
    FROM amiga_games g
    UNION ALL
    SELECT
        g.player_b_id AS pid,
        g.player_a_id AS oid,
        CASE WHEN g.goals_b > g.goals_a THEN 1 ELSE 0 END AS w,
        CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END AS d,
        CASE WHEN g.goals_b < g.goals_a THEN 1 ELSE 0 END AS l,
        g.goals_b AS gf,
        g.goals_a AS ga
    FROM amiga_games g
) AS sides
GROUP BY pid, oid
"""


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def clear_matchup_summary(conn: pymysql.connections.Connection, *, dry_run: bool = False) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
        n = int(cur.fetchone()["n"])
    log.info("clear_matchup_summary: %s existing rows", n)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_player_matchup_summary")
    conn.commit()


def rebuild_all_matchup_summary(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> int:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        game_count = int(cur.fetchone()["n"])
    log.info("rebuild_all_matchup_summary: %s games", game_count)
    if dry_run:
        return game_count

    clear_matchup_summary(conn, dry_run=False)
    with conn.cursor() as cur:
        cur.execute(_REBUILD_INSERT_SQL)
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
        written = int(cur.fetchone()["n"])
        cur.execute("SELECT COALESCE(SUM(games), 0) AS n FROM amiga_player_matchup_summary")
        games_sum = int(cur.fetchone()["n"])
    conn.commit()
    expected = game_count * 2
    if games_sum != expected:
        raise RuntimeError(
            f"matchup parity failed: SUM(games)={games_sum} expected {expected} (2 × {game_count})"
        )
    log.info("amiga_player_matchup_summary: %s rows, SUM(games)=%s", written, games_sum)
    return written


def run_matchup_rebuild(*, dry_run: bool = False) -> int:
    conn = _connect()
    try:
        return rebuild_all_matchup_summary(conn, dry_run=dry_run)
    finally:
        conn.close()
