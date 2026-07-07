"""Amiga country registry — load and validate official country tokens."""

from __future__ import annotations

import json
from functools import lru_cache
from pathlib import Path
from typing import Any

REPO_ROOT = Path(__file__).resolve().parents[2]
REGISTRY_PATH = REPO_ROOT / "data" / "amiga" / "country_registry.json"

# Sentinel — not a registry row (docs/amiga-country-registry-policy.md CR3).
AMIGA_COUNTRY_UNKNOWN_TOKEN = "Unknown"


class CountryRegistryError(Exception):
    """Invalid or missing registry data."""


def registry_path() -> Path:
    return REGISTRY_PATH


@lru_cache(maxsize=1)
def load_registry() -> dict[str, Any]:
    path = registry_path()
    if not path.is_file():
        raise CountryRegistryError(f"Country registry missing: {path}")
    data = json.loads(path.read_text(encoding="utf-8"))
    countries = data.get("countries")
    if not isinstance(countries, list) or not countries:
        raise CountryRegistryError("country_registry.json: countries[] required")
    return data


def countries_rows() -> list[dict[str, Any]]:
    rows = load_registry()["countries"]
    return [row for row in rows if isinstance(row, dict)]


def official_names() -> frozenset[str]:
    return frozenset(str(row["official_name"]) for row in countries_rows())


def official_name_to_row() -> dict[str, dict[str, Any]]:
    out: dict[str, dict[str, Any]] = {}
    for row in countries_rows():
        name = str(row["official_name"])
        if name in out:
            raise CountryRegistryError(f"Duplicate official_name: {name!r}")
        out[name] = row
    return out


def alias_map() -> dict[str, str]:
    """Map legacy alias string -> official_name."""
    out: dict[str, str] = {}
    for row in countries_rows():
        official = str(row["official_name"])
        for alias in row.get("legacy_aliases") or []:
            key = str(alias).strip()
            if not key:
                continue
            if key in out and out[key] != official:
                raise CountryRegistryError(f"Conflicting legacy alias: {key!r}")
            out[key] = official
    return out


def resolve_official(raw: str) -> str | None:
    """Return registry official_name for raw token/alias, or None if unknown."""
    token = (raw or "").strip()
    if token == "":
        return None
    if token in official_names():
        return token
    return alias_map().get(token)


def canonicalize_country_token(raw: str) -> str:
    """Map alias or official input to official_name; pass-through trim for official."""
    token = (raw or "").strip()
    if token == "":
        return ""
    resolved = resolve_official(token)
    if resolved is not None:
        return resolved
    return token


def validate_official(name: str) -> bool:
    return (name or "").strip() in official_names()


def choosable_rows() -> list[dict[str, Any]]:
    return [row for row in countries_rows() if bool(row.get("choosable", True))]


def choosable_flag_codes() -> list[str]:
    codes: list[str] = []
    for row in choosable_rows():
        code = str(row.get("flag_code") or "").strip()
        if not code:
            raise CountryRegistryError(f"Choosable row missing flag_code: {row.get('official_name')!r}")
        codes.append(code)
    return codes


def registry_version() -> int:
    return int(load_registry().get("version") or 0)


def flag_icons_version() -> str:
    return str(load_registry().get("flag_icons_version") or "").strip()
