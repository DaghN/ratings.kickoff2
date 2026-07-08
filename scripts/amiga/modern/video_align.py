"""Tournament video DB anchor sync on ko2amiga_work."""

from __future__ import annotations

import logging

from scripts.amiga.modern.db_config import activate_work_database_env
from scripts.amiga.tournament_videos.sync_db_ids import run as sync_tournament_video_db

log = logging.getLogger(__name__)


def run_video_align_work(*, dry_run: bool = False) -> int:
    activate_work_database_env()
    log.info("video_align: sync tournament video DB anchors on ko2amiga_work")
    return sync_tournament_video_db(
        write=not dry_run,
        resolve_matches=True,
        rebuild=not dry_run,
    )