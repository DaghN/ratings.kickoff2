#!/usr/bin/env python3
"""Verify export pack manifests and STOP gates (slice 7)."""

from __future__ import annotations

import json
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.export_packs import (
    ALL_PACKS,
    L5_TABLES,
    PACK_GROUND,
    PACK_MIRROR,
    PACK_PRODUCT,
    PACK_STRUCTURE,
    PACK_TABLES,
    _DEFAULT_PACKS_ROOT,
)
from scripts.amiga.verify_structure import HOMEBURG_TOURNAMENT_ID, PURE_RR_SMOKE_TOURNAMENT_ID

_REPO = Path(__file__).resolve().parents[2]


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


def _load_pack_manifest(pack_dir: Path) -> dict[str, object]:
    path = pack_dir / "pack_manifest.json"
    if not path.is_file():
        raise FileNotFoundError(path)
    return json.loads(path.read_text(encoding="utf-8"))


def verify_export_pack(
    pack: str,
    *,
    pack_root: Path = _DEFAULT_PACKS_ROOT,
    check_live_db: bool = True,
) -> list[str]:
    errors: list[str] = []
    if pack not in ALL_PACKS:
        return [f"unknown pack {pack!r}"]

    pack_dir = pack_root / pack
    try:
        manifest = _load_pack_manifest(pack_dir)
    except FileNotFoundError as exc:
        return [str(exc)]

    for rel in manifest.get("files", []):
        if not (pack_dir / str(rel)).is_file():
            errors.append(f"missing pack file: {pack_dir / rel}")

    if pack == PACK_MIRROR:
        if not (pack_dir / "L1_mirror.sql").is_file():
            errors.append("mirror pack missing L1_mirror.sql")
        return errors

    if not (pack_dir / "schema.sql").is_file():
        errors.append("missing schema.sql")
    if not (pack_dir / "data.sql").is_file():
        errors.append("missing data.sql")

    manifest_tables: dict[str, int] = manifest.get("tables", {})
    expected_tables = PACK_TABLES[pack]

    for table in expected_tables:
        if table not in manifest_tables:
            errors.append(f"pack_manifest.tables missing {table}")

    for table in manifest_tables:
        if table in L5_TABLES and pack in (PACK_GROUND, PACK_STRUCTURE):
            errors.append(f"pack {pack} must not include L5 table {table}")

    if pack != PACK_MIRROR and not (pack_dir / "manifests" / "import_manifest.json").is_file():
        errors.append("missing manifests/import_manifest.json")

    if pack in (PACK_STRUCTURE, PACK_PRODUCT):
        if not (pack_dir / "manifests" / "disposition_register.json").is_file():
            errors.append("missing manifests/disposition_register.json")

    if check_live_db and manifest_tables and not errors:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                for table, want in manifest_tables.items():
                    cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
                    got = int(cur.fetchone()["n"])
                    if got != int(want):
                        errors.append(f"live {table}: {got} rows != pack_manifest {want}")
        finally:
            conn.close()

    if pack == PACK_STRUCTURE and check_live_db and not errors:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
                if int(cur.fetchone()["n"]) == 0:
                    errors.append("structure pack STOP: tournament_fixtures is empty")

                cur.execute(
                    """
                    SELECT COUNT(*) AS linked FROM amiga_games
                    WHERE tournament_id = %s AND fixture_id IS NOT NULL
                    """,
                    (HOMEBURG_TOURNAMENT_ID,),
                )
                homburg_linked = int(cur.fetchone()["linked"])
                cur.execute(
                    "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s",
                    (HOMEBURG_TOURNAMENT_ID,),
                )
                homburg_games = int(cur.fetchone()["n"])
                if homburg_games > 0 and homburg_linked != homburg_games:
                    errors.append(
                        f"structure pack STOP: Homburg id={HOMEBURG_TOURNAMENT_ID} "
                        f"{homburg_linked}/{homburg_games} games linked"
                    )

                cur.execute(
                    """
                    SELECT COUNT(*) AS linked FROM amiga_games
                    WHERE tournament_id = %s AND fixture_id IS NOT NULL
                    """,
                    (PURE_RR_SMOKE_TOURNAMENT_ID,),
                )
                rr_linked = int(cur.fetchone()["linked"])
                cur.execute(
                    "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s",
                    (PURE_RR_SMOKE_TOURNAMENT_ID,),
                )
                rr_games = int(cur.fetchone()["n"])
                if rr_games > 0 and rr_linked != rr_games:
                    errors.append(
                        f"structure pack STOP: pure_rr smoke id={PURE_RR_SMOKE_TOURNAMENT_ID} "
                        f"{rr_linked}/{rr_games} games linked"
                    )
        finally:
            conn.close()

    if pack == PACK_PRODUCT and check_live_db and not errors:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
                games = int(cur.fetchone()["n"])
                cur.execute("SELECT COUNT(*) AS n FROM amiga_game_ratings")
                ratings = int(cur.fetchone()["n"])
                if games > 0 and ratings != games:
                    errors.append(f"product pack: ratings {ratings} != games {games}")
        finally:
            conn.close()

    return errors


def main(argv: list[str] | None = None) -> int:
    import argparse
    import sys

    parser = argparse.ArgumentParser(description="Verify Amiga export pack")
    parser.add_argument("pack", choices=ALL_PACKS)
    parser.add_argument("--pack-root", type=Path, default=_DEFAULT_PACKS_ROOT)
    parser.add_argument("--no-live-db", action="store_true")
    args = parser.parse_args(argv)

    errors = verify_export_pack(
        args.pack,
        pack_root=args.pack_root,
        check_live_db=not args.no_live_db,
    )
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(f"OK: export pack {args.pack!r} verified ({args.pack_root / args.pack})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
