"""Preflight checks before modern simul on ko2amiga_work."""

from __future__ import annotations

import json
import logging
from pathlib import Path
from typing import Any

from scripts.amiga.modern.constants import DAY0_DIR, WORK_DB
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)


def _load_day0_manifest() -> dict[str, Any]:
    path = DAY0_DIR / "manifest.json"
    if not path.is_file():
        raise SystemExit(f"Missing day 0 manifest: {path}\nRun: python -m scripts.amiga seal-day0")
    return json.loads(path.read_text(encoding="utf-8"))


def preflight_simul(*, require_l3: bool = True) -> dict[str, Any]:
    manifest = _load_day0_manifest()
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM tournaments")
            tournaments = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_players")
            players = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            games = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_game_ratings")
            ratings = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
            fixtures = int(cur.fetchone()["n"])
    finally:
        conn.close()

    if require_l3:
        checks = (
            ("tournaments", manifest.get("tournament_count"), tournaments),
            ("players", manifest.get("player_count"), players),
            ("games", manifest.get("game_count"), games),
        )
        for key, expected, got in checks:
            if expected is not None and int(expected) != got:
                raise SystemExit(
                    f"Preflight L3 mismatch on {WORK_DB}: {key} expected {expected}, got {got}\n"
                    "Run: python -m scripts.amiga seed-work"
                )

    if games == 0:
        raise SystemExit(f"Preflight failed: no L3 games in {WORK_DB}")

    log.info(
        "preflight OK: %s tournaments=%s players=%s games=%s ratings=%s fixtures=%s",
        WORK_DB,
        tournaments,
        players,
        games,
        ratings,
        fixtures,
    )
    return {
        "database": WORK_DB,
        "day0_version": manifest.get("version"),
        "counts": {
            "tournaments": tournaments,
            "players": players,
            "games": games,
            "ratings_before": ratings,
            "fixtures_before": fixtures,
        },
    }