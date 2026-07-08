#!/usr/bin/env python3
"""Community export packs — Mirror (L1) / Ground / Structure / Product (L3–L5)."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Final

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_manifest import default_manifest_path
from scripts.amiga.import_pristine import _DEFAULT_OUT as _PRISTINE_OUT
from scripts.amiga.import_pristine import run_import_pristine
from scripts.amiga.tournament_structure.disposition_register import REGISTER_PATH
from scripts.work_prepare.paths import find_mysqldump_exe

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_DEFAULT_PACKS_ROOT = _REPO / "data" / "amiga" / "exports" / "packs"

PACK_MIRROR: Final[str] = "mirror"
PACK_GROUND: Final[str] = "ground"
PACK_STRUCTURE: Final[str] = "structure"
PACK_PRODUCT: Final[str] = "product"

ALL_PACKS: tuple[str, ...] = (PACK_MIRROR, PACK_GROUND, PACK_STRUCTURE, PACK_PRODUCT)

L3_TABLES: tuple[str, ...] = (
    "tournaments",
    "amiga_players",
    "amiga_games",
    "amiga_tournament_finish_override",
)

L4_TABLES: tuple[str, ...] = (
    "tournament_format_templates",
    "tournament_entrants",
    "tournament_stages",
    "tournament_stage_players",
    "tournament_fixtures",
)

L5_TABLES: tuple[str, ...] = (
    "amiga_game_ratings",
    "amiga_player_event_snapshots",
    "amiga_player_current",
    "amiga_player_matchup_at_event",
    "amiga_player_matchup_summary",
    "amiga_tournament_standings",
    "amiga_tournament_catalog_stats",
    "amiga_generalstats",
    "amiga_realm_snapshots",
    "amiga_community_stats",
    "amiga_community_stats_snapshots",
    "amiga_community_stat_facts",
)

PACK_TABLES: dict[str, tuple[str, ...]] = {
    PACK_GROUND: L3_TABLES,
    PACK_STRUCTURE: L3_TABLES + L4_TABLES,
    PACK_PRODUCT: L3_TABLES + L4_TABLES + L5_TABLES,
}

PACK_LAYERS: dict[str, str] = {
    PACK_MIRROR: "L1",
    PACK_GROUND: "L3",
    PACK_STRUCTURE: "L3+L4",
    PACK_PRODUCT: "L3+L4+L5",
}


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database not in ("ko2amiga_work", "ko2amiga_db"):
        raise SystemExit(
            f"Refusing export: expected ko2amiga_work or ko2amiga_db, got {cfg.database!r}"
        )
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def _mysqldump(database: str, tables: tuple[str, ...], *, schema_only: bool) -> str:
    dump_exe = find_mysqldump_exe()
    args = [str(dump_exe), "-u", "root", "--single-transaction", "--set-gtid-purged=OFF"]
    if schema_only:
        args.append("--no-data")
    else:
        args.append("--no-create-info")
    args.append(database)
    args.extend(tables)
    proc = subprocess.run(args, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if proc.returncode != 0:
        raise SystemExit(f"mysqldump failed ({proc.returncode}): {proc.stderr.strip() or proc.stdout[:500]}")
    return proc.stdout


def _table_row_counts(conn: pymysql.connections.Connection, tables: tuple[str, ...]) -> dict[str, int]:
    counts: dict[str, int] = {}
    with conn.cursor() as cur:
        for table in tables:
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            counts[table] = int(cur.fetchone()["n"])
    return counts


def _copy_manifests(pack_dir: Path, *, include_disposition: bool) -> list[str]:
    copied: list[str] = []
    manifest_dir = pack_dir / "manifests"
    manifest_dir.mkdir(parents=True, exist_ok=True)

    import_manifest = default_manifest_path(_REPO)
    if import_manifest.is_file():
        dest = manifest_dir / "import_manifest.json"
        shutil.copy2(import_manifest, dest)
        copied.append(str(dest.relative_to(pack_dir)))

    name_merges = _REPO / "data" / "amiga" / "exports" / "name_merges.json"
    if name_merges.is_file():
        dest = manifest_dir / "name_merges.json"
        shutil.copy2(name_merges, dest)
        copied.append(str(dest.relative_to(pack_dir)))

    if include_disposition and REGISTER_PATH.is_file():
        dest = manifest_dir / "disposition_register.json"
        shutil.copy2(REGISTER_PATH, dest)
        copied.append(str(dest.relative_to(pack_dir)))

    prune_manifest = _REPO / "data" / "amiga" / "exports" / "pruned" / "prune_manifest.json"
    if prune_manifest.is_file():
        dest = manifest_dir / "prune_manifest.json"
        shutil.copy2(prune_manifest, dest)
        copied.append(str(dest.relative_to(pack_dir)))

    return copied


def export_pack_mirror(
    *,
    out_dir: Path,
    mdb: Path = _DEFAULT_MDB,
    refresh_pristine: bool = False,
) -> dict[str, object]:
    """Pack Mirror — L1 pristine Access mirror (archivists)."""
    out_dir.mkdir(parents=True, exist_ok=True)
    pristine_sql = _PRISTINE_OUT / "L1_mirror.sql"
    pristine_manifest = _PRISTINE_OUT / "pristine_manifest.json"

    if refresh_pristine or not pristine_sql.is_file():
        log.info("mirror pack: running import-pristine")
        run_import_pristine(mdb=mdb, out_dir=_PRISTINE_OUT, verify=True)

    if not pristine_sql.is_file():
        raise SystemExit(f"L1 mirror missing: {pristine_sql} — run import-pristine first")

    shutil.copy2(pristine_sql, out_dir / "L1_mirror.sql")
    if pristine_manifest.is_file():
        shutil.copy2(pristine_manifest, out_dir / "pristine_manifest.json")

    pristine_meta = json.loads(pristine_manifest.read_text(encoding="utf-8")) if pristine_manifest.is_file() else {}
    files = ["L1_mirror.sql"]
    if pristine_manifest.is_file():
        files.append("pristine_manifest.json")

    pack_manifest = {
        "pack": PACK_MIRROR,
        "layer": PACK_LAYERS[PACK_MIRROR],
        "version": 1,
        "exported_at_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "description": "Full mechanical koatd.mdb mirror — no corrections",
        "source_mdb": str(mdb.resolve()),
        "files": files,
        "table_count": pristine_meta.get("table_count"),
        "rows_total": sum(int(t.get("rows", 0)) for t in (pristine_meta.get("tables") or {}).values()),
    }
    manifest_path = out_dir / "pack_manifest.json"
    manifest_path.write_text(json.dumps(pack_manifest, indent=2) + "\n", encoding="utf-8")

    return {
        "pack": PACK_MIRROR,
        "out_dir": str(out_dir),
        "manifest_path": str(manifest_path),
        "files": files,
        "table_count": pack_manifest.get("table_count"),
    }


def export_pack_mysql(
    pack: str,
    *,
    out_dir: Path,
) -> dict[str, object]:
    """Export Ground / Structure / Product pack from live ko2amiga_db."""
    if pack not in PACK_TABLES:
        raise ValueError(f"unknown mysql pack {pack!r}")

    tables = PACK_TABLES[pack]
    out_dir.mkdir(parents=True, exist_ok=True)

    conn = _connect()
    try:
        row_counts = _table_row_counts(conn, tables)
    finally:
        conn.close()

    cfg = load_amiga_db_config()
    schema_sql = _mysqldump(cfg.database, tables, schema_only=True)
    data_sql = _mysqldump(cfg.database, tables, schema_only=False)

    schema_path = out_dir / "schema.sql"
    data_path = out_dir / "data.sql"
    schema_path.write_text(schema_sql, encoding="utf-8")
    data_path.write_text(data_sql, encoding="utf-8")

    manifest_files = _copy_manifests(
        out_dir,
        include_disposition=pack in (PACK_STRUCTURE, PACK_PRODUCT),
    )

    files = ["schema.sql", "data.sql", *manifest_files]
    pack_manifest = {
        "pack": pack,
        "layer": PACK_LAYERS[pack],
        "version": 1,
        "exported_at_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "database": cfg.database,
        "description": {
            PACK_GROUND: "L3 witness ground — neutral canonical facts (no L5)",
            PACK_STRUCTURE: "L3 + L4 structure overlay — fixtures and lifecycle",
            PACK_PRODUCT: "L3 + L4 + L5 product derived — ratings.kickoff.com parity",
        }[pack],
        "tables": row_counts,
        "ddl_bundles": {
            PACK_GROUND: ["ground"],
            PACK_STRUCTURE: ["ground", "structure"],
            PACK_PRODUCT: ["ground", "structure", "derived"],
        }[pack],
        "files": files,
        "rows_total": sum(row_counts.values()),
    }
    manifest_path = out_dir / "pack_manifest.json"
    manifest_path.write_text(json.dumps(pack_manifest, indent=2) + "\n", encoding="utf-8")

    return {
        "pack": pack,
        "out_dir": str(out_dir),
        "manifest_path": str(manifest_path),
        "tables": len(tables),
        "rows_total": pack_manifest["rows_total"],
        "files": files,
    }


def export_pack(
    pack: str,
    *,
    out_root: Path = _DEFAULT_PACKS_ROOT,
    mdb: Path = _DEFAULT_MDB,
    refresh_pristine: bool = False,
) -> dict[str, object]:
    out_dir = out_root / pack
    if pack == PACK_MIRROR:
        return export_pack_mirror(out_dir=out_dir, mdb=mdb, refresh_pristine=refresh_pristine)
    return export_pack_mysql(pack, out_dir=out_dir)


def export_all_packs(
    *,
    out_root: Path = _DEFAULT_PACKS_ROOT,
    mdb: Path = _DEFAULT_MDB,
    refresh_pristine: bool = False,
) -> list[dict[str, object]]:
    results: list[dict[str, object]] = []
    for pack in ALL_PACKS:
        log.info("export-pack %s → %s", pack, out_root / pack)
        results.append(
            export_pack(pack, out_root=out_root, mdb=mdb, refresh_pristine=refresh_pristine)
        )
    return results
