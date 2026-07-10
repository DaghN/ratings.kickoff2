#!/usr/bin/env python3
"""Structural oracle for L4b scoring contracts (SC-2)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.scoring_contract import (
    load_stage_scoring_contract,
    validate_stage_scoring_contract,
)


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


def verify_scoring_contract(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.stage_id
            FROM tournament_stage_scoring_steps s
            INNER JOIN tournament_stages st ON st.id = s.stage_id
            WHERE st.scoring_primitive IS NULL
            GROUP BY s.stage_id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"stage_id={row['stage_id']}: scoring steps exist but scoring_primitive IS NULL"
            )

        cur.execute(
            """
            SELECT id, tournament_id, stage_key
            FROM tournament_stages
            WHERE scoring_primitive IS NULL
              AND (
                  scoring_schema_version IS NOT NULL
                  OR scoring_win_points IS NOT NULL
                  OR scoring_draw_points IS NOT NULL
                  OR scoring_loss_points IS NOT NULL
              )
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"stage_id={row['id']} tournament_id={row['tournament_id']} "
                f"stage_key={row['stage_key']!r}: partial scoring contract without scoring_primitive"
            )

        cur.execute(
            """
            SELECT id
            FROM tournament_stages
            WHERE scoring_primitive IS NOT NULL
            ORDER BY id
            """
        )
        stage_ids = [int(row["id"]) for row in cur.fetchall()]

    for stage_id in stage_ids:
        try:
            contract = load_stage_scoring_contract(conn, stage_id)
        except ValueError as exc:
            errors.append(str(exc))
            continue
        if contract is None:
            errors.append(
                f"stage_id={stage_id}: scoring_primitive set but contract load returned None"
            )
            continue
        errors.extend(validate_stage_scoring_contract(contract))

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, tournament_id, stage_key, stage_type
            FROM tournament_stages
            WHERE stage_type IN ('round_robin', 'knockout')
              AND scoring_primitive IS NULL
            ORDER BY id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"stage_id={row['id']} tournament_id={row['tournament_id']} "
                f"stage_key={row['stage_key']!r}: missing scoring contract (SC-6 backfill)"
            )

        cur.execute(
            """
            SELECT id, name
            FROM tournaments
            WHERE rating_finalized = 1
              AND scoring_frozen_at IS NULL
            ORDER BY id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"tournament_id={row['id']} name={row['name']!r}: "
                "rating_finalized without scoring freeze (SC-7)"
            )

        cur.execute(
            """
            SELECT s.id, s.tournament_id, s.stage_key
            FROM tournament_stages s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 1
              AND s.scoring_primitive IS NOT NULL
              AND s.frozen_scoring_primitive IS NULL
            ORDER BY s.id
            """
        )
        for row in cur.fetchall():
            errors.append(
                f"stage_id={row['id']} tournament_id={row['tournament_id']} "
                f"stage_key={row['stage_key']!r}: missing frozen scoring contract (SC-7)"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_scoring_contract(conn)
    finally:
        conn.close()

    if errors:
        for err in errors[:50]:
            print(f"verify-scoring-contract: {err}", file=sys.stderr)
        if len(errors) > 50:
            print(
                f"verify-scoring-contract: ... and {len(errors) - 50} more",
                file=sys.stderr,
            )
        return 1

    print("verify-scoring-contract OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
