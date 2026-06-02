"""Database config: same source as PHP (ko2unitydb_config.php), optional ladder.ini override."""

from __future__ import annotations

import configparser
import re
from dataclasses import dataclass
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]
DEFAULT_INI = REPO_ROOT / "site" / "config" / "ladder.ini"
EXAMPLE_INI = REPO_ROOT / "site" / "config" / "ladder.ini.example"

def _php_config_candidates() -> tuple[Path, ...]:
    """Paths to ko2unitydb_config.php (same order PHP uses on each layout)."""
    ladder_dir = Path(__file__).resolve().parent
    root = ladder_dir.parent.parent  # repo root, or public_html if deployed there
    bases = [root]
    if root.name == "public_html":
        bases.append(root.parent)
    seen: set[Path] = set()
    out: list[Path] = []
    for base in bases:
        for sub in ("site/config", "config"):
            p = (base / sub / "ko2unitydb_config.php").resolve()
            if p not in seen:
                seen.add(p)
                out.append(p)
    return tuple(out)

_PHP_ASSIGN = re.compile(
    r"\$(?P<name>dbhost|username|password|database|dbportnum)\s*=\s*"
    r"(?:['\"](?P<quoted>[^'\"]*)['\"]|(?P<number>\d+))\s*;",
    re.MULTILINE,
)


@dataclass(frozen=True)
class DbConfig:
    host: str
    port: int
    user: str
    password: str
    database: str


def _parse_php_config(path: Path) -> DbConfig:
    text = path.read_text(encoding="utf-8", errors="replace")
    values: dict[str, str] = {}
    for match in _PHP_ASSIGN.finditer(text):
        name = match.group("name")
        values[name] = match.group("quoted") if match.group("quoted") is not None else match.group("number")

    missing = [n for n in ("dbhost", "username", "password", "database", "dbportnum") if n not in values]
    if missing:
        raise SystemExit(
            f"Could not read ${', '.join(missing)} from {path}\n"
            "Expected simple assignments like $database = 'kooldb';"
        )

    return DbConfig(
        host=values["dbhost"],
        port=int(values["dbportnum"]),
        user=values["username"],
        password=values["password"],
        database=values["database"],
    )


def _load_from_ini(path: Path) -> DbConfig:
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


def _resolve_php_config_path(path: Path) -> Path:
    """If path is the host router, load dev credentials from *.local.php (CLI has no HTTP_HOST)."""
    try:
        text = path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return path
    if "ko2unitydb_config.local.php" in text and "HTTP_HOST" in text:
        local = path.parent / "ko2unitydb_config.local.php"
        if local.is_file():
            return local
    return path


def _find_php_config() -> Path | None:
    for path in _php_config_candidates():
        if path.is_file():
            return _resolve_php_config_path(path)
    return None


def load_db_config(ini_path: Path | None = None) -> DbConfig:
    """Load DB settings. Default: ko2unitydb_config.local.php via router (dev DB, same as ratingskickoff.test)."""
    if ini_path is not None:
        if not ini_path.is_file():
            raise SystemExit(f"Config not found: {ini_path}")
        return _load_from_ini(ini_path)

    php_path = _find_php_config()
    if php_path is not None:
        return _parse_php_config(php_path)

    if DEFAULT_INI.is_file():
        return _load_from_ini(DEFAULT_INI)

    candidates = "\n  ".join(str(p) for p in _php_config_candidates())
    raise SystemExit(
        "No database config found.\n"
        f"  PHP (preferred): {candidates}\n"
        f"  INI (optional): {DEFAULT_INI}\n"
        f"Copy {EXAMPLE_INI} to ladder.ini only if you cannot use the PHP config file."
    )
