"""Build and write the per-import audit manifest."""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

MANIFEST_VERSION = 1

# Modules that apply transforms (documented in docs/amiga-import-layer.md).
AUTOMATIC_TRANSFORM_MODULES = (
    "scripts.amiga.player_names",
    "scripts.amiga.tournament_names",
    "scripts.amiga.tournament_format",
    "scripts.amiga.tournament_structure",
    "scripts.amiga.import_access",
    "scripts.amiga.import_country_registry",
)
MANUAL_TRANSFORM_MODULE = "scripts.amiga.import_corrections"


def source_metadata(mdb: Path) -> dict[str, Any]:
    stat = mdb.stat()
    modified = datetime.fromtimestamp(stat.st_mtime, tz=timezone.utc).isoformat()
    return {
        "path": str(mdb.resolve()),
        "filename": mdb.name,
        "size_bytes": stat.st_size,
        "modified_utc": modified,
    }


def build_manifest(
    *,
    mdb: Path | None = None,
    source: dict[str, Any] | None = None,
    stats: dict[str, int],
    name_merges: list[dict[str, object]],
    catalog_overrides: list[dict[str, str]],
    player_country_overrides: list[dict[str, str]] | None = None,
    country_token_normalizations: list[dict[str, str]] | None = None,
    country_registry: dict[str, int | str] | None = None,
    catalog_splits: list[dict[str, str | int | float]] | None = None,
    score_supplements: list[dict[str, str | int]] | None = None,
    structure_specs: list[dict[str, object]] | None = None,
) -> dict[str, Any]:
    if source is None:
        if mdb is None:
            raise ValueError("build_manifest requires source= or mdb=")
        source = source_metadata(mdb)
    return {
        "manifest_version": MANIFEST_VERSION,
        "generated_at_utc": datetime.now(tz=timezone.utc).isoformat(),
        "source": source,
        "stats": stats,
        "transforms": {
            "name_merges": name_merges,
            "catalog_overrides": catalog_overrides,
            "player_country_overrides": player_country_overrides or [],
            "country_token_normalizations": country_token_normalizations or [],
            "catalog_splits": catalog_splits or [],
            "score_supplements": score_supplements or [],
            "structure_specs": structure_specs or [],
        },
        "registry": {
            "automatic_modules": list(AUTOMATIC_TRANSFORM_MODULES),
            "manual_overrides_module": MANUAL_TRANSFORM_MODULE,
            "country_registry": country_registry or {},
        },
    }


def default_manifest_path(repo_root: Path) -> Path:
    return repo_root / "data" / "amiga" / "exports" / "import_manifest.json"


def write_manifest(path: Path, manifest: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
