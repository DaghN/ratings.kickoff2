#!/usr/bin/env python3
"""Assert tournaments.is_world_cup matches canonical name rule and snapshot denorm."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_honours import is_world_cup_tournament


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def verify_is_world_cup(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute("SELECT id, name, is_world_cup, source_id FROM tournaments ORDER BY id")
        for row in cur.fetchall():
            name = str(row.get("name") or "")
            expected = 1 if is_world_cup_tournament(name) else 0
            actual = int(row.get("is_world_cup") or 0)
            if actual != expected:
                errors.append(
                    f"tournaments.id={row['id']} name={name!r}: is_world_cup={actual} expected {expected}"
                )

        cur.execute(
            """
            SELECT s.player_id, s.tournament_id, s.is_world_cup AS snap_flag, t.is_world_cup AS tour_flag
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE COALESCE(s.is_world_cup, -1) <> COALESCE(t.is_world_cup, 0)
            LIMIT 20
            """
        )
        for row in cur.fetchall():
            errors.append(
                "snapshot is_world_cup mismatch "
                f"player_id={row['player_id']} tournament_id={row['tournament_id']}: "
                f"snap={row['snap_flag']} tour={row['tour_flag']}"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_is_world_cup(conn)
    finally:
        conn.close()

    if errors:
        for err in errors[:50]:
            print(f"verify-is-world-cup: {err}", file=sys.stderr)
        if len(errors) > 50:
            print(f"verify-is-world-cup: ... and {len(errors) - 50} more", file=sys.stderr)
        return 1

    print("verify-is-world-cup OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())