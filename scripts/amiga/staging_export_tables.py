"""Canonical Amiga staging export table list (local work -> staged ko2amiga_db, pull dump).

Single source of truth for:
- ``scripts/lib/Export-Ko2AmigaStaging.ps1`` — schema + **all multipart data parts**
  (iterates this list; only special-case = chunked ``amiga_games`` / ``amiga_game_ratings``)
- ``amiga_backup_seal_lib.php`` (L5 seals) — same JSON-driven data parts
- ``amiga_staging_export_lib.php`` (pull mysqldump / table list)

Regenerate JSON before export: ``python -m scripts.amiga write-staging-export-tables``
Preflight gate: ``python -m scripts.amiga audit-staging-export --database ko2amiga_work``

Do **not** maintain a second hardcoded dump table list in PS1 or PHP seal code.
"""

from __future__ import annotations

import json
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Final

from scripts.amiga.schema_bundles import DERIVED_SQL, GROUND_SQL, STRUCTURE_SQL

_REPO = Path(__file__).resolve().parents[2]
_STAGING_EXPORT_JSON = (
    _REPO / "site" / "public_html" / "data" / "amiga" / "staging_export_tables.json"
)

_NON_AMIGA_PRODUCT: Final[frozenset[str]] = frozenset({"ratedresults", "playertable"})

RETIRED_STAGING_EXPORT_TABLES: Final[frozenset[str]] = frozenset(
    {
        "amiga_player_stats",
        "amiga_player_tournament_participation",
        "amiga_player_tournament_totals",
        "amiga_rating_events",
    }
)

STAGING_EXPORT_TABLES: Final[tuple[str, ...]] = (
    "tournament_format_templates",
    "tournaments",
    "amiga_players",
    "amiga_tournament_finish_override",
    "tournament_entrants",
    "tournament_stages",
    "tournament_stage_scoring_steps",
    "tournament_stage_players",
    "tournament_fixtures",
    "amiga_games",
    "amiga_game_ratings",
    "amiga_player_event_snapshots",
    "amiga_player_current",
    "amiga_player_elo_rank_at_event",
    "amiga_player_inverse_count_at_event",
    "amiga_player_matchup_at_event",
    "amiga_player_matchup_summary",
    "amiga_tournament_standings",
    "amiga_tournament_catalog_stats",
    "amiga_generalstats",
    "amiga_realm_snapshots",
    "amiga_community_stats",
    "amiga_community_stats_snapshots",
    "amiga_community_stat_facts",
    "amiga_world_cup_stats",
    "amiga_player_slice_totals",
    "amiga_player_slice_at_event",
    "amiga_country_slice_totals",
    "amiga_country_slice_at_event",
    "amiga_wc_hof_snapshots",
    "amiga_wc_hof_present",
)

_CREATE_TABLE_RE = re.compile(r"CREATE TABLE IF NOT EXISTS `([^`]+)`", re.IGNORECASE)


def _parse_create_tables_from_sql_paths(paths: tuple[Path, ...]) -> frozenset[str]:
    found: set[str] = set()
    for path in paths:
        text = path.read_text(encoding="utf-8")
        found.update(_CREATE_TABLE_RE.findall(text))
    return frozenset(found)


def bundle_product_tables() -> frozenset[str]:
    all_paths = GROUND_SQL + STRUCTURE_SQL + DERIVED_SQL
    return (
        _parse_create_tables_from_sql_paths(all_paths)
        - _NON_AMIGA_PRODUCT
        - RETIRED_STAGING_EXPORT_TABLES
    )


def validate_staging_export_registry() -> list[str]:
    errors: list[str] = []
    export_set = frozenset(STAGING_EXPORT_TABLES)
    bundle_set = bundle_product_tables()

    missing_from_export = sorted(bundle_set - export_set)
    if missing_from_export:
        errors.append(
            "STAGING_EXPORT_TABLES missing bundle product table(s): "
            + ", ".join(missing_from_export)
        )

    extra_in_export = sorted(export_set - bundle_set)
    if extra_in_export:
        errors.append(
            "STAGING_EXPORT_TABLES has table(s) not in schema bundles: "
            + ", ".join(extra_in_export)
        )

    if len(STAGING_EXPORT_TABLES) != len(export_set):
        errors.append("STAGING_EXPORT_TABLES contains duplicate names")

    retired_in_export = sorted(export_set & RETIRED_STAGING_EXPORT_TABLES)
    if retired_in_export:
        errors.append(
            "Retired table(s) still in STAGING_EXPORT_TABLES: " + ", ".join(retired_in_export)
        )

    return errors


