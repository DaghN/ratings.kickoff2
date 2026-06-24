"""Persist World Cup country slice rows after tournament finalize."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.country_slice_columns import (
    COUNTRY_SLICE_AT_EVENT_COLUMNS,
    COUNTRY_SLICE_STAT_COLUMNS,
    COUNTRY_SLICE_TOTALS_COLUMNS,
    SLICE_KEY_WORLD_CUP,
)

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
    country_token: str,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    totals: dict[str, Any],
) -> dict[str, Any]:
    row: dict[str, Any] = {
        "country_token": country_token,
        "slice_key": SLICE_KEY_WORLD_CUP,
        "as_of_tournament_id": tournament_id,
        "event_date": event_date,
        "event_chrono": event_chrono,
    }
    for key in COUNTRY_SLICE_STAT_COLUMNS:
        row[key] = totals.get(key)
    return row


def _totals_row(country_token: str, totals: dict[str, Any]) -> dict[str, Any]:
    row: dict[str, Any] = {
        "country_token": country_token,
        "slice_key": SLICE_KEY_WORLD_CUP,
    }
    for key in COUNTRY_SLICE_STAT_COLUMNS:
        row[key] = totals.get(key)
    return row


def persist_country_slices_at_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    slice_by_country: dict[str, dict[str, Any]],
    *,
    commit: bool = True,
) -> int:
    """Write at_event rows for all eligible countries and upsert totals."""
    active = sorted(
        token
        for token, totals in slice_by_country.items()
        if int(totals.get("players") or 0) >= 1
    )
    if not active:
        return 0

    at_event_sql = _upsert_sql(
        "amiga_country_slice_at_event",
        COUNTRY_SLICE_AT_EVENT_COLUMNS,
        key_columns=("country_token", "slice_key", "as_of_tournament_id"),
    )
    totals_sql = _upsert_sql(
        "amiga_country_slice_totals",
        COUNTRY_SLICE_TOTALS_COLUMNS,
        key_columns=("country_token", "slice_key"),
    )

    at_rows = [
        _at_event_row(token, tournament_id, event_date, event_chrono, slice_by_country[token])
        for token in active
    ]
    total_rows = [_totals_row(token, slice_by_country[token]) for token in active]

    with conn.cursor() as cur:
        cur.executemany(at_event_sql, at_rows)
        cur.executemany(totals_sql, total_rows)
    if commit:
        conn.commit()

    return len(at_rows)
