"""Nuclear reset + replay + verify — holy Amiga loop (ko2amiga_db proof path)."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Callable

from scripts.amiga.import_access import _DEFAULT_MDB, import_all
from scripts.amiga.replay import run_replay
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_event_snapshots import main as verify_event_snapshots_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.verify_player_matchups import main as verify_player_matchups_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.tournament_format import main as verify_tournament_formats_main

log = logging.getLogger(__name__)

_VERIFY_STEPS: list[tuple[str, Callable[[], int]]] = [
    ("verify-chronology", verify_chronology_main),
    ("verify-rating-events", verify_rating_events_main),
    ("verify-event-snapshots", verify_event_snapshots_main),
    ("verify-player-participation", verify_player_participation_main),
    ("verify-player-matchups", verify_player_matchups_main),
    ("verify-import-manifest", verify_import_manifest_main),
    ("verify-tournament-formats", lambda: verify_tournament_formats_main([])),
]


def run_prove(
    *,
    mdb: Path = _DEFAULT_MDB,
    dry_run: bool = False,
    limit: int | None = None,
) -> int:
    """
    Drop schema, import Access ground truth, replay derived, run verify suite.

    ``limit``: replay smoke only — several verifiers require a full replay; do not use
    ``limit`` for sign-off (use full prove).
    """
    if limit is not None:
        log.warning(
            "prove --limit=%s: smoke only; rating-events / event-snapshots need full replay",
            limit,
        )

    log.info("prove: import --recreate-schema")
    stats = import_all(mdb=mdb, recreate_schema=True)
    log.info("prove: import complete %s", stats)

    log.info("prove: replay")
    run_replay(dry_run=dry_run, limit=limit)

    if dry_run:
        log.info("prove: dry-run — skipping verify suite")
        return 0

    for name, verify_fn in _VERIFY_STEPS:
        log.info("prove: %s", name)
        rc = verify_fn()
        if rc != 0:
            log.error("prove failed at %s (exit %s)", name, rc)
            return rc

    log.info("prove OK: nuclear reset + replay + verify suite passed")
    return 0
