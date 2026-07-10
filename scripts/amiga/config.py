"""Load ko2amiga_db credentials (mirrors scripts/k2_rating_core/config.py)."""

from __future__ import annotations

import os
from dataclasses import replace
from pathlib import Path

from scripts.k2_rating_core.config import DbConfig, _parse_php_config

_REPO = Path(__file__).resolve().parents[2]
_LOCAL = _REPO / "site" / "config" / "ko2amiga_config.local.php"

# Living repair shop + frozen oracle (see amiga-modern-ground-platform.md).
AMIGA_GROUND_DATABASES: frozenset[str] = frozenset({"ko2amiga_db", "ko2amiga_work"})


def require_amiga_ground_database(cfg: DbConfig, *, operation: str) -> None:
    if cfg.database in AMIGA_GROUND_DATABASES:
        return
    allowed = ", ".join(sorted(AMIGA_GROUND_DATABASES))
    raise SystemExit(
        f"Refusing {operation}: database must be one of {allowed}, got {cfg.database!r}"
    )


def load_amiga_db_config() -> DbConfig:
    if not _LOCAL.is_file():
        example = _REPO / "site" / "config" / "ko2amiga_config.local.php.example"
        raise SystemExit(
            f"Missing {_LOCAL}\n"
            f"Copy {example.name} → ko2amiga_config.local.php and set database=ko2amiga_db"
        )
    cfg = _parse_php_config(_LOCAL)
    override = os.environ.get("KO2AMIGA_DATABASE", "").strip()
    if override:
        return replace(cfg, database=override)
    return cfg
