"""Tournament chrono cutoff helpers for realm snapshot compute."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

import pymysql


@dataclass(frozen=True)
class RealmCutoff:
    tournament_id: int
    event_date: Any
    chrono: Any
    tournament_name: str


def load_realm_cutoff(
    conn: pymysql.connections.Connection,
    as_of_tournament_id: int,
) -> RealmCutoff:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, event_date, chrono, name
            FROM tournaments
            WHERE id = %s
            LIMIT 1
            """,
            (as_of_tournament_id,),
        )
        row = cur.fetchone()
    if row is None:
        raise ValueError(f"tournament_id={as_of_tournament_id} not found")
    return RealmCutoff(
        tournament_id=int(row["id"]),
        event_date=row["event_date"],
        chrono=row["chrono"],
        tournament_name=str(row["name"]),
    )


def cutoff_params(cutoff: RealmCutoff) -> tuple[Any, Any, int]:
    return (cutoff.event_date, cutoff.chrono, cutoff.tournament_id)


def tournament_cutoff_params(cutoff: RealmCutoff) -> tuple[Any, Any, Any, Any, int]:
    """Five placeholders for event_date/chrono/id inclusive cutoff WHERE clauses."""
    return (
        cutoff.event_date,
        cutoff.event_date,
        cutoff.chrono,
        cutoff.chrono,
        cutoff.tournament_id,
    )


def game_cutoff_sql(alias: str = "t") -> str:
    return f"({alias}.event_date, {alias}.chrono, {alias}.id) <= (%s, %s, %s)"


def latest_finalized_tournament_id(conn: pymysql.connections.Connection) -> int | None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id
            FROM tournaments
            WHERE rating_finalized = 1
            ORDER BY event_date DESC, chrono DESC, id DESC
            LIMIT 1
            """
        )
        row = cur.fetchone()
    return int(row["id"]) if row else None
