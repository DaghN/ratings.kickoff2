"""Modern verify suite for ko2amiga_work simul (subset of legacy prove)."""

from __future__ import annotations

import logging
from typing import Callable

from scripts.amiga.modern.db_config import activate_work_database_env
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_country_registry import main as verify_country_registry_main
from scripts.amiga.verify_is_world_cup import main as verify_is_world_cup_main
from scripts.amiga.verify_player_create import main as verify_player_create_main
from scripts.amiga.verify_scoring_contract import main as verify_scoring_contract_main
from scripts.amiga.verify_running_tournament_boundary import main as verify_running_tournament_boundary_main
from scripts.amiga.verify_player_matchups import main as verify_player_matchups_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.verify_event_snapshots import main as verify_event_snapshots_main
from scripts.amiga.verify_realm_snapshots import main as verify_realm_snapshots_main
from scripts.amiga.verify_community_stats import main as verify_community_stats_main
from scripts.amiga.verify_world_cup_stats import main as verify_world_cup_stats_main
from scripts.amiga.verify_php_community_parity import main as verify_php_community_parity_main
from scripts.amiga.verify_php_standings_parity import main as verify_php_standings_parity_main
from scripts.amiga.verify_rtb_standings_parity import main as verify_rtb_standings_parity_main
from scripts.amiga.verify_hof_peak_rating_holder import main as verify_hof_peak_rating_holder_main
from scripts.amiga.verify_hof_geo_year import main as verify_hof_geo_year_main
from scripts.amiga.verify_hof_holder_projection import main as verify_hof_holder_projection_main
from scripts.amiga.verify_stored_id_date_pairs import main as verify_stored_id_date_pairs_main
from scripts.amiga.verify_player_slice import main as verify_player_slice_main
from scripts.amiga.verify_country_slice import main as verify_country_slice_main
from scripts.amiga.verify_wc_hof import main as verify_wc_hof_main
from scripts.amiga.verify_perfect_event import main as verify_perfect_event_main
from scripts.amiga.modern.verify_tournament_formats_work import main as verify_tournament_formats_work_main
from scripts.amiga.modern.video_catalog import run_verify_tournament_videos_work

log = logging.getLogger(__name__)

MODERN_VERIFY_STEPS: list[tuple[str, Callable[[], int]]] = [
    ("verify-chronology", verify_chronology_main),
    ("verify-is-world-cup", verify_is_world_cup_main),
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
    ("verify-country-registry", verify_country_registry_main),
    ("verify-player-create", verify_player_create_main),
    ("verify-running-tournament-boundary", verify_running_tournament_boundary_main),
    ("verify-scoring-contract", verify_scoring_contract_main),
    ("verify-php-standings-parity", verify_php_standings_parity_main),
    ("verify-rtb-standings-parity", verify_rtb_standings_parity_main),
    ("verify-tournament-formats", verify_tournament_formats_work_main),
]


def run_modern_verify_suite(*, include_videos: bool = False) -> int:
    activate_work_database_env()
    steps = list(MODERN_VERIFY_STEPS)
    if include_videos:
        steps.append(("verify-tournament-videos", run_verify_tournament_videos_work))

    for name, verify_fn in steps:
        log.info("simul verify: %s", name)
        rc = verify_fn()
        if rc != 0:
            log.error("simul verify failed at %s (exit %s)", name, rc)
            return rc

    log.info("modern verify suite OK (%s steps)", len(steps))
    return 0