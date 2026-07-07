#!/usr/bin/env python3
"""Verify witness country tokens and choosable flag SVGs against country_registry.json."""

from __future__ import annotations

import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.country_registry import choosable_flag_codes, official_names, registry_path

_REPO = Path(__file__).resolve().parents[2]
_SITE_FLAGS = _REPO / "site" / "public_html" / "img" / "flags" / "amiga"
_SITE_REGISTRY = _REPO / "site" / "public_html" / "data" / "amiga" / "country_registry.json"


def main() -> int:
    errors: list[str] = []

    if not registry_path().is_file():
        errors.append(f"missing registry: {registry_path()}")
    if not _SITE_REGISTRY.is_file():
        errors.append(f"missing staging deploy copy: {_SITE_REGISTRY.relative_to(_REPO)}")

    official = official_names()
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute(
            "SELECT DISTINCT TRIM(country) AS token FROM amiga_players WHERE TRIM(country) <> ''"
        )
        player_tokens = {str(row["token"]) for row in cur.fetchall()}
        cur.execute(
            "SELECT DISTINCT TRIM(country) AS token FROM tournaments WHERE TRIM(country) <> ''"
        )
        host_tokens = {str(row["token"]) for row in cur.fetchall()}

    conn.close()

    for token in sorted(player_tokens | host_tokens):
        if token not in official:
            errors.append(f"DB country not in registry: {token!r}")

    for code in choosable_flag_codes():
        path = _SITE_FLAGS / f"{code}.svg"
        if not path.is_file():
            errors.append(f"missing choosable flag SVG: {path.relative_to(_REPO)}")

    legacy = {"N. Ireland", "UAE"}
    found_legacy = (player_tokens | host_tokens) & legacy
    if found_legacy:
        errors.append(f"legacy country token(s) still in DB: {sorted(found_legacy)}")

    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1

    print(
        f"OK: country registry — {len(player_tokens)} player + {len(host_tokens)} host tokens; "
        f"{len(choosable_flag_codes())} choosable flag SVG(s)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())