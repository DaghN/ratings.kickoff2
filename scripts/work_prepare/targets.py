"""Load prepare target profiles (local-work, staging-work)."""

from __future__ import annotations

import configparser
from dataclasses import dataclass

from .constants import DEFAULT_PROFILES
from .paths import work_targets_ini


@dataclass(frozen=True)
class WorkTarget:
    profile: str
    work_database: str
    baseline_database: str
    host: str
    port: int
    user: str
    password: str


def load_target(profile: str) -> WorkTarget:
    if profile not in DEFAULT_PROFILES:
        known = ", ".join(sorted(DEFAULT_PROFILES))
        raise SystemExit(f"Unknown target profile {profile!r}. Expected one of: {known}")

    data = dict(DEFAULT_PROFILES[profile])
    ini_path = work_targets_ini()
    if ini_path.is_file():
        parser = configparser.ConfigParser()
        parser.read(ini_path, encoding="utf-8")
        if parser.has_section(profile):
            section = parser[profile]
            for key in ("work_database", "baseline_database", "host", "user", "password"):
                if section.get(key):
                    data[key] = section.get(key, "").strip()
            if section.get("port"):
                data["port"] = int(section.get("port", "3306"))

    return WorkTarget(
        profile=profile,
        work_database=str(data["work_database"]),
        baseline_database=str(data["baseline_database"]),
        host=str(data["host"]),
        port=int(data["port"]),
        user=str(data["user"]),
        password=str(data["password"]),
    )
