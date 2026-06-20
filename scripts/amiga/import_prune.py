#!/usr/bin/env python3
"""L2 pruned mirror — drop legacy-derived Access tables from L1 SQL."""

from __future__ import annotations

import json
import logging
import re
from datetime import datetime, timezone
from pathlib import Path

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_L1_DIR = _REPO / "data" / "amiga" / "exports" / "pristine"
_DEFAULT_OUT = _REPO / "data" / "amiga" / "exports" / "pruned"

# Witness-candidate tables retained at L2 (still uncorrected). Policy §4 L2.
L2_RETAIN_TABLES: frozenset[str] = frozenset(
    {
        "Scores",
        "Tournament players",
        "Countries",
    }
)

_TABLE_SECTION = re.compile(r"^-- table: (.+)$", re.MULTILINE)


def classify_prune_reason(table: str) -> str:
    if table == "added_misc":
        return "scratch_ignore"
    if table == "added_players":
        return "legacy_derived_career"
    if table == "Rankings":
        return "legacy_derived_ratings"
    if table == "Tables":
        return "legacy_derived_standings"
    if table in ("Paste Errors", "Scores$_ImportErrors"):
        return "import_artefact"
    if table.endswith(" Tables") or table.endswith(" Table"):
        return "legacy_derived_standings"
    return "legacy_derived_other"


def split_l1_sql_sections(sql_text: str) -> tuple[str, dict[str, str]]:
    """Split L1_mirror.sql into header preamble and per-table bodies."""
    matches = list(_TABLE_SECTION.finditer(sql_text))
    if not matches:
        raise ValueError("L1 SQL has no '-- table:' sections — run import-pristine first")

    header = sql_text[: matches[0].start()].rstrip()
    sections: dict[str, str] = {}
    for i, match in enumerate(matches):
        name = match.group(1)
        start = match.start()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(sql_text)
        body = sql_text[start:end].rstrip()
        if body.endswith("SET FOREIGN_KEY_CHECKS = 1;"):
            body = body[: -len("SET FOREIGN_KEY_CHECKS = 1;")].rstrip()
        sections[name] = body
    return header, sections


def _load_l1_manifest(path: Path) -> dict[str, object]:
    if not path.is_file():
        raise FileNotFoundError(path)
    return json.loads(path.read_text(encoding="utf-8"))


def prune_l1_mirror(
    *,
    l1_manifest_path: Path,
    l1_sql_path: Path,
    out_dir: Path,
    sql_name: str = "L2_pruned.sql",
    retain: frozenset[str] = L2_RETAIN_TABLES,
) -> dict[str, object]:
    l1_manifest = _load_l1_manifest(l1_manifest_path)
    l1_tables: dict[str, dict[str, object]] = l1_manifest.get("tables", {})
    if not l1_tables:
        raise ValueError(f"L1 manifest has no tables: {l1_manifest_path}")

    missing_retain = retain - set(l1_tables)
    if missing_retain:
        raise ValueError(f"L1 mirror missing witness tables: {sorted(missing_retain)}")

    sql_text = l1_sql_path.read_text(encoding="utf-8")
    _header, sections = split_l1_sql_sections(sql_text)
    if set(sections) != set(l1_tables):
        only_sql = set(sections) - set(l1_tables)
        only_manifest = set(l1_tables) - set(sections)
        raise ValueError(
            "L1 SQL sections != manifest tables"
            f" sql_only={sorted(only_sql)} manifest_only={sorted(only_manifest)}"
        )

    pruned: list[dict[str, object]] = []
    retained_stats: dict[str, dict[str, object]] = {}

    for table in sorted(l1_tables):
        meta = l1_tables[table]
        rows = int(meta["rows"])
        if table in retain:
            retained_stats[table] = {
                "rows": rows,
                "columns": meta.get("columns"),
            }
        else:
            pruned.append(
                {
                    "table": table,
                    "rows": rows,
                    "reason": classify_prune_reason(table),
                }
            )

    out_dir.mkdir(parents=True, exist_ok=True)
    sql_path = out_dir / sql_name

    sql_parts = [
        "-- L2 pruned mirror — witness-candidate Access tables only (hard drop, no sidecar).",
        "-- Policy: docs/amiga-ground-layers-policy.md §4 L2",
        f"-- Source L1: {l1_sql_path.name} + {l1_manifest_path.name}",
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS = 0;",
    ]
    for table in sorted(retain):
        sql_parts.append("")
        sql_parts.append(sections[table])
    sql_parts.append("")
    sql_parts.append("SET FOREIGN_KEY_CHECKS = 1;")
    sql_path.write_text("\n".join(sql_parts) + "\n", encoding="utf-8")

    manifest_path = out_dir / "prune_manifest.json"
    manifest: dict[str, object] = {
        "layer": "L2",
        "description": "Hard-pruned witness candidates — legacy derived tables omitted (see pruned_from_l1)",
        "source_l1": {
            "manifest": str(l1_manifest_path.resolve()),
            "sql_file": l1_sql_path.name,
            "table_count": l1_manifest.get("table_count"),
        },
        "pruned_at_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "sql_file": sql_name,
        "sql_bytes": sql_path.stat().st_size,
        "retain_tables": sorted(retain),
        "table_count": len(retained_stats),
        "tables": retained_stats,
        "pruned_from_l1": pruned,
        "pruned_table_count": len(pruned),
        "rows_retained": sum(int(t["rows"]) for t in retained_stats.values()),
        "rows_pruned": sum(int(p["rows"]) for p in pruned),
    }
    manifest_path.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    return {
        "retained_tables": len(retained_stats),
        "pruned_tables": len(pruned),
        "rows_retained": manifest["rows_retained"],
        "rows_pruned": manifest["rows_pruned"],
        "sql_path": str(sql_path),
        "manifest_path": str(manifest_path),
    }


