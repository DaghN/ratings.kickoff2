#!/usr/bin/env python3
"""L2 pruned mirror — drop legacy-derived Access tables from L1 SQL."""

from __future__ import annotations

import json
import logging
import re
from datetime import datetime, timezone
from pathlib import Path

from scripts.amiga.import_pristine import _insert_batches, _quote_ident, _sql_literal

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_L1_DIR = _REPO / "data" / "amiga" / "exports" / "pristine"
_DEFAULT_OUT = _REPO / "data" / "amiga" / "exports" / "pruned"

# Witness-candidate Access tables retained at L2 (uncorrected). Policy §4 L2 / stack doc §4.
L2_RETAIN_TABLES: frozenset[str] = frozenset(
    {
        "Scores",
        "Tournament players",
    }
)

L2_IDENTITY_SOURCE_TABLE = "Rankings"
WITNESS_PLAYER_IDENTITY = "witness_player_identity"
L2_IDENTITY_COLUMNS: tuple[str, str] = ("player", "country")
L2_IDENTITY_SOURCE_COLUMNS: tuple[str, str] = ("Player", "Country")

_TABLE_SECTION = re.compile(r"^-- table: (.+)$", re.MULTILINE)
_INSERT_RANKINGS = re.compile(
    r"INSERT INTO `Rankings` \(([^)]+)\) VALUES\s*(.+?);",
    re.DOTALL,
)
_FORBIDDEN_L2_PATTERNS = (
    re.compile(r"CREATE TABLE IF NOT EXISTS `Rankings`"),
    re.compile(r"CREATE TABLE IF NOT EXISTS `Countries`"),
    re.compile(r"`R0102`"),
)


def classify_prune_reason(table: str) -> str:
    if table == "added_misc":
        return "scratch_ignore"
    if table == "added_players":
        return "legacy_derived_career"
    if table == "Rankings":
        return "legacy_derived_ratings_grid"
    if table == "Countries":
        return "legacy_lookup_list"
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


def _split_sql_tuple_values(inner: str) -> list[object]:
    """Parse one SQL VALUES tuple body (without outer parens) into Python values."""
    values: list[object] = []
    i = 0
    n = len(inner)
    while i < n:
        while i < n and inner[i] in " \t\n\r,":
            i += 1
        if i >= n:
            break
        if inner.startswith("NULL", i) and (i + 4 == n or inner[i + 4] in ",)"):
            values.append(None)
            i += 4
            continue
        if inner[i] == "'":
            i += 1
            chars: list[str] = []
            while i < n:
                ch = inner[i]
                if ch == "'":
                    if i + 1 < n and inner[i + 1] == "'":
                        chars.append("'")
                        i += 2
                        continue
                    i += 1
                    break
                chars.append(ch)
                i += 1
            values.append("".join(chars))
            continue
        start = i
        while i < n and inner[i] not in ",":
            i += 1
        token = inner[start:i].strip()
        if not token:
            continue
        if "." in token:
            values.append(float(token))
        else:
            values.append(int(token))
    return values


def _parse_value_tuple_inners(values_sql: str) -> list[str]:
    tuples: list[str] = []
    depth = 0
    start: int | None = None
    for i, ch in enumerate(values_sql):
        if ch == "(":
            if depth == 0:
                start = i + 1
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0 and start is not None:
                tuples.append(values_sql[start:i])
                start = None
    return tuples


