"""Rebuild player_milestones after replay (processed ratedresults + simulators)."""

from __future__ import annotations

import logging
import re
from datetime import date, datetime, timedelta
from pathlib import Path

import pymysql

from .milestone_sim import (
    MilestoneRow,
    simulate_appearance_milestones,
    simulate_chrono_milestones,
    simulate_club_milestones,
    simulate_period_burst_milestones,
    simulate_play_streak_100_milestones,
    simulate_streak_milestones,
    simulate_tail_milestones,
    simulate_year_in_heaven_milestones,
)

log = logging.getLogger(__name__)

_SQL_DIR = Path(__file__).resolve().parents[2] / "docs" / "archive" / "batch-rebuild-sql-2026-05"
# Unaliased ratedresults scans only (exists SQL); skip `FROM ratedresults r` in period rebuild.
_RR_FROM = re.compile(r"FROM\s+`?ratedresults`?(?=\s*(?:\r?\n|$))", re.IGNORECASE)
_EXISTS_PATH = _SQL_DIR / "player_milestones_rebuild_exists.sql"
_PERIOD_PATH = _SQL_DIR / "player_milestones_rebuild_period.sql"
_LEAGUE_MARKER = "-- League wave:"
_LOBBY_MARKER = "-- entered_arena:"


def _table_exists(conn: pymysql.connections.Connection, table: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = %s",
            (table,),
        )
        return int(cur.fetchone()["n"]) > 0


def _strip_line_comments(sql: str) -> str:
    lines: list[str] = []
    for line in sql.splitlines():
        if line.strip().startswith("--"):
            continue
        lines.append(line)
    return "\n".join(lines)


def _run_sql_text(conn: pymysql.connections.Connection, sql: str, *, processed_only: bool) -> None:
    if processed_only:
        sql = _RR_FROM.sub("FROM `ratedresults` WHERE `NewRatingA` IS NOT NULL", sql)
    statements = [s.strip() for s in sql.split(";") if s.strip()]
    with conn.cursor() as cur:
        for stmt in statements:
            if stmt.upper().startswith("SET "):
                cur.execute(stmt)
                continue
            cur.execute(stmt)


def _run_sql_file(conn: pymysql.connections.Connection, path: Path, *, processed_only: bool) -> None:
    _run_sql_text(conn, _strip_line_comments(path.read_text(encoding="utf-8")), processed_only=processed_only)


def _league_lobby_sql() -> tuple[str, str]:
    text = (_SQL_DIR / "player_milestones_rebuild.sql").read_text(encoding="utf-8")
    league_idx = text.find(_LEAGUE_MARKER)
    lobby_idx = text.find(_LOBBY_MARKER)
    if league_idx < 0 or lobby_idx < 0:
        raise SystemExit("League/lobby markers missing in player_milestones_rebuild.sql")
    return text[league_idx:lobby_idx], text[lobby_idx:]


def _insert_rows(conn: pymysql.connections.Connection, rows: list[MilestoneRow]) -> None:
    if not rows:
        return
    sql = (
        "INSERT INTO `player_milestones` "
        "(`player_id`, `milestone_key`, `achieved_at`, `value`, "
        "`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) "
        "VALUES (%s, %s, %s, %s, 'game', %s, NULL, NULL, NULL)"
    )
    with conn.cursor() as cur:
        for pid, key, achieved_at, val, gid in rows:
            cur.execute(
                "SELECT 1 FROM player_milestones WHERE player_id = %s AND milestone_key = %s LIMIT 1",
                (pid, key),
            )
            if cur.fetchone():
                continue
            cur.execute(sql, (pid, key, achieved_at, val, gid))


def _fetch_processed_games(conn: pymysql.connections.Connection) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
        cur.execute(
            """
            SELECT id, `Date`, idA, idB, GoalsA, GoalsB, ActualScore,
                   RatingA, RatingB, NewRatingA, NewRatingB
            FROM ratedresults
            WHERE NewRatingA IS NOT NULL
            ORDER BY `Date` ASC, id ASC
            """
        )
        return list(cur.fetchall())


