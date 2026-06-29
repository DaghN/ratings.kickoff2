"""Persist amiga_wc_hof_snapshots (per WC) + amiga_wc_hof_present (id=1) — WCH-3.

Idempotent (decision ID2): snapshot is UPSERT keyed by ``tournament_id``; present is
UPSERT id=1 mirroring the row just written. A full replay finalizes World Cups in
chrono order, so present ends on the latest WC snapshot.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

import pymysql

from scripts.amiga.realm_cutoff import load_realm_cutoff
from scripts.amiga.wc_hof import build_wc_hof_payload
from scripts.amiga.wc_hof_columns import (
    WC_HOF_PAYLOAD_COLUMNS,
    WC_HOF_SNAPSHOT_COLUMNS,
)


def build_wc_hof_row(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int,
    finalized_at: datetime | None = None,
) -> dict[str, Any]:
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    payload = build_wc_hof_payload(conn, as_of_tournament_id=as_of_tournament_id)
    if finalized_at is None:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT rating_finalized_at FROM tournaments WHERE id = %s LIMIT 1",
                (as_of_tournament_id,),
            )
            tour = cur.fetchone()
        finalized_at = tour.get("rating_finalized_at") if tour else None
    if finalized_at is None:
        finalized_at = datetime.now(timezone.utc).replace(tzinfo=None)

    row: dict[str, Any] = {
        "tournament_id": cutoff.tournament_id,
        "event_date": cutoff.event_date,
        "event_chrono": cutoff.chrono,
        "tournament_name": cutoff.tournament_name,
        "finalized_at": finalized_at,
    }
    row.update(payload)
    missing = [col for col in WC_HOF_SNAPSHOT_COLUMNS if col not in row]
    if missing:
        raise RuntimeError(f"wc hof row missing columns: {missing[:5]}")
    return row


def _snapshot_upsert_sql() -> str:
    cols = WC_HOF_SNAPSHOT_COLUMNS
    col_list = ", ".join(f"`{c}`" for c in cols)
    placeholders = ", ".join(["%s"] * len(cols))
    updates = ", ".join(f"`{c}` = VALUES(`{c}`)" for c in cols if c != "tournament_id")
    return (
        f"INSERT INTO amiga_wc_hof_snapshots ({col_list}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def _present_upsert_sql() -> str:
    cols = ("id",) + WC_HOF_PAYLOAD_COLUMNS
    col_list = ", ".join(f"`{c}`" for c in cols)
    placeholders = ", ".join(["%s"] * len(cols))
    updates = ", ".join(f"`{c}` = VALUES(`{c}`)" for c in WC_HOF_PAYLOAD_COLUMNS)
    return (
        f"INSERT INTO amiga_wc_hof_present ({col_list}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def persist_wc_hof_snapshot(
    conn: pymysql.connections.Connection,
    row: dict[str, Any],
    *,
    commit: bool = True,
) -> None:
    snapshot_values = [row[col] for col in WC_HOF_SNAPSHOT_COLUMNS]
    present_values = [1] + [row.get(col) for col in WC_HOF_PAYLOAD_COLUMNS]
    with conn.cursor() as cur:
        cur.execute(_snapshot_upsert_sql(), snapshot_values)
        cur.execute(_present_upsert_sql(), present_values)
    if commit:
        conn.commit()


def persist_wc_hof_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    finalized_at: datetime | None = None,
    commit: bool = True,
) -> dict[str, Any]:
    """Compute + write the WC HoF snapshot/present for a World Cup tournament."""
    row = build_wc_hof_row(
        conn,
        as_of_tournament_id=tournament_id,
        finalized_at=finalized_at,
    )
    persist_wc_hof_snapshot(conn, row, commit=commit)
    return row