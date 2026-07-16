"""Living-ground safety guards for ko2amiga_work (nuclear ops + fingerprint)."""

from __future__ import annotations

import hashlib
import json
import logging
import os
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pymysql

from scripts.amiga.modern.constants import DAY0_DIR, WORK_DB
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
GROUND_FINGERPRINT_JSON = _REPO / "data" / "amiga" / "work" / "ground-fingerprint.json"
VIDEO_PROMOTE_EXPORTS_DIR = _REPO / "data" / "amiga" / "exports" / "video-promote"

DESTROY_WORK_CLI_FLAG = "--i-mean-destroy-work"
DESTROY_WORK_CONFIRM_PHRASE = "destroy-ko2amiga-work"
DESTROY_WORK_ENV = "KO2AMIGA_ALLOW_DESTROY_WORK"


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


def _day0_table_counts() -> dict[str, int] | None:
    path = DAY0_DIR / "manifest.json"
    if not path.is_file():
        return None
    manifest = json.loads(path.read_text(encoding="utf-8"))
    tables = manifest.get("tables")
    if not isinstance(tables, dict):
        return None
    out: dict[str, int] = {}
    for key in ("tournaments", "amiga_players", "amiga_games"):
        if key in tables:
            out[key] = int(tables[key])
    return out or None


def work_ground_counts(conn: pymysql.connections.Connection) -> dict[str, int]:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournaments")
        tournaments = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_players")
        players = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM tournament_stages")
        stages = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
        fixtures = int(cur.fetchone()["n"])
    return {
        "tournaments": tournaments,
        "players": players,
        "games": games,
        "stages": stages,
        "fixtures": fixtures,
    }


def living_ground_reasons(conn: pymysql.connections.Connection) -> list[str]:
    """Return human reasons why work DB should be treated as living ground."""
    counts = work_ground_counts(conn)
    reasons: list[str] = []
    day0 = _day0_table_counts()
    if day0:
        for key in ("tournaments", "amiga_players", "amiga_games"):
            work_key = key if key != "amiga_players" else "players"
            work_key = key if key != "amiga_games" else "games"
            mapping = {
                "tournaments": "tournaments",
                "amiga_players": "players",
                "amiga_games": "games",
            }
            wk = mapping[key]
            if counts[wk] > int(day0.get(key, 0)):
                reasons.append(
                    f"{wk}={counts[wk]} exceeds day0 {day0[key]} "
                    f"(+{counts[wk] - int(day0[key])})"
                )
    if counts["fixtures"] > 0:
        reasons.append(f"fixtures={counts['fixtures']} (L4 structure on work)")
    if counts["stages"] > 0:
        reasons.append(f"stages={counts['stages']} (curated tournament structure)")
    if not reasons and counts["tournaments"] > 0:
        reasons.append(f"populated work DB ({counts['tournaments']} tournaments)")
    return reasons


def living_ground_protected(conn: pymysql.connections.Connection | None = None) -> bool:
    own = conn is None
    if own:
        conn = connect_work()
    try:
        return bool(living_ground_reasons(conn))
    finally:
        if own:
            conn.close()


def require_work_destroy_consent(
    *,
    operation: str,
    cli_destroy_flag: bool,
    confirm_phrase: str | None,
) -> None:
    """Refuse nuclear work ops unless explicit double consent (or env bypass)."""
    if os.environ.get(DESTROY_WORK_ENV) == "1":
        log.warning("%s: %s bypass via %s", operation, "allowed", DESTROY_WORK_ENV)
        return
    if not cli_destroy_flag:
        raise SystemExit(
            f"Refusing {operation}: ko2amiga_work has living ground "
            f"(Nottingham, structure, forward events, video bindings).\n"
            f"Pass {DESTROY_WORK_CLI_FLAG} and "
            f"--confirm-destroy={DESTROY_WORK_CONFIRM_PHRASE!r} to proceed.\n"
            f"Forward path: python -m scripts.amiga simul (does not wipe L3/L4)."
        )
    if (confirm_phrase or "").strip() != DESTROY_WORK_CONFIRM_PHRASE:
        raise SystemExit(
            f"Refusing {operation}: missing confirmation phrase.\n"
            f"Pass --confirm-destroy={DESTROY_WORK_CONFIRM_PHRASE!r}"
        )
    log.warning(
        "%s: DESTRUCTIVE consent accepted — all post-day-0 ground on %s will be lost",
        operation,
        WORK_DB,
    )


