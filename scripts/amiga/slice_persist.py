"""Persist player tournament slice rows after tournament finalize."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.slice_columns import (
    SLICE_AT_EVENT_COLUMNS,
    SLICE_KEY_WORLD_CUP,
    SLICE_TOTALS_COLUMNS,
)
from scripts.amiga.slice_totals import empty_world_cup_slice

log = logging.getLogger(__name__)


def _upsert_sql(table: str, columns: tuple[str, ...], key_columns: tuple[str, ...]) -> str:
    col_list = ", ".join(f"`{c}`" for c in columns)
    val_list = ", ".join(f"%({c})s" for c in columns)
    key_set = set(key_columns)
    updates = ", ".join(f"`{c}`=VALUES(`{c}`)" for c in columns if c not in key_set)
    return (
        f"INSERT INTO `{table}` ({col_list}) VALUES ({val_list}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def _at_event_row(
    player_id: int,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    totals: dict[str, Any],
) -> dict[str, Any]:
    row: dict[str, Any] = {
        "player_id": player_id,
        "slice_key": SLICE_KEY_WORLD_CUP,
        "as_of_tournament_id": tournament_id,
        "event_date": event_date,
        "event_chrono": event_chrono,
    }
    for key in totals:
        if key == "slice_key":
            continue
        row[key] = totals[key]
    return row


def _totals_row(player_id: int, totals: dict[str, Any]) -> dict[str, Any]:
    row: dict[str, Any] = {
        "player_id": player_id,
        "slice_key": SLICE_KEY_WORLD_CUP,
    }
    for key, value in totals.items():
        if key == "slice_key":
            continue
        row[key] = value
    return row


def load_prior_world_cup_slices(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    player_ids: list[int],
) -> dict[int, dict[str, Any]]:
    """Latest world_cup slice strictly before ``tournament_id`` per player."""
    if not player_ids:
        return {}

    placeholders = ", ".join(["%s"] * len(player_ids))
    sql = f"""
        SELECT ranked.*
        FROM (
            SELECT s.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s.player_id
                       ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_player_slice_at_event s
            INNER JOIN tournaments tc ON tc.id = %s
            WHERE s.slice_key = %s
              AND s.player_id IN ({placeholders})
              AND (
                  s.event_date < tc.event_date
                  OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono)
                  OR (
                      s.event_date = tc.event_date
                      AND s.event_chrono = tc.chrono
                      AND s.as_of_tournament_id < tc.id
                  )
              )
        ) ranked
        WHERE ranked.rn = 1
    """
    with conn.cursor() as cur:
        cur.execute(sql, [tournament_id, SLICE_KEY_WORLD_CUP, *player_ids])
        rows = cur.fetchall()

    from scripts.amiga.slice_totals import slice_from_at_event_row

    return {int(row["player_id"]): slice_from_at_event_row(row) for row in rows}


def persist_world_cup_slices_at_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    slice_by_player: dict[int, dict[str, Any]],
    *,
    participant_ids: set[int],
) -> int:
    """
    Write ``amiga_player_slice_at_event`` for participants and upsert ``slice_totals``.

    ``slice_by_player`` must hold cumulative world_cup dicts for each participant after increment.
    """
    active_ids = sorted(
        pid
        for pid in participant_ids
        if pid in slice_by_player
        and int(slice_by_player[pid].get("tournaments_played") or 0) > 0
    )
    if not active_ids:
        return 0

    at_event_sql = _upsert_sql(
        "amiga_player_slice_at_event",
        SLICE_AT_EVENT_COLUMNS,
        key_columns=("player_id", "slice_key", "as_of_tournament_id"),
    )
    totals_sql = _upsert_sql(
        "amiga_player_slice_totals",
        SLICE_TOTALS_COLUMNS,
        key_columns=("player_id", "slice_key"),
    )

    at_rows: list[dict[str, Any]] = []
    total_rows: list[dict[str, Any]] = []
    for pid in active_ids:
        totals = slice_by_player[pid]
        at_rows.append(_at_event_row(pid, tournament_id, event_date, event_chrono, totals))
        total_rows.append(_totals_row(pid, totals))

    with conn.cursor() as cur:
        cur.executemany(at_event_sql, at_rows)
        cur.executemany(totals_sql, total_rows)
    conn.commit()

    log.info(
        "persist_world_cup_slices_at_tournament: tournament_id=%s rows=%s",
        tournament_id,
        len(at_rows),
    )
    return len(at_rows)


def carry_forward_world_cup_slice(totals: dict[str, Any] | None) -> dict[str, Any]:
    if totals is None:
        return empty_world_cup_slice()
    return dict(totals)
