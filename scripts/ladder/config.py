"""Load site/config/ladder.ini (copy from ladder.ini.example)."""

from __future__ import annotations

import configparser
from dataclasses import dataclass
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_INI = REPO_ROOT / "site" / "config" / "ladder.ini"
EXAMPLE_INI = REPO_ROOT / "site" / "config" / "ladder.ini.example"


@dataclass(frozen=True)
class DbConfig:
    host: str
    port: int
    user: str
    password: str
    database: str


def load_db_config(ini_path: Path | None = None) -> DbConfig:
    path = ini_path or DEFAULT_INI
    if not path.is_file():
        if path == DEFAULT_INI and EXAMPLE_INI.is_file():
            raise SystemExit(
                f"Missing {path}\n"
                f"Copy {EXAMPLE_INI} to {DEFAULT_INI} and adjust credentials."
            )
        raise SystemExit(f"Config not found: {path}")

    parser = configparser.ConfigParser()
    parser.read(path, encoding="utf-8")
    section = parser["database"]
    return DbConfig(
        host=section.get("host", "127.0.0.1"),
        port=section.getint("port", 3306),
        user=section.get("user", "root"),
        password=section.get("password", ""),
        database=section.get("database", "ko2unity_db"),
    )