def extract_witness_player_identity(rankings_section: str) -> list[tuple[str, str]]:
    """Extract (player, country) rows from L1 Rankings SQL section."""
    if L2_IDENTITY_SOURCE_TABLE not in rankings_section:
        raise ValueError(f"L1 SQL missing {L2_IDENTITY_SOURCE_TABLE!r} section")

    rows: list[tuple[str, str]] = []
    player_idx: int | None = None
    country_idx: int | None = None

    for match in _INSERT_RANKINGS.finditer(rankings_section):
        col_names = [c.strip().strip("`") for c in match.group(1).split(",")]
        if player_idx is None:
            try:
                player_idx = col_names.index(L2_IDENTITY_SOURCE_COLUMNS[0])
                country_idx = col_names.index(L2_IDENTITY_SOURCE_COLUMNS[1])
            except ValueError as exc:
                raise ValueError(
                    f"{L2_IDENTITY_SOURCE_TABLE} INSERT missing Player/Country columns"
                ) from exc
        elif (
            col_names[player_idx] != L2_IDENTITY_SOURCE_COLUMNS[0]
            or col_names[country_idx] != L2_IDENTITY_SOURCE_COLUMNS[1]
        ):
            raise ValueError(f"{L2_IDENTITY_SOURCE_TABLE} INSERT column order drift")

        for tuple_inner in _parse_value_tuple_inners(match.group(2)):
            vals = _split_sql_tuple_values(tuple_inner)
            if player_idx is None or country_idx is None:
                raise RuntimeError("identity column indices not set")
            if len(vals) <= max(player_idx, country_idx):
                raise ValueError(f"{L2_IDENTITY_SOURCE_TABLE} row too short for identity extract")
            player_raw = vals[player_idx]
            country_raw = vals[country_idx]
            player = "" if player_raw is None else str(player_raw).strip()
            country = "" if country_raw is None else str(country_raw).strip()
            rows.append((player, country))

    if not rows:
        raise ValueError(f"No {L2_IDENTITY_SOURCE_TABLE} INSERT rows found in L1 SQL")
    return rows


def build_witness_player_identity_sql(
    identity_rows: list[tuple[str, str]],
    *,
    batch_size: int = 200,
) -> str:
    table_q = _quote_ident(WITNESS_PLAYER_IDENTITY)
    parts = [
        f"-- witness slice from L1 {L2_IDENTITY_SOURCE_TABLE} (player + country only; rating grid dropped)",
        f"DROP TABLE IF EXISTS {table_q};",
        (
            f"CREATE TABLE IF NOT EXISTS {table_q} (\n"
            f"  {_quote_ident('player')} varchar(255) NOT NULL,\n"
            f"  {_quote_ident('country')} varchar(255) NOT NULL DEFAULT ''\n"
            f") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
        ),
    ]
    if identity_rows:
        parts.extend(
            _insert_batches(
                WITNESS_PLAYER_IDENTITY,
                list(L2_IDENTITY_COLUMNS),
                identity_rows,
                batch_size=batch_size,
            )
        )
    return "\n".join(parts)


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

    if L2_IDENTITY_SOURCE_TABLE not in l1_tables:
        raise ValueError(f"L1 mirror missing {L2_IDENTITY_SOURCE_TABLE!r} for identity extract")

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

    identity_rows = extract_witness_player_identity(sections[L2_IDENTITY_SOURCE_TABLE])
    l1_rankings_rows = int(l1_tables[L2_IDENTITY_SOURCE_TABLE]["rows"])
    if len(identity_rows) != l1_rankings_rows:
        raise ValueError(
            f"identity extract row count {len(identity_rows)} != L1 "
            f"{L2_IDENTITY_SOURCE_TABLE} rows {l1_rankings_rows}"
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
            entry: dict[str, object] = {
                "table": table,
                "rows": rows,
                "reason": classify_prune_reason(table),
            }
            if table == L2_IDENTITY_SOURCE_TABLE:
                entry["note"] = f"identity → {WITNESS_PLAYER_IDENTITY}"
            pruned.append(entry)

    retained_stats[WITNESS_PLAYER_IDENTITY] = {
        "rows": len(identity_rows),
        "columns": len(L2_IDENTITY_COLUMNS),
        "source_table": L2_IDENTITY_SOURCE_TABLE,
        "source_columns": list(L2_IDENTITY_SOURCE_COLUMNS),
    }

    extracted_from_l1: list[dict[str, object]] = [
        {
            "source_table": L2_IDENTITY_SOURCE_TABLE,
            "witness_table": WITNESS_PLAYER_IDENTITY,
            "columns": list(L2_IDENTITY_COLUMNS),
            "rows": len(identity_rows),
            "reason": "identity_slice; rating_grid_dropped",
        }
    ]

    out_dir.mkdir(parents=True, exist_ok=True)
    sql_path = out_dir / sql_name

    sql_parts = [
        "-- L2 pruned witness — Scores + catalog + witness_player_identity (policy v3 / slice 9).",
        "-- Policy: docs/amiga-ground-stack.md §4",
        f"-- Source L1: {l1_sql_path.name} + {l1_manifest_path.name}",
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS = 0;",
    ]
    for table in sorted(retain):
        sql_parts.append("")
        sql_parts.append(sections[table])
    sql_parts.append("")
    sql_parts.append(build_witness_player_identity_sql(identity_rows))
    sql_parts.append("")
    sql_parts.append("SET FOREIGN_KEY_CHECKS = 1;")
    sql_path.write_text("\n".join(sql_parts) + "\n", encoding="utf-8")

    witness_rows = len(identity_rows)
    access_rows_kept = sum(int(t["rows"]) for t in retained_stats.values() if t.get("source_table") is None)
    manifest_path = out_dir / "prune_manifest.json"
    manifest: dict[str, object] = {
        "layer": "L2",
        "description": (
            "Hard-pruned witness SQL — legacy derived tables dropped; "
            f"{WITNESS_PLAYER_IDENTITY} extracted from {L2_IDENTITY_SOURCE_TABLE}"
        ),
        "source_l1": {
            "manifest": str(l1_manifest_path.resolve()),
            "sql_file": l1_sql_path.name,
            "table_count": l1_manifest.get("table_count"),
        },
        "pruned_at_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "sql_file": sql_name,
        "sql_bytes": sql_path.stat().st_size,
        "retain_tables": sorted(retain),
        "witness_tables": [WITNESS_PLAYER_IDENTITY],
        "table_count": len(retained_stats),
        "tables": retained_stats,
        "extracted_from_l1": extracted_from_l1,
        "pruned_from_l1": pruned,
        "pruned_table_count": len(pruned),
        "rows_retained_access": access_rows_kept,
        "rows_witness_identity": witness_rows,
        "rows_retained": access_rows_kept + witness_rows,
        "rows_pruned": sum(int(p["rows"]) for p in pruned),
    }
    manifest_path.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )

    return {
        "retained_tables": len(retain),
        "witness_identity_rows": witness_rows,
        "pruned_tables": len(pruned),
        "rows_retained": manifest["rows_retained"],
        "rows_pruned": manifest["rows_pruned"],
        "sql_path": str(sql_path),
        "manifest_path": str(manifest_path),
    }


