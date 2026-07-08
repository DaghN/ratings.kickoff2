"""V-1 tournament video catalog — work/oracle compartments (MG11 path isolation)."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
import time
from contextlib import contextmanager
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable, Iterator, TypeVar

from scripts.amiga.modern.constants import (
    LEGACY_MANIFEST_JSON,
    ORACLE_VIDEO_DIR,
    SHARED_VIDEO_DIR,
    WORK_DB,
    WORK_MANIFEST_JSON,
    WORK_VIDEO_DIR,
)
from scripts.amiga.modern.db_config import activate_work_database_env
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_VIDEO_LAST = _REPO / "data" / "amiga" / "modern" / "video-last.json"

T = TypeVar("T")

_SHARED_FILES = (
    "review.csv",
    "video_game_links.csv",
    "dropped.csv",
)


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


@contextmanager
def work_video_paths() -> Iterator[None]:
    """Point legacy tournament_videos modules at work outputs only."""
    import scripts.amiga.tournament_videos.build_manifest as build_manifest
    import scripts.amiga.tournament_videos.constants as tv_const
    import scripts.amiga.tournament_videos.game_links as gl
    import scripts.amiga.tournament_videos.manifest_db as manifest_db

    work_review = WORK_VIDEO_DIR / "review.csv"
    work_manifest = WORK_MANIFEST_JSON
    work_public_review = work_manifest.parent / "tournament_videos" / "review.csv"
    work_links = WORK_VIDEO_DIR / "video_game_links.csv"

    saved = (
        tv_const.REVIEW_CSV,
        tv_const.MANIFEST_JSON,
        gl.VIDEO_GAME_LINKS_CSV,
        build_manifest.REVIEW_CSV,
        build_manifest.MANIFEST_JSON,
        build_manifest.REVIEW_CSV_PUBLIC,
        manifest_db.REVIEW_CSV,
        manifest_db.MANIFEST_JSON,
    )
    try:
        tv_const.REVIEW_CSV = work_review
        tv_const.MANIFEST_JSON = work_manifest
        gl.VIDEO_GAME_LINKS_CSV = work_links
        build_manifest.REVIEW_CSV = work_review
        build_manifest.MANIFEST_JSON = work_manifest
        build_manifest.REVIEW_CSV_PUBLIC = work_public_review
        manifest_db.REVIEW_CSV = work_review
        manifest_db.MANIFEST_JSON = work_manifest
        yield
    finally:
        (
            tv_const.REVIEW_CSV,
            tv_const.MANIFEST_JSON,
            gl.VIDEO_GAME_LINKS_CSV,
            build_manifest.REVIEW_CSV,
            build_manifest.MANIFEST_JSON,
            build_manifest.REVIEW_CSV_PUBLIC,
            manifest_db.REVIEW_CSV,
            manifest_db.MANIFEST_JSON,
        ) = saved


def seal_video_oracle() -> dict[str, Any]:
    """V-1.0 — snapshot oracle-aligned catalog (read-only baseline)."""
    ORACLE_VIDEO_DIR.mkdir(parents=True, exist_ok=True)
    copied: list[str] = []

    for name in _SHARED_FILES:
        src = SHARED_VIDEO_DIR / name
        if src.is_file():
            shutil.copy2(src, ORACLE_VIDEO_DIR / name)
            copied.append(name)

    if LEGACY_MANIFEST_JSON.is_file():
        shutil.copy2(LEGACY_MANIFEST_JSON, ORACLE_VIDEO_DIR / "tournament_videos.json")
        copied.append("tournament_videos.json")

    if "review.csv" not in copied:
        raise SystemExit(f"Missing shared review.csv at {SHARED_VIDEO_DIR / 'review.csv'}")

    summary = {
        "oracle_dir": str(ORACLE_VIDEO_DIR),
        "copied": copied,
        "sealed_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
    }
    log.info("seal-video-oracle OK: %s", copied)
    return summary


def seed_work_video_catalog(*, force: bool = False) -> dict[str, Any]:
    """V-1.2 — copy shared editorial into work compartment."""
    WORK_VIDEO_DIR.mkdir(parents=True, exist_ok=True)
    copied: list[str] = []

    for name in _SHARED_FILES:
        src = SHARED_VIDEO_DIR / name
        if not src.is_file():
            continue
        dest = WORK_VIDEO_DIR / name
        if dest.is_file() and not force:
            continue
        shutil.copy2(src, dest)
        copied.append(name)

    if not (WORK_VIDEO_DIR / "review.csv").is_file():
        raise SystemExit(
            f"Work video seed failed: no review.csv in {WORK_VIDEO_DIR}\n"
            f"Copy from {SHARED_VIDEO_DIR}"
        )

    log.info("seed-video-work: %s", copied or "(work catalog already present)")
    return {"work_dir": str(WORK_VIDEO_DIR), "copied": copied}


def _run_with_work_db(fn: Callable[[], T]) -> T:
    activate_work_database_env()
    return fn()


def run_video_align_work(*, dry_run: bool = False) -> int:
    """Align work review.csv + manifest to ko2amiga_work (never legacy paths)."""
    from scripts.amiga.tournament_videos.build_manifest import run as build_manifest
    from scripts.amiga.tournament_videos.manifest_db import (
        DbSnapshot,
        connect_db,
        load_review_rows,
        sync_review_csv_from_db,
        validate_catalog,
        write_review_csv,
    )

    t0 = time.monotonic()
    started_utc = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    seed_work_video_catalog()

    def _align() -> tuple[list[str], list[str], list[str], int]:
        with work_video_paths():
            rows = load_review_rows()
            conn = connect_db()
            try:
                snap = DbSnapshot.load(conn)
            finally:
                conn.close()

            changes, escalations = sync_review_csv_from_db(
                rows, snap, resolve_matches=True
            )

            errors: list[str] = []
            total = 0
            if not dry_run:
                write_review_csv(rows)
                build_manifest()
                conn = connect_db()
                try:
                    snap = DbSnapshot.load(conn)
                finally:
                    conn.close()
                errors, total = validate_catalog(snap, csv_rows=rows)
            return changes, escalations, errors, total

    changes, escalations, errors, total = _run_with_work_db(_align)

    report: dict[str, Any] = {
        "ok": total == 0,
        "database": WORK_DB,
        "dry_run": dry_run,
        "started_utc": started_utc,
        "finished_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "duration_sec": round(time.monotonic() - t0, 2),
        "git_head": _git_head(),
        "work_review_csv": str(WORK_VIDEO_DIR / "review.csv"),
        "work_manifest_json": str(WORK_MANIFEST_JSON),
        "changes": len(changes),
        "escalations": len(escalations),
        "verify_errors": total,
    }
    _VIDEO_LAST.parent.mkdir(parents=True, exist_ok=True)
    _VIDEO_LAST.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")

    if escalations:
        for line in escalations[:15]:
            log.warning("video_align escalation: %s", line)
        if len(escalations) > 15:
            log.warning("video_align: ... and %s more escalations", len(escalations) - 15)

    if dry_run:
        log.info("video_align dry-run: %s changes, %s escalations", len(changes), len(escalations))
        return 0

    if total:
        for err in errors:
            log.error("video_align verify: %s", err)
        log.error("video_align failed — see %s", _VIDEO_LAST)
        return 1

    log.info(
        "video_align OK on %s (%s changes, manifest %s)",
        WORK_DB,
        len(changes),
        WORK_MANIFEST_JSON,
    )
    return 0


def run_verify_tournament_videos_work() -> int:
    """verify-tournament-videos against work manifest + ko2amiga_work."""
    from scripts.amiga.tournament_videos.manifest_db import (
        DbSnapshot,
        connect_db,
        validate_catalog,
    )

    if not WORK_MANIFEST_JSON.is_file():
        raise SystemExit(
            f"Missing work manifest {WORK_MANIFEST_JSON}\n"
            "Run: python -m scripts.amiga align-video-work"
        )

    def _verify() -> int:
        with work_video_paths():
            conn = connect_db()
            try:
                snap = DbSnapshot.load(conn)
            finally:
                conn.close()
            errors, total = validate_catalog(snap)
        if total:
            for err in errors:
                log.error("verify-tournament-videos-work: %s", err)
            return 1
        log.info("verify-tournament-videos-work OK on %s", WORK_DB)
        return 0

    return _run_with_work_db(_verify)


def promote_work_video_deploy() -> dict[str, Any]:
    """PROMOTE-1 — copy work video build to PHP deploy paths."""
    work_manifest = WORK_MANIFEST_JSON
    work_review = WORK_VIDEO_DIR / "review.csv"
    deploy_manifest = LEGACY_MANIFEST_JSON
    deploy_review = deploy_manifest.parent / "tournament_videos" / "review.csv"

    if not work_manifest.is_file():
        raise SystemExit(
            f"Missing work manifest {work_manifest}\n"
            "Run: python -m scripts.amiga align-video-work"
        )

    deploy_manifest.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(work_manifest, deploy_manifest)
    copied = [str(deploy_manifest.relative_to(_REPO))]

    if work_review.is_file():
        deploy_review.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(work_review, deploy_review)
        copied.append(str(deploy_review.relative_to(_REPO)))

    summary = {
        "work_manifest": str(work_manifest),
        "deployed": copied,
        "promoted_utc": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
    }
    log.info("promote-video-deploy OK: %s", copied)
    return summary