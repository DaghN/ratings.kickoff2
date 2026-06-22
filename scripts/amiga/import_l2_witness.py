#!/usr/bin/env python3
"""Load L2 pruned witness SQL for L3 import (slice 10 — strict stack)."""

from __future__ import annotations

import re
from datetime import date, datetime
from pathlib import Path

from scripts.amiga.import_prune import (
    WITNESS_PLAYER_IDENTITY,
    split_l1_sql_sections,
    _parse_value_tuple_inners,
    _split_sql_tuple_values,
)
from scripts.amiga.player_names import normalize_display_name

_REPO = Path(__file__).resolve().parents[2]
DEFAULT_L2_DIR = _REPO / "data" / "amiga" / "exports" / "pruned"
DEFAULT_L2_SQL = "L2_pruned.sql"
DEFAULT_PRUNE_MANIFEST = "prune_manifest.json"

_L2_SCORES_TABLE = "Scores"
_L2_TOURNAMENT_TABLE = "Tournament players"

_INSERT_TABLE = re.compile(
    r"INSERT INTO `([^`]+)` \(([^)]+)\) VALUES\s*(.+?);",
    re.DOTALL,
)


def l2_paths(l2_dir: Path) -> tuple[Path, Path]:
    sql_path = l2_dir / DEFAULT_L2_SQL
    manifest_path = l2_dir / DEFAULT_PRUNE_MANIFEST
    return sql_path, manifest_path


def l2_source_metadata(
    l2_sql_path: Path,
    *,
    prune_manifest_path: Path | None = None,
) -> dict[str, object]:
    from datetime import timezone

    stat = l2_sql_path.stat()
    modified = (
        datetime.fromtimestamp(stat.st_mtime, tz=timezone.utc).isoformat().replace("+00:00", "Z")
    )
    source: dict[str, object] = {
        "layer": "L2",
        "path": str(l2_sql_path.resolve()),
        "filename": l2_sql_path.name,
        "size_bytes": stat.st_size,
        "modified_utc": modified,
    }
    if prune_manifest_path and prune_manifest_path.is_file():
        source["prune_manifest"] = str(prune_manifest_path.resolve())
    return source


def _coerce_event_date(value: object) -> date | datetime | None:
    if value is None:
        return None
    if isinstance(value, datetime):
        return value
    if isinstance(value, date):
        return value
    text = str(value).strip()
    if not text:
        return None
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d"):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue
    raise ValueError(f"unparseable tournament Date: {value!r}")


def _parse_sql_table_inserts(section: str, table: str) -> list[dict[str, object]]:
    rows_out: list[dict[str, object]] = []
    needle = f"INSERT INTO `{table}`"
    for match in _INSERT_TABLE.finditer(section):
        if match.group(1) != table:
            continue
        col_names = [c.strip().strip("`") for c in match.group(2).split(",")]
        for tuple_inner in _parse_value_tuple_inners(match.group(3)):
            vals = _split_sql_tuple_values(tuple_inner)
            if len(vals) != len(col_names):
                raise ValueError(
                    f"{table}: column count {len(col_names)} != value count {len(vals)}"
                )
            rows_out.append({col_names[i]: vals[i] for i in range(len(col_names))})
    if needle in section and not rows_out:
        raise ValueError(f"{table}: INSERT present but no rows parsed")
    return rows_out


def load_l2_tournaments(section: str) -> list[dict]:
    rows = _parse_sql_table_inserts(section, _L2_TOURNAMENT_TABLE)
    out: list[dict] = []
    for row in rows:
        out.append(
            {
                "source_id": int(row["ID"]),
                "name": str(row["Tournament"]).strip(),
                "chrono": float(row["Chrono"]) if row["Chrono"] is not None else None,
                "event_date": _coerce_event_date(row["Date"]),
                "is_cup": bool(row["Cup?"]),
                "country": row["Country"],
                "equal_teams": bool(row["EqualTeams"]),
                "player_count": int(row["Players"]) if row["Players"] is not None else None,
            }
        )
    return out


def load_l2_scores(section: str) -> list:
    from scripts.amiga.import_access import AccessScore

    rows = _parse_sql_table_inserts(section, _L2_SCORES_TABLE)
    out: list[AccessScore] = []
    for row in rows:
        extra_raw = row.get("Extra")
        out.append(
            AccessScore(
                source_id=int(row["ID"]),
                team_a=normalize_display_name(str(row["Team A"])),
                team_b=normalize_display_name(str(row["Team B"])),
                goals_a=int(row["A"]),
                goals_b=int(row["B"]),
                raw_tournament=str(row["Tournament"]).strip() if row["Tournament"] else "",
                phase=str(row["Phase"]).strip() if row.get("Phase") else None,
                extra=str(extra_raw).strip() if extra_raw else None,
            )
        )
    return out


def load_l2_player_identity(l2_sql_text: str) -> dict[str, str]:
    pattern = re.compile(
        rf"INSERT INTO `{WITNESS_PLAYER_IDENTITY}` \(`player`, `country`\) VALUES\s*(.+?);",
        re.DOTALL,
    )
    out: dict[str, str] = {}
    for match in pattern.finditer(l2_sql_text):
        for tuple_inner in _parse_value_tuple_inners(match.group(1)):
            vals = _split_sql_tuple_values(tuple_inner)
            if len(vals) < 2:
                raise ValueError(f"{WITNESS_PLAYER_IDENTITY}: short identity row")
            name = normalize_display_name(str(vals[0] or ""))
            country = str(vals[1] or "").strip() if vals[1] is not None else ""
            if name:
                out[name] = country
    if not out:
        raise ValueError(f"L2 SQL has no {WITNESS_PLAYER_IDENTITY} rows")
    return out


def load_l2_witness_inputs(l2_dir: Path) -> tuple[dict[str, object], list[dict], list[AccessScore], dict[str, str]]:
    """Return (source metadata, tournaments, scores, player→country)."""
    sql_path, manifest_path = l2_paths(l2_dir)
    if not sql_path.is_file():
        raise FileNotFoundError(sql_path)

    text = sql_path.read_text(encoding="utf-8")
    _header, sections = split_l1_sql_sections(text)
    for required in (_L2_SCORES_TABLE, _L2_TOURNAMENT_TABLE):
        if required not in sections:
            raise ValueError(f"L2 SQL missing {required!r} — run import-prune first")

    tournaments = load_l2_tournaments(sections[_L2_TOURNAMENT_TABLE])
    scores = load_l2_scores(sections[_L2_SCORES_TABLE])
    countries = load_l2_player_identity(text)
    source = l2_source_metadata(sql_path, prune_manifest_path=manifest_path)
    return source, tournaments, scores, countries
