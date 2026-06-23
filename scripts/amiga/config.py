"""Load ko2amiga_db credentials (mirrors scripts/k2_rating_core/config.py)."""

from __future__ import annotations

from pathlib import Path

from scripts.k2_rating_core.config import DbConfig, _parse_php_config

_REPO = Path(__file__).resolve().parents[2]
_LOCAL = _REPO / "site" / "config" / "ko2amiga_config.local.php"


def load_amiga_db_config() -> DbConfig:
    if not _LOCAL.is_file():
        example = _REPO / "site" / "config" / "ko2amiga_config.local.php.example"
        raise SystemExit(
            f"Missing {_LOCAL}\n"
            f"Copy {example.name} → ko2amiga_config.local.php and set database=ko2amiga_db"
        )
    return _parse_php_config(_LOCAL)