def verify_prune_manifest(
    l1_manifest_path: Path,
    prune_manifest_path: Path,
    *,
    retain: frozenset[str] = L2_RETAIN_TABLES,
) -> list[str]:
    errors: list[str] = []
    if not l1_manifest_path.is_file():
        return [f"L1 manifest missing: {l1_manifest_path}"]
    if not prune_manifest_path.is_file():
        return [f"L2 prune manifest missing: {prune_manifest_path}"]

    l1 = _load_l1_manifest(l1_manifest_path)
    l2 = json.loads(prune_manifest_path.read_text(encoding="utf-8"))
    l1_tables = set(l1.get("tables", {}))
    retained = set(l2.get("tables", {}))
    pruned_names = {str(p["table"]) for p in l2.get("pruned_from_l1", [])}

    if retained != retain & l1_tables:
        errors.append(f"retained tables mismatch: got {sorted(retained)}, want {sorted(retain & l1_tables)}")

    if retained & pruned_names:
        errors.append(f"tables both retained and pruned: {sorted(retained & pruned_names)}")

    if retained | pruned_names != l1_tables:
        errors.append(
            "L2 partition does not cover L1: "
            f"missing={sorted(l1_tables - retained - pruned_names)} "
            f"extra={sorted((retained | pruned_names) - l1_tables)}"
        )

    for table, meta in l2.get("tables", {}).items():
        l1_rows = int(l1["tables"][table]["rows"])
        l2_rows = int(meta["rows"])
        if l1_rows != l2_rows:
            errors.append(f"{table}: L1 rows={l1_rows}, L2 manifest rows={l2_rows}")

    for entry in l2.get("pruned_from_l1", []):
        table = str(entry["table"])
        if table not in l1_tables:
            errors.append(f"pruned unknown table: {table}")
            continue
        l1_rows = int(l1["tables"][table]["rows"])
        if int(entry["rows"]) != l1_rows:
            errors.append(f"{table}: pruned rows={entry['rows']}, L1 rows={l1_rows}")

    return errors


def run_import_prune(
    *,
    l1_dir: Path,
    out_dir: Path,
    l1_manifest_name: str = "pristine_manifest.json",
    l1_sql_name: str = "L1_mirror.sql",
    verify: bool = True,
) -> dict[str, object]:
    l1_manifest_path = l1_dir / l1_manifest_name
    l1_sql_path = l1_dir / l1_sql_name
    if not l1_sql_path.is_file():
        raise FileNotFoundError(l1_sql_path)

    stats = prune_l1_mirror(
        l1_manifest_path=l1_manifest_path,
        l1_sql_path=l1_sql_path,
        out_dir=out_dir,
    )
    if verify:
        errors = verify_prune_manifest(l1_manifest_path, Path(stats["manifest_path"]))
        if errors:
            raise SystemExit("L2 prune verify failed:\n  " + "\n  ".join(errors))
    return stats
