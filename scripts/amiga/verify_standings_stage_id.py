#!/usr/bin/env python3
"""L5 stage_id dual-write oracle vs compute output (SC-9)."""

from __future__ import annotations

import argparse
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.scoring_contract import load_scoring_context_for_tournament
from scripts.amiga.tournament_standings import GAME_SELECT_FOR_TOURNAMENT, compute_tournament_standings


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


def _column_exists(conn: pymysql.connections.Connection, table: str, column: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = %s
              AND COLUMN_NAME = %s
            LIMIT 1
            """,
            (table, column),
        )
        return cur.fetchone() is not None


def _sample_tournament_ids(
    conn: pymysql.connections.Connection,
    *,
    sample: int,
    tournament_id: int | None,
    sweep: bool,
) -> list[int]:
    if tournament_id is not None:
        return [tournament_id]
    with conn.cursor() as cur:
        if sweep:
            cur.execute(
                """
                SELECT DISTINCT tournament_id
                FROM amiga_tournament_standings
                ORDER BY tournament_id
                """
            )
            return [int(row["tournament_id"]) for row in cur.fetchall()]
        cur.execute(
            """
            SELECT tournament_id
            FROM amiga_tournament_standings
            GROUP BY tournament_id
            ORDER BY COUNT(*) DESC, tournament_id ASC
            LIMIT %s
            """,
            (max(sample, 1),),
        )
        return [int(row["tournament_id"]) for row in cur.fetchall()]


def verify_standings_stage_id(
    conn: pymysql.connections.Connection,
    *,
    tournament_ids: list[int],
) -> list[str]:
    errors: list[str] = []
    if not _column_exists(conn, "amiga_tournament_standings", "stage_id"):
        return ["amiga_tournament_standings.stage_id column missing — run apply_schema_derived"]

    for tid in tournament_ids:
        with conn.cursor() as cur:
            cur.execute(GAME_SELECT_FOR_TOURNAMENT, (tid,))
            games = cur.fetchall()
            cur.execute(
                """
                SELECT scope_type, scope_key, player_id, stage_id
                FROM amiga_tournament_standings
                WHERE tournament_id = %s
                """,
                (tid,),
            )
            db_rows = list(cur.fetchall())

        if not games or not db_rows:
            continue

        scoring_context = load_scoring_context_for_tournament(conn, tid)
        computed = compute_tournament_standings(games, scoring_context=scoring_context)
        expected = {
            (row["scope_type"], row["scope_key"], int(row["player_id"])): row.get("stage_id")
            for row in computed
        }

        for row in db_rows:
            key = (row["scope_type"], row["scope_key"], int(row["player_id"]))
            if key not in expected:
                errors.append(f"tournament_id={tid}: unexpected DB standings row {key}")
                continue
            exp = expected[key]
            got = row["stage_id"]
            if exp is None and got is None:
                continue
            if exp is None or got is None or int(exp) != int(got):
                errors.append(
                    f"tournament_id={tid}: stage_id mismatch for {key} (db={got}, expected={exp})"
                )

    return errors


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Verify L5 stage_id dual-write (SC-9)")
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--sample", type=int, default=10)
    parser.add_argument("--sweep", action="store_true")
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        tournament_ids = _sample_tournament_ids(
            conn,
            sample=args.sample,
            tournament_id=args.tournament_id,
            sweep=args.sweep,
        )
        errors = verify_standings_stage_id(conn, tournament_ids=tournament_ids)
    finally:
        conn.close()

    if errors:
        for err in errors[:20]:
            print(f"FAIL: {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"FAIL: ... and {len(errors) - 20} more", file=sys.stderr)
        print(f"verify-standings-stage-id: FAIL ({len(errors)} errors)", file=sys.stderr)
        return 1

    print(f"verify-standings-stage-id: OK ({len(tournament_ids)} tournament(s) checked)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())