def verify_prune_sql(l2_sql_path: Path) -> list[str]:
    errors: list[str] = []
    if not l2_sql_path.is_file():
        return [f"L2 SQL missing: {l2_sql_path}"]
    text = l2_sql_path.read_text(encoding="utf-8")
    if f"CREATE TABLE IF NOT EXISTS `{WITNESS_PLAYER_IDENTITY}`" not in text:
        errors.append(f"L2 SQL missing {WITNESS_PLAYER_IDENTITY} table")
    for pattern in _FORBIDDEN_L2_PATTERNS:
        if pattern.search(text):
            errors.append(f"L2 SQL forbidden pattern: {pattern.pattern}")
    return errors


def verify_prune_manifest(
    l1_manifest_path: Path,
    prune_manifest_path: Path,
    *,
    retain: frozenset[str] = L2_RETAIN_TABLES,
    l2_sql_path: Path | None = None,
) -> list[str]:
    errors: list[str] = []
    if not l1_manifest_path.is_file():
        return [f"L1 manifest missing: {l1_manifest_path}"]
    if not prune_manifest_path.is_file():
        return [f"L2 prune manifest missing: {prune_manifest_path}"]

    l1 = _load_l1_manifest(l1_manifest_path)
    l2 = json.loads(prune_manifest_path.read_text(encoding="utf-8"))
    l1_tables = set(l1.get("tables", {}))
    l2_tables_meta: dict[str, dict[str, object]] = l2.get("tables", {})
    retained_l1 = set(l2_tables_meta) & l1_tables
    pruned_names = {str(p["table"]) for p in l2.get("pruned_from_l1", [])}

    if retained_l1 != retain & l1_tables:
        errors.append(
            f"retained L1 tables mismatch: got {sorted(retained_l1)}, "
            f"want {sorted(retain & l1_tables)}"
        )

    if WITNESS_PLAYER_IDENTITY not in l2_tables_meta:
        errors.append(f"missing witness table in manifest: {WITNESS_PLAYER_IDENTITY}")
    elif L2_IDENTITY_SOURCE_TABLE in retained_l1:
        errors.append(f"full {L2_IDENTITY_SOURCE_TABLE} must not be retained at L2")

    if "Countries" in retained_l1:
        errors.append("Countries must not be retained at L2")

    if retained_l1 & pruned_names:
        errors.append(f"tables both retained and pruned: {sorted(retained_l1 & pruned_names)}")

    if retained_l1 | pruned_names != l1_tables:
        errors.append(
            "L2 partition does not cover L1: "
            f"missing={sorted(l1_tables - retained_l1 - pruned_names)} "
            f"extra={sorted((retained_l1 | pruned_names) - l1_tables)}"
        )

    extracted = l2.get("extracted_from_l1", [])
    if not extracted:
        errors.append("manifest missing extracted_from_l1")
    else:
        match = [
            e
            for e in extracted
            if e.get("source_table") == L2_IDENTITY_SOURCE_TABLE
            and e.get("witness_table") == WITNESS_PLAYER_IDENTITY
        ]
        if not match:
            errors.append(
                f"extracted_from_l1 missing {L2_IDENTITY_SOURCE_TABLE} → {WITNESS_PLAYER_IDENTITY}"
            )

    rankings_entry = [
        p for p in l2.get("pruned_from_l1", []) if str(p.get("table")) == L2_IDENTITY_SOURCE_TABLE
    ]
    if not rankings_entry:
        errors.append(f"pruned_from_l1 missing {L2_IDENTITY_SOURCE_TABLE}")
    countries_entry = [p for p in l2.get("pruned_from_l1", []) if str(p.get("table")) == "Countries"]
    if l1_tables >= {"Countries"} and not countries_entry:
        errors.append("pruned_from_l1 missing Countries")

    for table, meta in l2_tables_meta.items():
        if table not in l1_tables:
            if table != WITNESS_PLAYER_IDENTITY:
                errors.append(f"unexpected L2-only table: {table}")
            continue
        l1_rows = int(l1["tables"][table]["rows"])
        l2_rows = int(meta["rows"])
        if l1_rows != l2_rows:
            errors.append(f"{table}: L1 rows={l1_rows}, L2 manifest rows={l2_rows}")

    if WITNESS_PLAYER_IDENTITY in l2_tables_meta:
        witness_rows = int(l2_tables_meta[WITNESS_PLAYER_IDENTITY]["rows"])
        l1_rankings_rows = int(l1["tables"][L2_IDENTITY_SOURCE_TABLE]["rows"])
        if witness_rows != l1_rankings_rows:
            errors.append(
                f"{WITNESS_PLAYER_IDENTITY}: rows={witness_rows}, "
                f"L1 {L2_IDENTITY_SOURCE_TABLE} rows={l1_rankings_rows}"
            )

    for entry in l2.get("pruned_from_l1", []):
        table = str(entry["table"])
        if table not in l1_tables:
            errors.append(f"pruned unknown table: {table}")
            continue
        l1_rows = int(l1["tables"][table]["rows"])
        if int(entry["rows"]) != l1_rows:
            errors.append(f"{table}: pruned rows={entry['rows']}, L1 rows={l1_rows}")

    sql_path = l2_sql_path
    if sql_path is None:
        sql_name = str(l2.get("sql_file", "L2_pruned.sql"))
        sql_path = prune_manifest_path.parent / sql_name
    errors.extend(verify_prune_sql(sql_path))

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
        errors = verify_prune_manifest(
            l1_manifest_path,
            Path(stats["manifest_path"]),
            l2_sql_path=Path(stats["sql_path"]),
        )
        if errors:
            raise SystemExit("L2 prune verify failed:\n  " + "\n  ".join(errors))
    return stats
