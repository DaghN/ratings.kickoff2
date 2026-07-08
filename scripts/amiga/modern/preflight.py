"""Preflight checks before modern simul on ko2amiga_work."""

from __future__ import annotations

import json
import logging
from typing import Any

from scripts.amiga.modern.constants import DAY0_DIR, L3_GROUND_COUNT_KEYS, WORK_DB
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)


def _day0_baseline() -> dict[str, Any] | None:
    """Optional bootstrap reference — living work may exceed these counts."""
    path = DAY0_DIR / "manifest.json"
    if not path.is_file():
        return None
    manifest = json.loads(path.read_text(encoding="utf-8"))
    return {
        "version": manifest.get("version"),
        "tournaments": manifest.get("tournament_count"),
        "players": manifest.get("player_count"),
        "games": manifest.get("game_count"),
    }


def preflight_simul() -> dict[str, Any]:
    """
    Living-ground preflight: work DB has L3 witness rows.

    Does **not** require counts to match day 0 — forward append may have grown ground.
    Day 0 manifest (if present) is logged as baseline reference only.
    """
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

    if tournaments == 0:
        raise SystemExit(f"Preflight failed: no tournaments in {WORK_DB}")
    if games == 0:
        raise SystemExit(f"Preflight failed: no L3 games in {WORK_DB}")

    counts = {
        "tournaments": tournaments,
        "players": players,
        "games": games,
        "ratings_before": ratings,
        "fixtures_before": fixtures,
    }

    day0 = _day0_baseline()
    if day0:
        above_day0 = {
            key: counts[key] - int(day0.get(key) or 0)
            for key in L3_GROUND_COUNT_KEYS
            if counts[key] > int(day0.get(key) or 0)
        }
        if above_day0:
            log.info("preflight: living ground above day 0 baseline: %s", above_day0)

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
        "day0_baseline": day0,
        "counts": counts,
    }
