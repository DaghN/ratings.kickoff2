"""Modern simul orchestrator — L4 + L5 + verify on ko2amiga_work."""

from __future__ import annotations

import json
import logging
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from scripts.amiga.modern.apply_structure import run_apply_structure_work
from scripts.amiga.modern.constants import DAY0_DIR, WORK_DB
from scripts.amiga.modern.db_config import activate_work_database_env
from scripts.amiga.modern.preflight import preflight_simul
from scripts.amiga.modern.replay import run_replay_work
from scripts.amiga.modern.verify_suite import run_modern_verify_suite
from scripts.amiga.modern.video_align import run_video_align_work
from scripts.amiga.modern.work_db import connect_work
from scripts.amiga.schema_bundles import apply_schema

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_SIMUL_LAST = _REPO / "data" / "amiga" / "modern" / "simul-last.json"


def _git_head() -> str | None:
    try:
        proc = subprocess.run(
            ["git", "rev-parse", "HEAD"],
            cwd=_REPO,
            capture_output=True,
            text=True,
            check=False,
        )
        if proc.returncode == 0:
            return proc.stdout.strip() or None
    except OSError:
        pass
    return None


def _l3_counts(conn) -> dict[str, int]:
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
    return {
        "tournaments": tournaments,
        "players": players,
        "games": games,
        "ratings": ratings,
        "fixtures": fixtures,
    }


def _fixtures_empty() -> bool:
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
            return int(cur.fetchone()["n"]) == 0
    finally:
        conn.close()


def _postcheck(
    *,
    started_utc: str,
    duration_sec: float,
    preflight: dict[str, Any],
    l3_before: dict[str, int],
    l3_after: dict[str, int],
    apply_structure: bool,
    skip_video: bool,
) -> dict[str, Any]:
    manifest = json.loads((DAY0_DIR / "manifest.json").read_text(encoding="utf-8"))
    for key, manifest_key in (
        ("tournaments", "tournament_count"),
        ("players", "player_count"),
        ("games", "game_count"),
    ):
        expected = int(manifest.get(manifest_key, -1))
        got = l3_after.get(key, -1)
        if expected != got:
            raise SystemExit(
                f"Postcheck L3 drift: {key} expected {expected}, got {got} (was {l3_before.get(key)})"
            )

    summary: dict[str, Any] = {
        "database": WORK_DB,
        "started_utc": started_utc,
        "finished_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "duration_sec": round(duration_sec, 2),
        "git_head": _git_head(),
        "day0_version": manifest.get("version"),
        "preflight": preflight,
        "l3_before": l3_before,
        "l3_after": l3_after,
        "apply_structure": apply_structure,
        "skip_video": skip_video,
        "derived": {
            "ratings": l3_after.get("ratings"),
            "fixtures": l3_after.get("fixtures"),
        },
    }

    _SIMUL_LAST.parent.mkdir(parents=True, exist_ok=True)
    _SIMUL_LAST.write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    log.info("simul postcheck OK — wrote %s", _SIMUL_LAST)
    return summary


def run_simul(
    *,
    dry_run: bool = False,
    skip_structure: bool = False,
    apply_structure: bool = False,
    skip_video: bool = True,
    skip_verify: bool = False,
    recreate_schema: bool = False,
) -> int:
    if recreate_schema:
        log.warning("simul --recreate-schema: destructive — drops all tables on %s", WORK_DB)

    activate_work_database_env()
    t0 = time.monotonic()
    started_utc = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    preflight = preflight_simul(require_l3=True)
    l3_before = preflight["counts"]

    conn = connect_work()
    try:
        apply_schema(conn, drop_existing=recreate_schema)
    finally:
        conn.close()

    need_structure = apply_structure or _fixtures_empty()
    if skip_structure:
        if need_structure and _fixtures_empty():
            raise SystemExit(
                "simul: L4 fixtures empty but --skip-structure set — bootstrap requires structure"
            )
        log.warning("simul --skip-structure: L4 dispatch skipped")
        need_structure = False

    if need_structure:
        log.info("simul: L4 apply-structure-work")
        stats = run_apply_structure_work(dry_run=dry_run)
        log.info("simul: L4 complete %s", stats.to_dict())

    if not dry_run:
        log.info("simul: L5 replay")
        run_replay_work(dry_run=False)
    else:
        log.info("simul: dry-run replay smoke")
        run_replay_work(dry_run=True)

    if dry_run:
        log.info("simul dry-run — skipping video + verify")
        return 0

    if not skip_video:
        if run_video_align_work(dry_run=False) != 0:
            log.error("simul failed at video_align")
            return 1
    else:
        log.info("simul: skip video align (--skip-video)")

    if not skip_verify:
        rc = run_modern_verify_suite(include_videos=not skip_video)
        if rc != 0:
            return rc
    else:
        log.warning("simul --skip-verify: verify suite skipped")

    conn = connect_work()
    try:
        l3_after = _l3_counts(conn)
    finally:
        conn.close()

    _postcheck(
        started_utc=started_utc,
        duration_sec=time.monotonic() - t0,
        preflight=preflight,
        l3_before={
            "tournaments": l3_before["tournaments"],
            "players": l3_before["players"],
            "games": l3_before["games"],
        },
        l3_after=l3_after,
        apply_structure=need_structure,
        skip_video=skip_video,
    )

    log.info("simul OK on %s", WORK_DB)
    return 0