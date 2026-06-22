"""Persist amiga_community_stats snapshots + facts."""

from __future__ import annotations

from datetime import datetime
from typing import Any

import pymysql

from scripts.amiga.community_stat_facts import (
    build_community_facts_at_cutoff,
    fact_row_values,
)
from scripts.amiga.community_stats import build_community_headline_row
from scripts.amiga.community_stats_columns import (
    COMMUNITY_FACT_COLUMNS,
    COMMUNITY_HEADLINE_COLUMNS,
    COMMUNITY_SNAPSHOT_COLUMNS,
)


def _headline_upsert_sql() -> str:
    cols = COMMUNITY_SNAPSHOT_COLUMNS
    col_list = ", ".join(f"`{c}`" for c in cols)
    placeholders = ", ".join(["%s"] * len(cols))
    updates = ", ".join(
        f"`{c}` = VALUES(`{c}`)" for c in cols if c != "tournament_id"
    )
    return (
        f"INSERT INTO amiga_community_stats_snapshots ({col_list}) VALUES ({placeholders}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def _write_present_headline(
    conn: pymysql.connections.Connection,
    headline: dict[str, Any],
) -> None:
    sets = ", ".join(f"`{col}` = %s" for col in COMMUNITY_HEADLINE_COLUMNS)
    values = [headline[col] for col in COMMUNITY_HEADLINE_COLUMNS]
    with conn.cursor() as cur:
        cur.execute(f"UPDATE amiga_community_stats SET {sets} WHERE id = 1", values)


def persist_community_headline(
    conn: pymysql.connections.Connection,
    row: dict[str, Any],
    *,
    commit: bool = True,
) -> None:
    sql = _headline_upsert_sql()
    values = [row[col] for col in COMMUNITY_SNAPSHOT_COLUMNS]
    with conn.cursor() as cur:
        cur.execute(sql, values)
    headline = {col: row[col] for col in COMMUNITY_HEADLINE_COLUMNS}
    _write_present_headline(conn, headline)
    if commit:
        conn.commit()


def persist_community_facts(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    facts: list[dict[str, Any]],
    *,
    commit: bool = True,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_community_stat_facts WHERE tournament_id = %s",
            (tournament_id,),
        )
        if facts:
            col_list = ", ".join(f"`{c}`" for c in COMMUNITY_FACT_COLUMNS)
            placeholders = ", ".join(["%s"] * len(COMMUNITY_FACT_COLUMNS))
            sql = (
                f"INSERT INTO amiga_community_stat_facts ({col_list}) "
                f"VALUES ({placeholders})"
            )
            cur.executemany(sql, [fact_row_values(f) for f in facts])
    if commit:
        conn.commit()
    return len(facts)


def persist_community_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    finalized_at: datetime | None = None,
    commit: bool = True,
) -> dict[str, Any]:
    row = build_community_headline_row(
        conn,
        as_of_tournament_id=tournament_id,
        finalized_at=finalized_at,
    )
    facts = build_community_facts_at_cutoff(conn, tournament_id)
    persist_community_headline(conn, row, commit=False)
    fact_count = persist_community_facts(conn, tournament_id, facts, commit=False)
    if commit:
        conn.commit()
    return {"headline_row": row, "fact_count": fact_count}
