"""L4 structure verify on ko2amiga_work (L4-1)."""

from __future__ import annotations

import json
import logging
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from scripts.amiga.modern.constants import WORK_DB
from scripts.amiga.modern.db_config import activate_work_database_env
from scripts.amiga.modern.work_db import connect_work
from scripts.amiga.tournament_structure.disposition_register import (
    DispositionRegister,
    verify_register,
)
from scripts.amiga.verify_structure import (
    HOMEBURG_TOURNAMENT_ID,
    PURE_RR_SMOKE_TOURNAMENT_ID,
    verify_structure,
)

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_L4_VERIFY_LAST = _REPO / "data" / "amiga" / "modern" / "l4-verify-last.json"


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


def _l4_summary() -> dict[str, Any]:
    reg = DispositionRegister.load()
    conn = connect_work()
    try:
        coverage = verify_register(conn, reg)
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM tournament_stages")
            stages = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
            fixtures = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            games = int(cur.fetchone()["n"])
            cur.execute(
                "SELECT COUNT(*) AS n FROM amiga_games WHERE fixture_id IS NOT NULL"
            )
            games_linked = int(cur.fetchone()["n"])
    finally:
        conn.close()

    handlers: dict[str, int] = {}
    for row in reg.rows.values():
        handlers[row.handler] = handlers.get(row.handler, 0) + 1

    return {
        "disposition_register_complete": coverage["ok"],
        "disposition_missing_ids": len(coverage.get("missing_ids", [])),
        "handlers": handlers,
        "tournament_stages": stages,
        "tournament_fixtures": fixtures,
        "amiga_games": games,
        "amiga_games_linked_to_fixtures": games_linked,
    }


def run_verify_structure_work() -> int:
    """Run legacy verify_structure against ko2amiga_work; write L4-1 report."""
    t0 = time.monotonic()
    started_utc = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    activate_work_database_env()
    summary = _l4_summary()
    errors = verify_structure()

    report: dict[str, Any] = {
        "ok": not errors,
        "database": WORK_DB,
        "started_utc": started_utc,
        "finished_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "duration_sec": round(time.monotonic() - t0, 2),
        "git_head": _git_head(),
        "smoke_anchors": {
            "homburg_tournament_id": HOMEBURG_TOURNAMENT_ID,
            "pure_rr_tournament_id": PURE_RR_SMOKE_TOURNAMENT_ID,
        },
        "summary": summary,
        "errors": errors,
    }

    _L4_VERIFY_LAST.parent.mkdir(parents=True, exist_ok=True)
    _L4_VERIFY_LAST.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")

    if errors:
        for err in errors:
            log.error("verify-structure-work: %s", err)
        log.error("L4-1 failed — see %s", _L4_VERIFY_LAST)
        return 1

    log.info(
        "verify-structure-work OK on %s (fixtures=%s games_linked=%s)",
        WORK_DB,
        summary["tournament_fixtures"],
        summary["amiga_games_linked_to_fixtures"],
    )
    log.info("L4-1 report: %s", _L4_VERIFY_LAST)
    return 0