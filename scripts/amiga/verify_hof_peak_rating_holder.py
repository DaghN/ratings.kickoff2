#!/usr/bin/env python3
"""Verify HoF peak row uses career PeakRating (not stored BiggestPeakRating)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.peak_rating_hof_holder import peak_rating_hof_holder_oracle
from scripts.amiga.realm_cutoff import latest_finalized_tournament_id, load_realm_cutoff

_RETIRED_COLUMNS = (
    "BiggestPeakRating",
    "BiggestPeakRatingID",
    "BiggestPeakRatingName",
    "BiggestPeakRatingDate",
)
_TABLES = ("amiga_generalstats", "amiga_realm_snapshots")


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def verify_hof_peak_rating_holder(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        for table in _TABLES:
            placeholders = ", ".join(["%s"] * len(_RETIRED_COLUMNS))
            cur.execute(
                f"""
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = %s
                  AND COLUMN_NAME IN ({placeholders})
                """,
                (table, *_RETIRED_COLUMNS),
            )
            present = [row["COLUMN_NAME"] for row in cur.fetchall()]
            if present:
                errors.append(
                    f"{table} still has retired BiggestPeakRating columns: {', '.join(present)}"
                )

    latest_tid = latest_finalized_tournament_id(conn)
    if latest_tid is None:
        return errors

    cutoff = load_realm_cutoff(conn, latest_tid)
    holder = peak_rating_hof_holder_oracle(conn, cutoff=cutoff)
    if holder is None:
        errors.append("peak_rating_hof_holder_oracle returned no holder")
        return errors

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT MAX(PeakRating) AS max_peak
            FROM amiga_player_current
            WHERE NumberGames > 0 AND PeakRating IS NOT NULL AND PeakRating > 0
            """
        )
        max_peak = cur.fetchone()["max_peak"]
    if max_peak is None:
        errors.append("no established PeakRating values on amiga_player_current")
        return errors

    if abs(float(holder["value"]) - float(max_peak)) > 1e-5:
        errors.append(
            f"HoF peak holder value {holder['value']!r} != max PeakRating {max_peak!r}"
        )

    return errors


def main() -> int:
    conn = _connect()
    try:
        errors = verify_hof_peak_rating_holder(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-hof-peak-rating-holder issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        return 1

    print("OK: verify-hof-peak-rating-holder")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
