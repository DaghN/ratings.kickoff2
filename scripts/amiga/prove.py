"""Nuclear reset + replay + verify — holy Amiga loop (ko2amiga_db proof path)."""

from __future__ import annotations

import logging
from pathlib import Path
from typing import Callable

from scripts.amiga.apply_structure import run_apply_structure
from scripts.amiga.import_access import _DEFAULT_L2_DIR, _DEFAULT_MDB, import_witness_nuclear
from scripts.amiga.import_prune import run_import_prune
from scripts.amiga.import_pristine import _DEFAULT_OUT as _DEFAULT_L1_OUT, run_import_pristine
from scripts.amiga.replay import run_replay
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_event_snapshots import main as verify_event_snapshots_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.verify_l2_l3_boundary import main as verify_l2_l3_boundary_main
from scripts.amiga.verify_player_matchups import main as verify_player_matchups_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.verify_realm_snapshots import main as verify_realm_snapshots_main
from scripts.amiga.verify_community_stats import main as verify_community_stats_main
from scripts.amiga.verify_world_cup_stats import main as verify_world_cup_stats_main
from scripts.amiga.verify_php_community_parity import main as verify_php_community_parity_main
from scripts.amiga.verify_hof_peak_rating_holder import main as verify_hof_peak_rating_holder_main
from scripts.amiga.verify_hof_geo_year import main as verify_hof_geo_year_main
from scripts.amiga.verify_hof_holder_projection import main as verify_hof_holder_projection_main
from scripts.amiga.verify_stored_id_date_pairs import main as verify_stored_id_date_pairs_main
from scripts.amiga.verify_player_slice import main as verify_player_slice_main
from scripts.amiga.verify_country_slice import main as verify_country_slice_main
from scripts.amiga.verify_wc_hof import main as verify_wc_hof_main
from scripts.amiga.verify_tournament_videos import main as verify_tournament_videos_main
from scripts.amiga.verify_perfect_event import main as verify_perfect_event_main
from scripts.amiga.tournament_format import main as verify_tournament_formats_main

log = logging.getLogger(__name__)

_VERIFY_STEPS: list[tuple[str, Callable[[], int]]] = [
    ("verify-chronology", verify_chronology_main),
    ("verify-rating-events", verify_rating_events_main),
    ("verify-event-snapshots", verify_event_snapshots_main),
    ("verify-player-participation", verify_player_participation_main),
    ("verify-player-matchups", verify_player_matchups_main),
    ("verify-player-slice", verify_player_slice_main),
    ("verify-country-slice", verify_country_slice_main),
    ("verify-wc-hof", verify_wc_hof_main),
    ("verify-realm-snapshots", verify_realm_snapshots_main),
    ("verify-community-stats", verify_community_stats_main),
    ("verify-world-cup-stats", verify_world_cup_stats_main),
    ("verify-php-community-parity", verify_php_community_parity_main),
    ("verify-hof-geo-year", verify_hof_geo_year_main),
    ("verify-perfect-event", verify_perfect_event_main),
    ("verify-hof-holder-projection", verify_hof_holder_projection_main),
    ("verify-hof-peak-rating-holder", verify_hof_peak_rating_holder_main),
    ("verify-stored-id-date-pairs", verify_stored_id_date_pairs_main),
    ("verify-import-manifest", verify_import_manifest_main),
    ("verify-tournament-videos", verify_tournament_videos_main),
    ("verify-l2-l3", lambda: verify_l2_l3_boundary_main([])),
    ("verify-tournament-formats", lambda: verify_tournament_formats_main([])),
]


def run_prove(
    *,
    mdb: Path = _DEFAULT_MDB,
    l1_dir: Path = _DEFAULT_L1_OUT,
    l2_dir: Path = _DEFAULT_L2_DIR,
    dry_run: bool = False,
    limit: int | None = None,
    skip_structure: bool = False,
    skip_l1_l2: bool = False,
) -> int:
    """
    L1 → L2 → L3 → L4 → L5 → verify — strict ground-layer orchestrator.

    ``skip_l1_l2``: use existing L2 artefact (dev only — full sign-off runs L0→L1→L2).

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
    if skip_l1_l2:
        log.warning("prove --skip-l1-l2: using existing L2 — not full L0 sign-off")

    if not skip_l1_l2:
        log.info("prove: L1 import-pristine")
        run_import_pristine(mdb=mdb, out_dir=l1_dir)
        log.info("prove: L2 import-prune")
        run_import_prune(l1_dir=l1_dir, out_dir=l2_dir)

    log.info("prove: L3 import-witness --recreate-ground (from L2)")
    stats = import_witness_nuclear(l2_dir=l2_dir)
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

    log.info("prove: sync tournament video DB anchors")
    from scripts.amiga.tournament_videos.sync_db_ids import run as sync_tournament_video_db

    if sync_tournament_video_db(write=True, resolve_matches=True, rebuild=True) != 0:
        log.error("prove failed at tournament-video DB anchor sync")
        return 1

    for name, verify_fn in _VERIFY_STEPS:
        log.info("prove: %s", name)
        rc = verify_fn()
        if rc != 0:
            log.error("prove failed at %s (exit %s)", name, rc)
            return rc

    log.info("prove OK: L1 → L2 → L3 → L4 → L5 → verify suite passed")
    return 0
