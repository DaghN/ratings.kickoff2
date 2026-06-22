"""Nuclear reset + replay + verify — holy Amiga loop (ko2amiga_db proof path)."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Callable

from scripts.amiga.apply_structure import run_apply_structure
from scripts.amiga.import_access import _DEFAULT_MDB, import_witness_nuclear
from scripts.amiga.replay import run_replay
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_event_snapshots import main as verify_event_snapshots_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.verify_player_matchups import main as verify_player_matchups_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.verify_realm_snapshots import main as verify_realm_snapshots_main
from scripts.amiga.verify_hof_geo_year import main as verify_hof_geo_year_main
from scripts.amiga.verify_hof_holder_projection import main as verify_hof_holder_projection_main
from scripts.amiga.verify_stored_id_date_pairs import main as verify_stored_id_date_pairs_main
from scripts.amiga.verify_player_slice import main as verify_player_slice_main
from scripts.amiga.tournament_format import main as verify_tournament_formats_main

log = logging.getLogger(__name__)

_VERIFY_STEPS: list[tuple[str, Callable[[], int]]] = [
    ("verify-chronology", verify_chronology_main),
    ("verify-rating-events", verify_rating_events_main),
    ("verify-event-snapshots", verify_event_snapshots_main),
    ("verify-player-participation", verify_player_participation_main),
    ("verify-player-matchups", verify_player_matchups_main),
    ("verify-player-slice", verify_player_slice_main),
    ("verify-realm-snapshots", verify_realm_snapshots_main),
    ("verify-hof-geo-year", verify_hof_geo_year_main),
    ("verify-hof-holder-projection", verify_hof_holder_projection_main),
    ("verify-stored-id-date-pairs", verify_stored_id_date_pairs_main),
    ("verify-import-manifest", verify_import_manifest_main),
    ("verify-tournament-formats", lambda: verify_tournament_formats_main([])),
]


def run_prove(
    *,
    mdb: Path = _DEFAULT_MDB,
    dry_run: bool = False,
    limit: int | None = None,
    skip_structure: bool = False,
) -> int:
    """
    L3 → L4 → L5 → verify — modular ground-layer orchestrator.

    ``limit``: replay smoke only — several verifiers require a full replay; do not use
    ``limit`` for sign-off (use full prove).

    ``skip_structure``: dev-only — skip L4 disposition dispatch (not sign-off).
    """
    if limit is not None:
        log.warning(
            "prove --limit=%s: smoke only; rating-events / event-snapshots need full replay",
            limit,
        )
    if skip_structure:
        log.warning("prove --skip-structure: L4 skipped — not sign-off")

    log.info("prove: L3 import-witness --recreate-ground")
    stats = import_witness_nuclear(mdb=mdb)
    log.info("prove: L3 complete %s", stats)

    if not skip_structure:
        log.info("prove: L4 apply-structure --from-disposition")
        l4 = run_apply_structure(from_disposition=True)
        log.info("prove: L4 complete %s", l4.to_dict())

    log.info("prove: L5 replay")
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

    log.info("prove OK: L3 → L4 → L5 → verify suite passed")
    return 0
