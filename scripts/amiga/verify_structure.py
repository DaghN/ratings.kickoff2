#!/usr/bin/env python3
"""Verify L4 structure overlay after apply-structure (slice 5 STOP gate)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_structure.disposition_register import (
    HANDLER_PENDING_REVIEW,
    HANDLER_PURE_RR,
    HANDLER_STRUCTURE_SPEC,
    DispositionRegister,
    verify_register,
)

# Slice 5 smoke anchors (disposition register).
HOMEBURG_TOURNAMENT_ID = 137
PURE_RR_SMOKE_TOURNAMENT_ID = 1


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def _tournament_structure_counts(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[str, int]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM tournament_stages WHERE tournament_id = %s",
            (tournament_id,),
        )
        stages = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s
            """,
            (tournament_id,),
        )
        fixtures = int(cur.fetchone()["n"])
        cur.execute(
            "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s",
            (tournament_id,),
        )
        games = int(cur.fetchone()["n"])
        cur.execute(
            """
            SELECT COUNT(*) AS n FROM amiga_games
            WHERE tournament_id = %s AND fixture_id IS NOT NULL
            """,
            (tournament_id,),
        )
        linked = int(cur.fetchone()["n"])
    return {
        "stages": stages,
        "fixtures": fixtures,
        "games": games,
        "linked": linked,
    }


def verify_structure(*, homburg_id: int = HOMEBURG_TOURNAMENT_ID, pure_rr_id: int = PURE_RR_SMOKE_TOURNAMENT_ID) -> list[str]:
    errors: list[str] = []
    reg = DispositionRegister.load()
    conn = _connect()
    try:
        coverage = verify_register(conn, reg)
        if not coverage["ok"]:
            errors.append(
                f"disposition register incomplete: missing {len(coverage['missing_ids'])} id(s)"
            )

        homburg_row = reg.get(homburg_id)
        if homburg_row is None or homburg_row.handler != HANDLER_STRUCTURE_SPEC:
            errors.append(f"tournament_id={homburg_id} expected structure_spec in register")
        homburg = _tournament_structure_counts(conn, homburg_id)
        if homburg["games"] == 0:
            errors.append(f"Homburg id={homburg_id} has no games")
        elif homburg["fixtures"] == 0:
            errors.append(f"Homburg id={homburg_id} has no fixtures")
        elif homburg["linked"] != homburg["games"]:
            errors.append(
                f"Homburg id={homburg_id}: {homburg['linked']}/{homburg['games']} games linked"
            )

        rr_row = reg.get(pure_rr_id)
        if rr_row is None or rr_row.handler != HANDLER_PURE_RR:
            errors.append(f"tournament_id={pure_rr_id} expected pure_rr in register")
        rr = _tournament_structure_counts(conn, pure_rr_id)
        if rr["games"] == 0:
            errors.append(f"pure_rr smoke id={pure_rr_id} has no games")
        elif rr["fixtures"] == 0:
            errors.append(f"pure_rr smoke id={pure_rr_id} has no fixtures")
        elif rr["linked"] != rr["games"]:
            errors.append(
                f"pure_rr smoke id={pure_rr_id}: {rr['linked']}/{rr['games']} games linked"
            )

        for tid, row in reg.rows.items():
            if row.handler != HANDLER_PENDING_REVIEW:
                continue
            counts = _tournament_structure_counts(conn, tid)
            if counts["games"] > 0 and counts["stages"] > 0:
                errors.append(
                    f"pending_review id={tid} unexpectedly has {counts['stages']} stage(s)"
                )
                break
    finally:
        conn.close()

    return errors


def main() -> int:
    errors = verify_structure()
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    print(
        f"OK: L4 structure verified (Homburg id={HOMEBURG_TOURNAMENT_ID}, "
        f"pure_rr smoke id={PURE_RR_SMOKE_TOURNAMENT_ID})"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