def assert_safe_to_nuke_work(
    *,
    operation: str,
    cli_destroy_flag: bool,
    confirm_phrase: str | None,
) -> None:
    conn = connect_work()
    try:
        if not living_ground_protected(conn):
            return
        reasons = living_ground_reasons(conn)
        log.error(
            "Living ground on %s: %s",
            WORK_DB,
            "; ".join(reasons),
        )
        require_work_destroy_consent(
            operation=operation,
            cli_destroy_flag=cli_destroy_flag,
            confirm_phrase=confirm_phrase,
        )
    finally:
        conn.close()


def write_ground_fingerprint(conn: pymysql.connections.Connection | None = None) -> dict[str, Any]:
    own = conn is None
    if own:
        conn = connect_work()
    try:
        counts = work_ground_counts(conn)
        summary: dict[str, Any] = {
            "database": WORK_DB,
            "written_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
            "git_head": _git_head(),
            "counts": counts,
            "day0_baseline": _day0_table_counts(),
        }
        GROUND_FINGERPRINT_JSON.parent.mkdir(parents=True, exist_ok=True)
        GROUND_FINGERPRINT_JSON.write_text(
            json.dumps(summary, indent=2) + "\n",
            encoding="utf-8",
        )
        log.info("ground fingerprint written: %s", GROUND_FINGERPRINT_JSON)
        return summary
    finally:
        if own:
            conn.close()


def check_ground_fingerprint_preflight(
    conn: pymysql.connections.Connection,
    *,
    allow_ground_shrink: bool = False,
) -> None:
    if not GROUND_FINGERPRINT_JSON.is_file():
        return
    prior = json.loads(GROUND_FINGERPRINT_JSON.read_text(encoding="utf-8"))
    prior_counts = prior.get("counts") or {}
    current = work_ground_counts(conn)
    shrink_keys: list[str] = []
    for key in ("tournaments", "players", "games", "stages", "fixtures"):
        before = int(prior_counts.get(key, 0))
        after = int(current.get(key, 0))
        if after < before:
            shrink_keys.append(f"{key} {before}->{after}")
    if shrink_keys and not allow_ground_shrink:
        raise SystemExit(
            "Preflight refused: L3/L4 ground shrank since last fingerprint "
            f"({', '.join(shrink_keys)}).\n"
            f"Prior fingerprint: {GROUND_FINGERPRINT_JSON}\n"
            "If you intentionally wiped ground, pass --allow-ground-shrink or "
            f"delete the fingerprint file."
        )


def sidecar_start_sec_fingerprint(sidecar_path: Path) -> str:
    if not sidecar_path.is_file():
        return ""
    digest = hashlib.sha256()
    with sidecar_path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def snapshot_video_promote_backup() -> dict[str, Any] | None:
    """Pre-export snapshot of deploy video manifest + shared sidecar fingerprint."""
    from scripts.amiga.modern.constants import LEGACY_MANIFEST_JSON, SHARED_VIDEO_DIR

    deploy_manifest = LEGACY_MANIFEST_JSON
    sidecar = SHARED_VIDEO_DIR / "video_game_links.csv"
    if not deploy_manifest.is_file() and not sidecar.is_file():
        return None

    stamp = datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")
    dest_dir = VIDEO_PROMOTE_EXPORTS_DIR / stamp
    dest_dir.mkdir(parents=True, exist_ok=True)
    copied: list[str] = []
    if deploy_manifest.is_file():
        target = dest_dir / "tournament_videos.json"
        target.write_bytes(deploy_manifest.read_bytes())
        copied.append(target.name)
    if sidecar.is_file():
        target = dest_dir / "video_game_links.csv"
        target.write_bytes(sidecar.read_bytes())
        copied.append(target.name)

    meta = {
        "snapshot_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "dir": str(dest_dir),
        "copied": copied,
        "sidecar_sha256": sidecar_start_sec_fingerprint(sidecar),
    }
    (dest_dir / "meta.json").write_text(json.dumps(meta, indent=2) + "\n", encoding="utf-8")
    log.info("video promote snapshot: %s (%s)", dest_dir, copied)
    return meta
