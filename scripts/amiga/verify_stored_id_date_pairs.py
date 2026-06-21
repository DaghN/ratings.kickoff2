#!/usr/bin/env python3
"""Verify stored id/date pairing invariants (Phase C stored-field semantics)."""

from __future__ import annotations

import sys
from dataclasses import dataclass
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.generalstats_columns import RECORD_RISE_PLAYER_COLUMNS
from scripts.amiga.snapshot_row import career_best_performance_fields

_SAMPLE_LIMIT = 5

_HONOURS_LAST_COLUMNS = (
    ("honours_last_tournament_id", "honours_last_event_date"),
)


def _rise_id_date_pairs() -> list[tuple[str, str]]:
    pairs: list[tuple[str, str]] = []
    for col in RECORD_RISE_PLAYER_COLUMNS:
        if col.endswith("_last_rise_tournament_id"):
            pairs.append((col, col.replace("_last_rise_tournament_id", "_last_rise_event_date")))
    return pairs


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


def _check_rise_pairs(
    conn: pymysql.connections.Connection,
    *,
    table: str,
    errors: list[str],
) -> None:
    alias = "r"
    for tid_col, date_col in _rise_id_date_pairs():
        label = tid_col.removesuffix("_last_rise_tournament_id")

        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT COUNT(*) AS n
                FROM `{table}` {alias}
                WHERE ({alias}.`{tid_col}` IS NULL)
                   <> ({alias}.`{date_col}` IS NULL)
                """
            )
            null_asym = int(cur.fetchone()["n"])
        if null_asym:
            errors.append(
                f"{table} {label}: {null_asym} row(s) with rise id/date null asymmetry"
            )

        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT COUNT(*) AS n
                FROM `{table}` {alias}
                LEFT JOIN tournaments t ON t.id = {alias}.`{tid_col}`
                WHERE {alias}.`{tid_col}` IS NOT NULL AND t.id IS NULL
                """
            )
            orphan_tid = int(cur.fetchone()["n"])
        if orphan_tid:
            errors.append(
                f"{table} {label}: {orphan_tid} row(s) with rise tournament_id missing FK"
            )

        with conn.cursor() as cur:
            cur.execute(
                f"""
                SELECT COUNT(*) AS n
                FROM `{table}` {alias}
                INNER JOIN tournaments t ON t.id = {alias}.`{tid_col}`
                WHERE {alias}.`{tid_col}` IS NOT NULL
                  AND {alias}.`{date_col}` IS NOT NULL
                  AND {alias}.`{date_col}` <> t.event_date
                """
            )
            date_mismatch = int(cur.fetchone()["n"])
        if date_mismatch:
            errors.append(
                f"{table} {label}: {date_mismatch} row(s) with rise event_date "
                f"<> tournaments.event_date"
            )


def _check_honours_last_on_snapshots(
    conn: pymysql.connections.Connection,
    errors: list[str],
) -> None:
    alias = "r"
    tid_col, date_col = _HONOURS_LAST_COLUMNS[0]

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots {alias}
            WHERE ({alias}.`{tid_col}` IS NULL)
               <> ({alias}.`{date_col}` IS NULL)
            """
        )
        null_asym = int(cur.fetchone()["n"])
    if null_asym:
        errors.append(
            f"amiga_player_event_snapshots honours_last: {null_asym} row(s) id/date null asymmetry"
        )

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots {alias}
            LEFT JOIN tournaments t ON t.id = {alias}.`{tid_col}`
            WHERE {alias}.`{tid_col}` IS NOT NULL AND t.id IS NULL
            """
        )
        orphan = int(cur.fetchone()["n"])
    if orphan:
        errors.append(
            f"amiga_player_event_snapshots honours_last: {orphan} row(s) orphan tournament_id"
        )

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots {alias}
            WHERE {alias}.`{tid_col}` IS NOT NULL
              AND {alias}.`{tid_col}` <> {alias}.tournament_id
            """
        )
        tid_neq = int(cur.fetchone()["n"])
    if tid_neq:
        errors.append(
            f"amiga_player_event_snapshots honours_last: {tid_neq} row(s) "
            f"honours_last_tournament_id <> snapshot tournament_id"
        )

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots {alias}
            WHERE {alias}.`{date_col}` IS NOT NULL
              AND {alias}.`{date_col}` <> {alias}.event_date
            """
        )
        date_neq = int(cur.fetchone()["n"])
    if date_neq:
        errors.append(
            f"amiga_player_event_snapshots honours_last: {date_neq} row(s) "
            f"honours_last_event_date <> snapshot event_date"
        )


