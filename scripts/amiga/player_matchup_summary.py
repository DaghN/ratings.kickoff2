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
    goals_against,
    max_goals_for,
    max_goals_against,
    min_goals_for,
    min_goals_against,
    max_win_margin,
    max_loss_margin,
    max_draw_goals,
    max_goal_sum,
    min_goal_sum,
    dd_wins,
    dd_losses,
    cs_wins,
    cs_losses
)
SELECT
    pid,
    oid,
    COUNT(*) AS games,
    SUM(w) AS wins,
    SUM(d) AS draws,
    SUM(l) AS losses,
    SUM(gf) AS goals_for,
    SUM(ga) AS goals_against,
    MAX(gf) AS max_goals_for,
    MAX(ga) AS max_goals_against,
    MIN(gf) AS min_goals_for,
    MIN(ga) AS min_goals_against,
    MAX(CASE WHEN w > 0 THEN gf - ga END) AS max_win_margin,
    MAX(CASE WHEN l > 0 THEN ga - gf END) AS max_loss_margin,
    MAX(CASE WHEN d > 0 THEN gf END) AS max_draw_goals,
    MAX(gf + ga) AS max_goal_sum,
    MIN(gf + ga) AS min_goal_sum,
    SUM(dd_w) AS dd_wins,
    SUM(dd_l) AS dd_losses,
    SUM(cs_w) AS cs_wins,
    SUM(cs_l) AS cs_losses
FROM (
    SELECT
        g.player_a_id AS pid,
        g.player_b_id AS oid,
        CASE WHEN g.goals_a > g.goals_b THEN 1 ELSE 0 END AS w,
        CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END AS d,
        CASE WHEN g.goals_a < g.goals_b THEN 1 ELSE 0 END AS l,
        g.goals_a AS gf,
        g.goals_b AS ga,
        CASE WHEN g.goals_a >= 10 THEN 1 ELSE 0 END AS dd_w,
        CASE WHEN g.goals_b >= 10 THEN 1 ELSE 0 END AS dd_l,
        CASE WHEN g.goals_b = 0 THEN 1 ELSE 0 END AS cs_w,
        CASE WHEN g.goals_a = 0 THEN 1 ELSE 0 END AS cs_l
    FROM amiga_games g
    UNION ALL
    SELECT
        g.player_b_id AS pid,
        g.player_a_id AS oid,
        CASE WHEN g.goals_b > g.goals_a THEN 1 ELSE 0 END AS w,
        CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END AS d,
        CASE WHEN g.goals_b < g.goals_a THEN 1 ELSE 0 END AS l,
        g.goals_b AS gf,
        g.goals_a AS ga,
        CASE WHEN g.goals_b >= 10 THEN 1 ELSE 0 END AS dd_w,
        CASE WHEN g.goals_a >= 10 THEN 1 ELSE 0 END AS dd_l,
        CASE WHEN g.goals_a = 0 THEN 1 ELSE 0 END AS cs_w,
        CASE WHEN g.goals_b = 0 THEN 1 ELSE 0 END AS cs_l
    FROM amiga_games g
) AS sides
GROUP BY pid, oid
"""

_PAIR_EXTREMES_ORACLE_SQL = """
SELECT
    MAX(gf) AS max_goals_for,
    MAX(ga) AS max_goals_against,
    MIN(gf) AS min_goals_for,
    MIN(ga) AS min_goals_against,
    MAX(CASE WHEN w > 0 THEN gf - ga END) AS max_win_margin,
    MAX(CASE WHEN l > 0 THEN ga - gf END) AS max_loss_margin,
    MAX(CASE WHEN d > 0 THEN gf END) AS max_draw_goals,
    MAX(gf + ga) AS max_goal_sum,
    MIN(gf + ga) AS min_goal_sum
FROM (
    SELECT
        CASE WHEN g.player_a_id = %(player_id)s AND g.player_b_id = %(opponent_id)s THEN g.goals_a
             WHEN g.player_b_id = %(player_id)s AND g.player_a_id = %(opponent_id)s THEN g.goals_b
        END AS gf,
        CASE WHEN g.player_a_id = %(player_id)s AND g.player_b_id = %(opponent_id)s THEN g.goals_b
             WHEN g.player_b_id = %(player_id)s AND g.player_a_id = %(opponent_id)s THEN g.goals_a
        END AS ga,
        CASE
            WHEN g.player_a_id = %(player_id)s AND g.player_b_id = %(opponent_id)s AND g.goals_a > g.goals_b THEN 1
            WHEN g.player_b_id = %(player_id)s AND g.player_a_id = %(opponent_id)s AND g.goals_b > g.goals_a THEN 1
            ELSE 0
        END AS w,
        CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END AS d,
        CASE
            WHEN g.player_a_id = %(player_id)s AND g.player_b_id = %(opponent_id)s AND g.goals_a < g.goals_b THEN 1
            WHEN g.player_b_id = %(player_id)s AND g.player_a_id = %(opponent_id)s AND g.goals_b < g.goals_a THEN 1
            ELSE 0
        END AS l
    FROM amiga_games g
    WHERE (g.player_a_id = %(player_id)s AND g.player_b_id = %(opponent_id)s)
       OR (g.player_b_id = %(player_id)s AND g.player_a_id = %(opponent_id)s)
) AS directed
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
