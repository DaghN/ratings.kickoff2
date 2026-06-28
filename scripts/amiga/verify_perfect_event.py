#!/usr/bin/env python3
"""Verify perfect event stored truth (policy: docs/amiga-perfect-event-policy.md)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.honours_totals import empty_honours_totals, increment_honours_totals
from scripts.amiga.realm_incremental import _career_holders_from_player_rows
from scripts.amiga.server_records import _load_cutoff_player_rows
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id, load_realm_cutoff


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
        autocommit=True,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def _verify_is_perfect_event_flags(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    sql = """
        SELECT s.player_id, s.tournament_id, s.games, s.wins, s.draws, s.losses, s.is_perfect_event
        FROM amiga_player_event_snapshots s
        INNER JOIN tournaments t ON t.id = s.tournament_id
        WHERE t.rating_finalized = 1
          AND (
            s.is_perfect_event <> CASE
                WHEN s.games >= 2 AND s.losses = 0 AND s.draws = 0 AND s.wins = s.games THEN 1
                ELSE 0
            END
          )
        LIMIT 20
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        bad = cur.fetchall()
    if bad:
        errors.append(f"is_perfect_event mismatch: {len(bad)} row(s) (showing up to 20)")
    return errors


def _oracle_honours_by_player(conn: pymysql.connections.Connection) -> dict[int, dict]:
    totals_by_player: dict[int, dict] = {}
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.player_id, s.tournament_id, s.event_date, s.tournament_name,
                   s.event_finish_position, s.is_winner, s.is_perfect_event
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 1
            ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, s.player_id ASC
            """
        )
        rows = cur.fetchall()
    for row in rows:
        pid = int(row["player_id"])
        if pid not in totals_by_player:
            totals_by_player[pid] = empty_honours_totals()
        increment_honours_totals(totals_by_player[pid], row)
    return totals_by_player


def _verify_perfect_events_counts(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    oracle = _oracle_honours_by_player(conn)
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, perfect_events,
                   perfect_events_last_rise_tournament_id,
                   perfect_events_last_rise_event_date
            FROM amiga_player_current
            WHERE perfect_events > 0 OR player_id IN (
                SELECT player_id FROM amiga_player_event_snapshots WHERE is_perfect_event = 1
            )
            """
        )
        rows = cur.fetchall()
    for row in rows:
        pid = int(row["player_id"])
        expected = oracle.get(pid, empty_honours_totals())
        if int(row["perfect_events"] or 0) != int(expected["perfect_events"]):
            errors.append(
                f"player {pid}: perfect_events stored={row['perfect_events']} "
                f"oracle={expected['perfect_events']}"
            )
            continue
        if int(expected["perfect_events"]) > 0:
            if int(row["perfect_events_last_rise_tournament_id"] or 0) != int(
                expected["perfect_events_last_rise_tournament_id"] or 0
            ):
                errors.append(f"player {pid}: perfect_events_last_rise_tournament_id mismatch")
            exp_date = expected["perfect_events_last_rise_event_date"]
            got_date = row["perfect_events_last_rise_event_date"]
            if str(got_date)[:10] != str(exp_date)[:10]:
                errors.append(f"player {pid}: perfect_events_last_rise_event_date mismatch")
    return errors[:20]


def _verify_catalog_flags(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT c.tournament_id, c.has_perfect_participant,
                   COALESCE(pe.flag, 0) AS oracle_flag
            FROM amiga_tournament_catalog_stats c
            LEFT JOIN (
                SELECT tournament_id, MAX(is_perfect_event) AS flag
                FROM amiga_player_event_snapshots
                GROUP BY tournament_id
            ) pe ON pe.tournament_id = c.tournament_id
            WHERE c.has_perfect_participant <> COALESCE(pe.flag, 0)
            LIMIT 20
            """
        )
        bad = cur.fetchall()
    if bad:
        errors.append(f"has_perfect_participant mismatch: {len(bad)} tournament(s)")
    return errors


def _verify_hof_holder(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    latest_tid = latest_finalized_tournament_id(conn)
    if latest_tid is None:
        return errors
    cutoff = load_realm_cutoff(conn, latest_tid)
    player_rows = _load_cutoff_player_rows(conn, cutoff)
    holder_patch = _career_holders_from_player_rows(player_rows)
    prefix = "MostPerfectEvents"
    with conn.cursor() as cur:
        cur.execute("SELECT * FROM amiga_generalstats WHERE id = 1 LIMIT 1")
        gst = cur.fetchone()
    if not gst:
        return ["generalstats id=1 missing"]
    if int(gst.get(prefix) or 0) != int(holder_patch.get(prefix) or 0):
        errors.append(
            f"{prefix} value gst={gst.get(prefix)} expected={holder_patch.get(prefix)}"
        )
    if int(gst.get(f"{prefix}ID") or 0) != int(holder_patch.get(f"{prefix}ID") or 0):
        errors.append(f"{prefix}ID mismatch")
    exp_date = holder_patch.get(f"{prefix}Date")
    got_date = gst.get(f"{prefix}Date")
    if exp_date and str(got_date)[:10] != str(exp_date)[:10]:
        errors.append(f"{prefix}Date mismatch gst={got_date!r} expected={exp_date!r}")
    return errors


def main() -> int:
    conn = _connect()
    errors: list[str] = []
    try:
        errors.extend(_verify_is_perfect_event_flags(conn))
        errors.extend(_verify_perfect_events_counts(conn))
        errors.extend(_verify_catalog_flags(conn))
        errors.extend(_verify_hof_holder(conn))
    finally:
        conn.close()

    if errors:
        print("verify-perfect-event FAIL:")
        for err in errors:
            print(f"  - {err}")
        return 1

    print("verify-perfect-event OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())