def _check_current_participation_last(
    conn: pymysql.connections.Connection,
    errors: list[str],
) -> None:
    """``last_*`` on current = last participation (not named honours_last on current)."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            WHERE (c.last_tournament_id IS NULL) <> (c.last_event_date IS NULL)
            """
        )
        null_asym = int(cur.fetchone()["n"])
    if null_asym:
        errors.append(
            f"amiga_player_current last participation: {null_asym} row(s) id/date null asymmetry"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            LEFT JOIN tournaments t ON t.id = c.last_tournament_id
            WHERE c.last_tournament_id IS NOT NULL AND t.id IS NULL
            """
        )
        orphan = int(cur.fetchone()["n"])
    if orphan:
        errors.append(
            f"amiga_player_current last participation: {orphan} row(s) orphan last_tournament_id"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            INNER JOIN tournaments t ON t.id = c.last_tournament_id
            WHERE c.last_event_date IS NOT NULL
              AND c.last_event_date <> t.event_date
            """
        )
        date_mismatch = int(cur.fetchone()["n"])
    if date_mismatch:
        errors.append(
            f"amiga_player_current last participation: {date_mismatch} row(s) "
            f"last_event_date <> tournaments.event_date"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            INNER JOIN (
                SELECT s.player_id, s.tournament_id, s.event_date,
                       ROW_NUMBER() OVER (
                           PARTITION BY s.player_id
                           ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
                       ) AS rn
                FROM amiga_player_event_snapshots s
            ) latest ON latest.player_id = c.player_id AND latest.rn = 1
            WHERE c.last_tournament_id <> latest.tournament_id
               OR c.last_event_date <> latest.event_date
            """
        )
        latest_mismatch = int(cur.fetchone()["n"])
    if latest_mismatch:
        errors.append(
            f"amiga_player_current last participation: {latest_mismatch} row(s) "
            f"<> latest snapshot event"
        )


@dataclass
class _CareerBestState:
    rating: float | None
    tournament_id: int | None
    games_at_tournament: int


def _empty_career_best() -> _CareerBestState:
    return _CareerBestState(None, None, 0)


def advance_career_best_state(
    state: _CareerBestState,
    row: dict[str, Any],
) -> _CareerBestState:
    """Replay one finalized snapshot row (mirrors snapshot persist carry)."""
    tid = int(row["tournament_id"])
    games = int(row.get("games_in_event") or 0)
    perf_raw = row.get("performance_rating")
    perf = float(perf_raw) if perf_raw is not None else None

    best_r, best_tid = career_best_performance_fields(
        performance_rating=perf,
        tournament_id=tid,
        games=games,
        prior_rating=state.rating,
        prior_tournament_id=state.tournament_id,
        prior_games=state.games_at_tournament,
    )
    if best_tid is None:
        return _empty_career_best()
    if state.tournament_id is not None and int(best_tid) == int(state.tournament_id):
        games_at = state.games_at_tournament
    else:
        games_at = games
    return _CareerBestState(best_r, int(best_tid), games_at)


def _check_career_best_performance(
    conn: pymysql.connections.Connection,
    errors: list[str],
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.player_id, s.tournament_id, s.performance_rating, s.games_in_event,
                   s.career_best_performance_rating, s.career_best_performance_tournament_id,
                   t.event_date, t.chrono
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 1
            ORDER BY t.event_date ASC, t.chrono ASC, s.tournament_id ASC, s.player_id ASC
            """
        )
        rows = cur.fetchall()

    state_by_player: dict[int, _CareerBestState] = {}
    snapshot_mismatch = 0
    first_snapshot_mismatch: tuple[int, int] | None = None

    for row in rows:
        pid = int(row["player_id"])
        state = state_by_player.get(pid, _empty_career_best())
        state = advance_career_best_state(state, row)
        state_by_player[pid] = state

        stored_rating = row.get("career_best_performance_rating")
        stored_tid = row.get("career_best_performance_tournament_id")
        rating_ok = (
            stored_rating is None and state.rating is None
        ) or (
            stored_rating is not None
            and state.rating is not None
            and abs(float(stored_rating) - float(state.rating)) < 1e-5
        )
        tid_ok = (stored_tid is None and state.tournament_id is None) or (
            stored_tid is not None
            and state.tournament_id is not None
            and int(stored_tid) == int(state.tournament_id)
        )
        if not rating_ok or not tid_ok:
            snapshot_mismatch += 1
            if first_snapshot_mismatch is None:
                first_snapshot_mismatch = (pid, int(row["tournament_id"]))

    if snapshot_mismatch:
        errors.append(
            f"career_best snapshot replay: {snapshot_mismatch} row(s) mismatch "
            f"(first player_id={first_snapshot_mismatch[0] if first_snapshot_mismatch else '?'} "
            f"tournament_id={first_snapshot_mismatch[1] if first_snapshot_mismatch else '?'})"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, career_best_performance_rating,
                   career_best_performance_tournament_id
            FROM amiga_player_current
            """
        )
        current_rows = cur.fetchall()

    current_mismatch = 0
    for row in current_rows:
        pid = int(row["player_id"])
        state = state_by_player.get(pid, _empty_career_best())
        stored_rating = row.get("career_best_performance_rating")
        stored_tid = row.get("career_best_performance_tournament_id")
        rating_ok = (
            stored_rating is None and state.rating is None
        ) or (
            stored_rating is not None
            and state.rating is not None
            and abs(float(stored_rating) - float(state.rating)) < 1e-5
        )
        tid_ok = (stored_tid is None and state.tournament_id is None) or (
            stored_tid is not None
            and state.tournament_id is not None
            and int(stored_tid) == int(state.tournament_id)
        )
        if not rating_ok or not tid_ok:
            current_mismatch += 1

    if current_mismatch:
        errors.append(
            f"career_best current vs replay: {current_mismatch} player(s) mismatch"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            LEFT JOIN tournaments t ON t.id = c.career_best_performance_tournament_id
            WHERE c.career_best_performance_tournament_id IS NOT NULL AND t.id IS NULL
            """
        )
        orphan_tid = int(cur.fetchone()["n"])
    if orphan_tid:
        errors.append(
            f"career_best: {orphan_tid} current row(s) with orphan tournament_id"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            LEFT JOIN amiga_player_event_snapshots s
              ON s.player_id = c.player_id
             AND s.tournament_id = c.career_best_performance_tournament_id
            WHERE c.career_best_performance_tournament_id IS NOT NULL
              AND s.player_id IS NULL
            """
        )
        missing_snap = int(cur.fetchone()["n"])
    if missing_snap:
        errors.append(
            f"career_best: {missing_snap} current row(s) with no matching snapshot"
        )

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_current c
            INNER JOIN amiga_player_event_snapshots s
              ON s.player_id = c.player_id
             AND s.tournament_id = c.career_best_performance_tournament_id
            WHERE c.career_best_performance_tournament_id IS NOT NULL
              AND (s.games_in_event IS NULL OR s.games_in_event < 2)
            """
        )
        low_games = int(cur.fetchone()["n"])
    if low_games:
        errors.append(
            f"career_best: {low_games} current row(s) best tournament has games_in_event < 2"
        )


def verify_stored_id_date_pairs(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    _check_rise_pairs(conn, table="amiga_player_current", errors=errors)
    _check_rise_pairs(conn, table="amiga_player_event_snapshots", errors=errors)
    _check_honours_last_on_snapshots(conn, errors)
    _check_current_participation_last(conn, errors)
    _check_career_best_performance(conn, errors)
    return errors


def main() -> int:
    conn = _connect()
    try:
        errors = verify_stored_id_date_pairs(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-stored-id-date-pairs issue(s):", file=sys.stderr)
        for err in errors[:_SAMPLE_LIMIT]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > _SAMPLE_LIMIT:
            print(f"  ... and {len(errors) - _SAMPLE_LIMIT} more", file=sys.stderr)
        return 1

    print("OK: verify-stored-id-date-pairs")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
