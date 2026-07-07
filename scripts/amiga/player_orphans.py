"""Orphan cleanup for live-created amiga_players."""

from __future__ import annotations

import pymysql

PLAYER_SOURCE_IMPORT = "import"
PLAYER_SOURCE_LIVE_OPS = "live_ops"


def _load_player_row(conn: pymysql.connections.Connection, player_id: int) -> dict[str, object] | None:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, name, country, player_source FROM amiga_players WHERE id = %s",
            (player_id,),
        )
        return cur.fetchone()


def count_player_games(conn: pymysql.connections.Connection, player_id: int) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_games WHERE player_a_id = %s OR player_b_id = %s",
            (player_id, player_id),
        )
        return int(cur.fetchone()["n"])


def count_entrant_links_excluding_tournament(
    conn: pymysql.connections.Connection,
    player_id: int,
    *,
    excluding_tournament_id: int | None,
) -> int:
    with conn.cursor() as cur:
        if excluding_tournament_id is None:
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournament_entrants WHERE player_id = %s",
                (player_id,),
            )
        else:
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournament_entrants WHERE player_id = %s AND tournament_id <> %s",
                (player_id, excluding_tournament_id),
            )
        return int(cur.fetchone()["n"])


def is_orphan_deletable(
    conn: pymysql.connections.Connection,
    player_id: int,
    *,
    excluding_tournament_id: int | None = None,
) -> bool:
    row = _load_player_row(conn, player_id)
    if row is None:
        return False
    if str(row.get("player_source") or PLAYER_SOURCE_IMPORT) != PLAYER_SOURCE_LIVE_OPS:
        return False
    if count_player_games(conn, player_id) > 0:
        return False
    if count_entrant_links_excluding_tournament(
        conn, player_id, excluding_tournament_id=excluding_tournament_id
    ) > 0:
        return False
    return True


def delete_orphan_player(
    conn: pymysql.connections.Connection,
    player_id: int,
    *,
    excluding_tournament_id: int | None = None,
    dry_run: bool = False,
) -> bool:
    if not is_orphan_deletable(conn, player_id, excluding_tournament_id=excluding_tournament_id):
        return False
    if dry_run:
        return True
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_players WHERE id = %s AND player_source = %s",
            (player_id, PLAYER_SOURCE_LIVE_OPS),
        )
        return cur.rowcount > 0


def tournament_entrant_player_ids(conn: pymysql.connections.Connection, tournament_id: int) -> list[int]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT DISTINCT player_id FROM tournament_entrants WHERE tournament_id = %s ORDER BY player_id",
            (tournament_id,),
        )
        return [int(row["player_id"]) for row in cur.fetchall()]


def delete_orphan_live_players_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> list[int]:
    deleted: list[int] = []
    for player_id in tournament_entrant_player_ids(conn, tournament_id):
        if delete_orphan_player(
            conn, player_id, excluding_tournament_id=tournament_id, dry_run=dry_run
        ):
            deleted.append(player_id)
    return deleted