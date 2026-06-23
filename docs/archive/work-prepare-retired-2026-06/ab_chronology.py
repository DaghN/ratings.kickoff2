"""Chronological game checkpoints for post-game sim / parity."""

from __future__ import annotations

import pymysql


def list_game_ids_chronological(
    conn: pymysql.connections.Connection,
    *,
    limit: int | None = None,
    until_game_id: int | None = None,
) -> list[int]:
    with conn.cursor() as cur:
        cur.execute("SELECT id FROM ratedresults ORDER BY Date ASC, id ASC")
        rows = cur.fetchall()

    ids: list[int] = []
    for row in rows:
        gid = int(row["id"])
        if until_game_id is not None and gid > until_game_id:
            break
        ids.append(gid)
        if limit is not None and len(ids) >= limit:
            break
    return ids
