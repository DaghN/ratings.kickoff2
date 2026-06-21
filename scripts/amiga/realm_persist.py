"""Build and persist amiga_realm_snapshots + amiga_generalstats rows."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

import pymysql

from scripts.amiga.generalstats_columns import (
    GENERALSTATS_PAYLOAD_COLUMNS,
    REALM_SNAPSHOT_COLUMNS,
)
from scripts.amiga.realm_cutoff import load_realm_cutoff
from scripts.amiga.realm_incremental import build_generalstats_payload_incremental
from scripts.ladder.player_state import PlayerState


def build_realm_row(
    conn: pymysql.connections.Connection,
    *,
    as_of_tournament_id: int,
    finalized_at: datetime | None = None,
    prior_payload: dict[str, Any] | None = None,
    players: dict[int, PlayerState] | None = None,
    names: dict[int, str] | None = None,
    games: list[dict[str, Any]] | None = None,
    ratings_by_game_id: dict[int, dict[str, Any]] | None = None,
    event_date: Any | None = None,
) -> dict[str, Any]:
    """Full realm snapshot row dict for one tournament cutoff."""
    cutoff = load_realm_cutoff(conn, as_of_tournament_id)
    payload = build_generalstats_payload_incremental(
        conn,
        as_of_tournament_id,
        prior_payload=prior_payload,
        players=players,
        names=names,
        games=games,
        ratings_by_game_id=ratings_by_game_id,
        event_date=event_date if event_date is not None else cutoff.event_date,
    )
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
    missing = [col for col in REALM_SNAPSHOT_COLUMNS if col not in row]
    if missing:
        raise RuntimeError(f"realm row missing columns: {missing[:5]}")
    return row


def realm_snapshot_upsert_sql() -> str:
    cols = REALM_SNAPSHOT_COLUMNS
    col_list = ", ".join(f"`{c}`" for c in cols)
    placeholders = ", ".join(["%s"] * len(cols))
    updates = ", ".join(
        f"`{c}` = VALUES(`{c}`)"
        for c in cols
        if c != "tournament_id"
    )
    return (
        f"INSERT INTO amiga_realm_snapshots ({col_list}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def persist_realm_snapshot(
    conn: pymysql.connections.Connection,
    row: dict[str, Any],
    *,
    commit: bool = True,
) -> None:
    """Write realm timeline row and present generalstats projection."""
    from scripts.amiga.server_records import write_generalstats_row

    sql = realm_snapshot_upsert_sql()
    values = [row[col] for col in REALM_SNAPSHOT_COLUMNS]
    with conn.cursor() as cur:
        cur.execute(sql, values)
    payload = {col: row[col] for col in GENERALSTATS_PAYLOAD_COLUMNS if col in row}
    write_generalstats_row(conn, payload)
    if commit:
        conn.commit()


def persist_realm_snapshot_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    finalized_at: datetime | None = None,
    commit: bool = True,
    prior_payload: dict[str, Any] | None = None,
    players: dict[int, PlayerState] | None = None,
    names: dict[int, str] | None = None,
    games: list[dict[str, Any]] | None = None,
    ratings_by_game_id: dict[int, dict[str, Any]] | None = None,
    event_date: Any | None = None,
) -> dict[str, Any]:
    row = build_realm_row(
        conn,
        as_of_tournament_id=tournament_id,
        finalized_at=finalized_at,
        prior_payload=prior_payload,
        players=players,
        names=names,
        games=games,
        ratings_by_game_id=ratings_by_game_id,
        event_date=event_date,
    )
    persist_realm_snapshot(conn, row, commit=commit)
    return row