def staging_export_manifest_payload() -> dict[str, object]:
    registry_errors = validate_staging_export_registry()
    if registry_errors:
        raise RuntimeError("staging export registry invalid:\n  - " + "\n  - ".join(registry_errors))

    return {
        "schema_version": 1,
        "generated": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "tables": list(STAGING_EXPORT_TABLES),
        "retired": sorted(RETIRED_STAGING_EXPORT_TABLES),
        "source": "scripts/amiga/staging_export_tables.py",
    }


def write_staging_export_manifest(path: Path | None = None) -> Path:
    out = path or _STAGING_EXPORT_JSON
    out.parent.mkdir(parents=True, exist_ok=True)
    payload = staging_export_manifest_payload()
    text = json.dumps(payload, indent=2) + "\n"
    out.write_text(text, encoding="utf-8")
    return out


def load_staging_export_tables_from_json(path: Path | None = None) -> list[str]:
    manifest_path = path or _STAGING_EXPORT_JSON
    payload = json.loads(manifest_path.read_text(encoding="utf-8"))
    tables = payload.get("tables")
    if not isinstance(tables, list) or not tables:
        raise ValueError(f"invalid tables in {manifest_path}")
    return [str(t) for t in tables]


def audit_staging_export(
    *,
    database: str = "ko2amiga_work",
    mysql_exe: Path | None = None,
    skip_db: bool = False,
) -> list[str]:
    errors: list[str] = []
    errors.extend(validate_staging_export_registry())

    canonical = frozenset(STAGING_EXPORT_TABLES)
    json_path = _STAGING_EXPORT_JSON
    if not json_path.is_file():
        errors.append(
            f"missing manifest JSON: {json_path} (run write-staging-export-tables)"
        )
    else:
        try:
            json_tables = frozenset(load_staging_export_tables_from_json(json_path))
        except (OSError, ValueError, json.JSONDecodeError) as exc:
            errors.append(f"invalid manifest JSON: {exc}")
            json_tables = frozenset()
        if json_tables and json_tables != canonical:
            missing = sorted(canonical - json_tables)
            extra = sorted(json_tables - canonical)
            if missing:
                errors.append(f"JSON manifest missing table(s): {', '.join(missing)}")
            if extra:
                errors.append(f"JSON manifest extra table(s): {', '.join(extra)}")

    if not skip_db and mysql_exe is not None:
        import subprocess

        out = subprocess.check_output(
            [
                str(mysql_exe),
                "-u",
                "root",
                "-N",
                "-B",
                "-e",
                "SELECT TABLE_NAME FROM information_schema.TABLES "
                f"WHERE TABLE_SCHEMA = '{database}' ORDER BY TABLE_NAME",
            ],
            text=True,
        )
        db_tables = {line.strip() for line in out.splitlines() if line.strip()}
        unknown = sorted(
            t
            for t in db_tables
            if t not in canonical
            and t not in RETIRED_STAGING_EXPORT_TABLES
            and t not in _NON_AMIGA_PRODUCT
        )
        if unknown:
            errors.append(
                f"{database} has table(s) not in export registry (review): {', '.join(unknown)}"
            )
        missing_in_db = sorted(t for t in canonical if t not in db_tables)
        if missing_in_db:
            errors.append(
                f"{database} missing export table(s) (apply schema first): "
                + ", ".join(missing_in_db)
            )

    return errors


def main_write_staging_export_tables() -> int:
    path = write_staging_export_manifest()
    print(f"Wrote {path} ({len(STAGING_EXPORT_TABLES)} tables)")
    return 0


def main_audit_staging_export(argv: list[str] | None = None) -> int:
    import argparse

    from scripts.work_prepare.paths import find_mysql_exe

    parser = argparse.ArgumentParser(description="Audit Amiga staging export table registry")
    parser.add_argument(
        "--database",
        default="ko2amiga_work",
        help="MySQL schema to check (default: ko2amiga_work)",
    )
    parser.add_argument(
        "--skip-db",
        action="store_true",
        help="Registry/JSON checks only (no live DB)",
    )
    args = parser.parse_args(argv)

    mysql = None if args.skip_db else find_mysql_exe()
    if not args.skip_db and mysql is None:
        print("FAIL mysql client not found")
        return 1

    errors = audit_staging_export(
        database=args.database,
        mysql_exe=mysql,
        skip_db=args.skip_db,
    )
    if errors:
        print("FAIL staging export audit:")
        for err in errors:
            print(f"  - {err}")
        return 1

    print(
        f"OK staging export audit ({len(STAGING_EXPORT_TABLES)} tables, db={args.database})"
    )
    return 0