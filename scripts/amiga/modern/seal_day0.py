"""Seal day 0 L3 witness ground from ko2amiga_db (D0-1)."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Final

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_manifest import default_manifest_path
from scripts.work_prepare.paths import find_mysqldump_exe

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_DEFAULT_OUT = _REPO / "data" / "amiga" / "day0"
_ORACLE_DB: Final[str] = "ko2amiga_db"

# Policy amiga-modern-ground-platform.md SS7.1 — L3 witness tables only.
DAY0_TABLES: Final[tuple[str, ...]] = (
    "tournament_format_templates",
    "tournaments",
    "amiga_players",
    "amiga_tournament_finish_override",
    "amiga_games",
)

_GAMES_CHUNK_SIZE: Final[int] = 5000


def _connect_oracle() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database != _ORACLE_DB:
        raise SystemExit(
            f"Refusing seal-day0: source must be {_ORACLE_DB!r}, got {cfg.database!r}"
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


def _mysqldump(database: str, tables: tuple[str, ...], *, schema_only: bool, where: str | None = None) -> str:
    dump_exe = find_mysqldump_exe()
    args = [str(dump_exe), "-u", "root", "--single-transaction", "--set-gtid-purged=OFF"]
    if schema_only:
        args.append("--no-data")
    else:
        args.append("--no-create-info")
    if where:
        args.append(f"--where={where}")
    args.append(database)
    args.extend(tables)
    proc = subprocess.run(args, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if proc.returncode != 0:
        raise SystemExit(f"mysqldump failed ({proc.returncode}): {proc.stderr.strip() or proc.stdout[:500]}")
    return proc.stdout


def _table_row_counts(conn: pymysql.connections.Connection) -> dict[str, int]:
    counts: dict[str, int] = {}
    with conn.cursor() as cur:
        for table in DAY0_TABLES:
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            counts[table] = int(cur.fetchone()["n"])
    return counts


def _copy_witness_manifests(out_dir: Path) -> list[str]:
    copied: list[str] = []
    manifest_dir = out_dir / "manifests"
    manifest_dir.mkdir(parents=True, exist_ok=True)

    import_manifest = default_manifest_path(_REPO)
    if import_manifest.is_file():
        dest = manifest_dir / "import_manifest.json"
        shutil.copy2(import_manifest, dest)
        copied.append(str(dest.relative_to(out_dir).as_posix()))

    name_merges = _REPO / "data" / "amiga" / "exports" / "name_merges.json"
    if name_merges.is_file():
        dest = manifest_dir / "name_merges.json"
        shutil.copy2(name_merges, dest)
        copied.append(str(dest.relative_to(out_dir).as_posix()))

    return copied


def _write_sql(path: Path, sql: str) -> None:
    path.write_text(sql, encoding="utf-8")


def _git_short_head() -> str | None:
    try:
        proc = subprocess.run(
            ["git", "rev-parse", "--short", "HEAD"],
            cwd=_REPO,
            capture_output=True,
            text=True,
            encoding="utf-8",
            check=False,
        )
        if proc.returncode == 0:
            return proc.stdout.strip() or None
    except OSError:
        pass
    return None


def seal_day0(*, out_dir: Path = _DEFAULT_OUT, version: str | None = None) -> dict[str, object]:
    """Export L3 witness tables from ko2amiga_db to data/amiga/day0/."""
    out_dir.mkdir(parents=True, exist_ok=True)
    stamp = date.today().isoformat()
    version = version or f"day0-{stamp}"

    conn = _connect_oracle()
    try:
        row_counts = _table_row_counts(conn)
        with conn.cursor() as cur:
            cur.execute("SELECT COALESCE(MAX(id), 0) AS n FROM amiga_games")
            max_game_id = int(cur.fetchone()["n"])
    finally:
        conn.close()

    sql_parts: list[str] = []

    schema_name = "day0_01_schema.sql"
    _write_sql(out_dir / schema_name, _mysqldump(_ORACLE_DB, DAY0_TABLES, schema_only=True))
    sql_parts.append(schema_name)

    small_tables = (
        ("day0_02_format_templates.sql", ("tournament_format_templates",)),
        ("day0_03_tournaments.sql", ("tournaments",)),
        ("day0_04_players.sql", ("amiga_players",)),
        ("day0_05_finish_override.sql", ("amiga_tournament_finish_override",)),
    )
    for filename, tables in small_tables:
        _write_sql(out_dir / filename, _mysqldump(_ORACLE_DB, tables, schema_only=False))
        sql_parts.append(filename)

    part_idx = 6
    for start in range(1, max_game_id + 1, _GAMES_CHUNK_SIZE):
        end = min(start + _GAMES_CHUNK_SIZE - 1, max_game_id)
        filename = f"day0_{part_idx:02d}_games_{start}_{end}.sql"
        where = f"id >= {start} AND id <= {end}"
        _write_sql(
            out_dir / filename,
            _mysqldump(_ORACLE_DB, ("amiga_games",), schema_only=False, where=where),
        )
        sql_parts.append(filename)
        part_idx += 1

    witness_manifests = _copy_witness_manifests(out_dir)
    git_head = _git_short_head()

    manifest = {
        "version": version,
        "layer": "L3",
        "generated_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "source_database": _ORACLE_DB,
        "source_note": (
            "Sealed witness ground from last legacy prove on ko2amiga_db "
            f"(git {git_head})" if git_head else "Sealed witness ground from ko2amiga_db"
        ),
        "oracle_frozen": True,
        "oracle_note": "ko2amiga_db is parity oracle (P-1) until promote; do not use as work seed",
        "tournament_count": row_counts["tournaments"],
        "player_count": row_counts["amiga_players"],
        "game_count": row_counts["amiga_games"],
        "tables": row_counts,
        "rows_total": sum(row_counts.values()),
        "excluded": {
            "L4_structure": [
                "tournament_entrants",
                "tournament_stages",
                "tournament_stage_players",
                "tournament_fixtures",
            ],
            "L5_derived": "all derived tables — rebuilt by simul on ko2amiga_work",
            "video": "editorial manifests — aligned on work after simul",
        },
        "sql_parts": sql_parts,
        "witness_manifests": witness_manifests,
        "policy": "docs/amiga-modern-ground-platform.md section 7",
    }
    manifest_path = out_dir / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")

    log.info(
        "seal-day0 %s: tournaments=%s players=%s games=%s parts=%s -> %s",
        version,
        row_counts["tournaments"],
        row_counts["amiga_players"],
        row_counts["amiga_games"],
        len(sql_parts),
        out_dir,
    )

    return {
        "version": version,
        "out_dir": str(out_dir),
        "manifest_path": str(manifest_path),
        "sql_parts": len(sql_parts),
        "row_counts": row_counts,
    }