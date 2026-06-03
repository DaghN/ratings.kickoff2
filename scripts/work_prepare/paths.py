"""Laragon MySQL paths and repo root (Windows local)."""

from __future__ import annotations

from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

OPS_ROOT = REPO_ROOT / "site" / "public_html" / "ops"
MILESTONES_SEED_PATH = OPS_ROOT / "data" / "milestones_definitions_seed.json"

MYSQL_CANDIDATES = (
    Path(r"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"),
    Path(r"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"),
    Path(r"C:\laragon\bin\mariadb\mariadb-11.4.2-winx64\bin\mysql.exe"),
    Path(r"C:\laragon\bin\mariadb\mariadb-10.11.8-winx64\bin\mysql.exe"),
)


def find_mysql_exe() -> Path:
    for path in MYSQL_CANDIDATES:
        if path.is_file():
            return path
    raise SystemExit(
        "mysql.exe not found under C:\\laragon\\bin\\mysql or mariadb. Start Laragon (docs/LOCAL_DEV.md)."
    )


def find_mysqldump_exe() -> Path:
    mysql = find_mysql_exe()
    dump = mysql.parent / "mysqldump.exe"
    if not dump.is_file():
        raise SystemExit(f"mysqldump.exe not found beside {mysql}")
    return dump


def work_targets_ini() -> Path:
    ops_ini = OPS_ROOT / "config" / "work-targets.ini"
    if ops_ini.is_file():
        return ops_ini
    return REPO_ROOT / "site" / "config" / "work-targets.ini"


def work_targets_example_ini() -> Path:
    ops_example = OPS_ROOT / "config" / "work-targets.ini.example"
    if ops_example.is_file():
        return ops_example
    return REPO_ROOT / "site" / "config" / "work-targets.ini.example"
