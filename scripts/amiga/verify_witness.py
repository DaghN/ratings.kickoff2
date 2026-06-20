#!/usr/bin/env python3
"""Assert L3 witness import: manifest + ground rows; L4/L5 empty until replay."""

from __future__ import annotations

import json
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import _DERIVED_TRUNCATE_ORDER
from scripts.amiga.import_manifest import default_manifest_path

_REPO = Path(__file__).resolve().parents[2]

_L4_STRUCTURE_TABLES = (
    "tournament_stages",
    "tournament_fixtures",
    "tournament_stage_players",
    "tournament_entrants",
)

_L3_GROUND_TABLES = (
    "tournaments",
    "amiga_players",
    "amiga_games",
)


def verify_witness(*, manifest_path: Path | None = None) -> list[str]:
    errors: list[str] = []
    manifest_path = manifest_path or default_manifest_path(_REPO)

    if not manifest_path.is_file():
        errors.append(f"missing manifest: {manifest_path} (run import-witness first)")
        manifest_stats: dict[str, object] = {}
    else:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("manifest_version") != 1:
            errors.append(f"unexpected manifest_version: {manifest.get('manifest_version')}")
        manifest_stats = manifest.get("stats", {})
        structure_specs = manifest.get("transforms", {}).get("structure_specs", None)
        if structure_specs is None:
            errors.append("manifest missing transforms.structure_specs")
        elif structure_specs:
            errors.append(
                f"witness manifest should have empty structure_specs, got {len(structure_specs)}"
            )

    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )

    with conn.cursor() as cur:
        cur.execute("SELECT DATABASE()")
        db = cur.fetchone()["DATABASE()"]

        for table in _L3_GROUND_TABLES:
            cur.execute(
                """
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = %s AND table_name = %s
                """,
                (db, table),
            )
            if not cur.fetchone():
                errors.append(f"missing L3 table: {table}")
                continue
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            count = int(cur.fetchone()["n"])
            if count == 0:
                errors.append(f"L3 table {table} is empty")

        if manifest_stats:
            for key, table in (
                ("tournaments", "tournaments"),
                ("games", "amiga_games"),
                ("players_canonical", "amiga_players"),
            ):
                want = manifest_stats.get(key)
                if want is None:
                    errors.append(f"manifest stats missing {key}")
                    continue
                cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
                got = int(cur.fetchone()["n"])
                if got != int(want):
                    errors.append(f"{table} count {got} != manifest stats.{key}={want}")

        for table in _L4_STRUCTURE_TABLES:
            cur.execute(
                """
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = %s AND table_name = %s
                """,
                (db, table),
            )
            if not cur.fetchone():
                continue
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            count = int(cur.fetchone()["n"])
            if count != 0:
                errors.append(f"L4 table {table} has {count} rows (witness expects 0)")

        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id IS NOT NULL")
        linked = int(cur.fetchone()["n"])
        if linked != 0:
            errors.append(f"amiga_games with fixture_id: {linked} (witness expects 0)")

        for table in _DERIVED_TRUNCATE_ORDER:
            cur.execute(
                """
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = %s AND table_name = %s
                """,
                (db, table),
            )
            if not cur.fetchone():
                continue
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            count = int(cur.fetchone()["n"])
            if table == "amiga_generalstats" and count == 1:
                cur.execute("SELECT GamesPlayed FROM amiga_generalstats WHERE id = 1")
                row = cur.fetchone()
                if row and int(row.get("GamesPlayed") or 0) != 0:
                    errors.append("amiga_generalstats.GamesPlayed should be 0 before replay")
                continue
            if count != 0:
                errors.append(f"L5 table {table} has {count} rows (witness expects 0)")

    conn.close()
    return errors


def main(argv: list[str] | None = None) -> int:
    import argparse

    parser = argparse.ArgumentParser(description="Verify L3 witness import state")
    parser.add_argument(
        "--manifest",
        type=Path,
        default=default_manifest_path(_REPO),
    )
    args = parser.parse_args(argv)

    errors = verify_witness(manifest_path=args.manifest)
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(f"OK: L3 witness verified ({args.manifest})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