def _eligible_players(conn: pymysql.connections.Connection) -> set[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT ID FROM playertable WHERE NumberGames >= 1")
        return {int(r["ID"]) for r in cur.fetchall()}


def _simulate_play_streak_100(conn: pymysql.connections.Connection) -> list[MilestoneRow]:
    if not _table_exists(conn, "player_period_games"):
        return []
    threshold = 100
    rows: list[MilestoneRow] = []
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
        cur.execute(
            """
            SELECT player_id, period_start
            FROM player_period_games
            WHERE period_type = 'day'
            ORDER BY player_id ASC, period_start ASC
            """
        )
        current_pid: int | None = None
        days: list[date] = []

        def flush(pid: int) -> None:
            if not days:
                return
            run = [days[0]]
            for i in range(1, len(days)):
                if days[i] == run[-1] + timedelta(days=1):
                    run.append(days[i])
                    if len(run) == threshold:
                        est = _establishing_game(cur, pid, run[-1])
                        if est:
                            rows.append((pid, "play_streak_100", est[1], threshold, est[0]))
                        return
                else:
                    run = [days[i]]
                    if len(run) == threshold:
                        est = _establishing_game(cur, pid, run[-1])
                        if est:
                            rows.append((pid, "play_streak_100", est[1], threshold, est[0]))
                        return

        for row in cur.fetchall():
            pid = int(row["player_id"])
            ps = row["period_start"]
            day = ps.date() if isinstance(ps, datetime) else date.fromisoformat(str(ps)[:10])
            if current_pid is None:
                current_pid = pid
            if pid != current_pid:
                flush(current_pid)
                days = []
                current_pid = pid
            days.append(day)
        if current_pid is not None:
            flush(current_pid)
    return rows


def _establishing_game(cur, player_id: int, day: date) -> tuple[int, datetime] | None:
    cur.execute(
        """
        SELECT MIN(id) AS game_id FROM (
          SELECT id FROM ratedresults
          WHERE idA = %s AND DATE(`Date`) = %s AND NewRatingA IS NOT NULL
          UNION ALL
          SELECT id FROM ratedresults
          WHERE idB = %s AND DATE(`Date`) = %s AND NewRatingA IS NOT NULL
        ) AS g
        """,
        (player_id, day, player_id, day),
    )
    row = cur.fetchone()
    if not row or row["game_id"] is None:
        return None
    gid = int(row["game_id"])
    cur.execute("SELECT `Date` FROM ratedresults WHERE id = %s", (gid,))
    dt_row = cur.fetchone()
    if not dt_row or not isinstance(dt_row["Date"], datetime):
        return None
    return gid, dt_row["Date"]


def rebuild_milestones_if_present(conn: pymysql.connections.Connection) -> None:
    if not _table_exists(conn, "player_milestones"):
        log.info("player_milestones missing — skip")
        return

    league, lobby = _league_lobby_sql()
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
        cur.execute("TRUNCATE TABLE `player_milestones`")

    games = _fetch_processed_games(conn)
    eligible = _eligible_players(conn)
    sim_rows: list[MilestoneRow] = []
    sim_rows.extend(simulate_appearance_milestones(games, eligible))
    sim_rows.extend(simulate_club_milestones(games, eligible))
    _run_sql_file(conn, _EXISTS_PATH, processed_only=True)
    sim_rows.extend(simulate_streak_milestones(games, eligible))
    sim_rows.extend(simulate_chrono_milestones(games))
    sim_rows.extend(simulate_tail_milestones(games, eligible))
    sim_rows.extend(simulate_period_burst_milestones(games, eligible))
    sim_rows.extend(simulate_year_in_heaven_milestones(games, eligible))
    sim_rows.extend(simulate_play_streak_100_milestones(games, eligible))
    _insert_rows(conn, sim_rows)
    _run_sql_text(conn, _strip_line_comments(league), processed_only=False)
    _run_sql_text(conn, _strip_line_comments(lobby), processed_only=False)

    log.info(
        "player_milestones rebuilt (%s processed games, %s sim rows inserted)",
        len(games),
        len(sim_rows),
    )
