#!/usr/bin/env python3
"""CLI: python -m scripts.amiga simul | seed-work | … (forward on ko2amiga_work); prove (oracle only)."""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.finalize_tournament import run_finalize_tournament
from scripts.amiga.import_access import _DEFAULT_L2_DIR, _DEFAULT_MDB, import_all, import_witness
from scripts.amiga.apply_structure import run_apply_structure
from scripts.amiga.import_pristine import (
    _DEFAULT_OUT as _PRISTINE_OUT,
    run_import_pristine,
    verify_pristine_manifest,
)
from scripts.amiga.import_prune import (
    _DEFAULT_L1_DIR,
    _DEFAULT_OUT as _PRUNED_OUT,
    run_import_prune,
    verify_prune_manifest,
)
from scripts.amiga.prove import run_prove
from scripts.amiga.replay import run_replay
from scripts.amiga.honours_parity_sample import main as honours_parity_sample_main
from scripts.amiga.player_tournament_participation import (
    run_participation_refresh_tournament,
    run_refresh_event_finish_snapshots,
)
from scripts.amiga.tournament_builder import main as tournament_builder_main
from scripts.amiga.tournament_format import main as tournament_format_main
from scripts.amiga.tournament_fixtures import main as tournament_fixtures_main
from scripts.amiga.standings_parity import main as standings_parity_main
from scripts.amiga.verify_track_b import main as verify_track_b_main
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_player_matchups import main as verify_player_matchups_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.verify_event_snapshots import main as verify_event_snapshots_main
from scripts.amiga.verify_realm_snapshots import main as verify_realm_snapshots_main
from scripts.amiga.verify_hof_geo_year import main as verify_hof_geo_year_main
from scripts.amiga.verify_hof_holder_projection import main as verify_hof_holder_projection_main
from scripts.amiga.verify_player_slice import main as verify_player_slice_main
from scripts.amiga.verify_wc_hof import main as verify_wc_hof_main
from scripts.amiga.verify_country_slice import main as verify_country_slice_main
from scripts.amiga.verify_stored_id_date_pairs import main as verify_stored_id_date_pairs_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.verify_l2_l3_boundary import main as verify_l2_l3_boundary_main
from scripts.amiga.import_manifest import default_manifest_path
from scripts.amiga.verify_witness import verify_witness
from scripts.amiga.export_packs import (
    ALL_PACKS,
    _DEFAULT_PACKS_ROOT,
    export_all_packs,
    export_pack,
)
from scripts.amiga.modern.constants import DAY0_DIR as _SEED_DAY0_DIR
from scripts.amiga.modern.seal_day0 import _DEFAULT_OUT as _DAY0_OUT, seal_day0
from scripts.amiga.modern.seed_work import seed_work_from_day0
from scripts.amiga.modern.simul import run_simul
from scripts.amiga.modern.apply_structure import run_apply_structure_work
from scripts.amiga.modern.parity import run_parity
from scripts.amiga.modern.verify_structure_work import run_verify_structure_work
from scripts.amiga.modern.video_catalog import (
    run_verify_tournament_videos_work,
    run_video_align_work,
    seal_video_oracle,
    seed_work_video_catalog,
)
from scripts.amiga.verify_export_pack import verify_export_pack
from scripts.amiga.verify_structure import verify_structure
from scripts.amiga.audit_catalog_dates import main as audit_catalog_dates_main
from scripts.amiga.staging_export_tables import (
    main_audit_staging_export,
    main_write_staging_export_tables,
)
from scripts.amiga.tournament_structure.audit import main as audit_suspicious_marathons_main
from scripts.amiga.tournament_structure.materialize_legacy import main as tournament_structure_main
from scripts.amiga.tournament_structure.verify import main as structure_main
from scripts.amiga.tournament_standings import _connect as standings_connect, rebuild_standings_for_tournament
from scripts.amiga.player_registry import main as player_registry_main

log = logging.getLogger("scripts.amiga")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Amiga realm — forward simul on ko2amiga_work; prove = oracle only")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_import = sub.add_parser("import", help="Load Access ground truth into MySQL")
    p_import.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_import.add_argument(
        "--l1-dir",
        type=Path,
        default=_PRISTINE_OUT,
        help="L1 export directory (used with --recreate-schema)",
    )
    p_import.add_argument(
        "--l2-dir",
        type=Path,
        default=_PRUNED_OUT,
        help="L2 pruned witness directory",
    )
    p_import.add_argument(
        "--recreate-schema",
        action="store_true",
        help="Drop and recreate DDL (required unless --incremental)",
    )
    p_import.add_argument(
        "--incremental",
        action="store_true",
        help="Reload ground truth only — import-layer debug; not sign-off (use prove)",
    )

    p_replay = sub.add_parser(
        "replay",
        help="Derived rebuild via tournament finalize (1600 / K=32, frozen within event)",
    )
    p_replay.add_argument("--dry-run", action="store_true")
    p_replay.add_argument(
        "--limit",
        type=int,
        default=None,
        help="Finalize tournaments until at least N games are covered (not N tournaments)",
    )

    p_finalize = sub.add_parser(
        "finalize-tournament",
        help="Finalize one tournament (frozen Elo batch + rating events)",
    )
    p_finalize.add_argument("--tournament-id", type=int, required=True)
    p_finalize.add_argument("--dry-run", action="store_true")

    p_run = sub.add_parser(
        "run",
        help="Nuclear import (--recreate-schema) + replay (use prove for verify suite)",
    )
    p_run.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_run.add_argument("--l1-dir", type=Path, default=_PRISTINE_OUT)
    p_run.add_argument("--l2-dir", type=Path, default=_PRUNED_OUT)
    p_run.add_argument("--dry-run", action="store_true")
    p_run.add_argument("--limit", type=int, default=None)

    p_prove = sub.add_parser(
        "prove",
        help="L1→L2→L3 witness → L4 structure → L5 replay → verify (holy Amiga loop / sign-off)",
    )
    p_prove.add_argument("--mdb", type=Path, default=_DEFAULT_MDB, help="L0 koatd for L1 import-pristine")
    p_prove.add_argument("--l1-dir", type=Path, default=_PRISTINE_OUT)
    p_prove.add_argument("--l2-dir", type=Path, default=_PRUNED_OUT)
    p_prove.add_argument("--dry-run", action="store_true")
    p_prove.add_argument(
        "--limit",
        type=int,
        default=None,
        help="Replay smoke only — not for sign-off",
    )
    p_prove.add_argument(
        "--skip-structure",
        action="store_true",
        help="Skip L4 apply-structure (dev only — not sign-off)",
    )

    p_prove.add_argument(
        "--skip-l1-l2",
        action="store_true",
        help="Skip L1/L2 rebuild — use existing L2 SQL (dev only; not full sign-off)",
    )

    p_pristine = sub.add_parser(
        "import-pristine",
        help="L1 full mechanical koatd.mdb → SQL mirror (all Access tables)",
    )
    p_pristine.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_pristine.add_argument(
        "--out-dir",
        type=Path,
        default=_PRISTINE_OUT,
        help="Output directory for L1_mirror.sql + pristine_manifest.json",
    )
    p_pristine.add_argument(
        "--no-verify",
        action="store_true",
        help="Skip row-count verify against Access after export",
    )

    p_verify_pristine = sub.add_parser(
        "verify-pristine",
        help="Compare pristine_manifest.json row counts to live Access",
    )
    p_verify_pristine.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_verify_pristine.add_argument(
        "--manifest",
        type=Path,
        default=_PRISTINE_OUT / "pristine_manifest.json",
    )

    p_prune = sub.add_parser(
        "import-prune",
        help="L2 hard-prune legacy-derived tables from L1 mirror SQL",
    )
    p_prune.add_argument(
        "--l1-dir",
        type=Path,
        default=_DEFAULT_L1_DIR,
        help="Directory with pristine_manifest.json + L1_mirror.sql",
    )
    p_prune.add_argument(
        "--out-dir",
        type=Path,
        default=_PRUNED_OUT,
        help="Output directory for L2_pruned.sql + prune_manifest.json",
    )
    p_prune.add_argument(
        "--no-verify",
        action="store_true",
        help="Skip partition verify against L1 manifest",
    )

    p_verify_prune = sub.add_parser(
        "verify-prune",
        help="Verify L2 prune_manifest partitions L1 completely",
    )
    p_verify_prune.add_argument(
        "--l1-manifest",
        type=Path,
        default=_DEFAULT_L1_DIR / "pristine_manifest.json",
    )
    p_verify_prune.add_argument(
        "--manifest",
        type=Path,
        default=_PRUNED_OUT / "prune_manifest.json",
    )

    p_witness = sub.add_parser(
        "import-witness",
        help="L3 witness import from L2 SQL (corrections + ground rows; no L4 disposition)",
    )
    p_witness.add_argument(
        "--l2-dir",
        type=Path,
        default=_DEFAULT_L2_DIR,
        help="Directory with L2_pruned.sql + prune_manifest.json",
    )
    p_witness.add_argument(
        "--recreate-ground",
        action="store_true",
        help="Drop and recreate L3/L4 DDL (ground + structure bundles; no L5 derived)",
    )

    p_verify_witness = sub.add_parser(
        "verify-witness",
        help="Assert L3 witness rows, empty L4/L5, manifest complete",
    )
    p_verify_witness.add_argument(
        "--manifest",
        type=Path,
        default=None,
        help="import_manifest.json path (default: data/amiga/exports/import_manifest.json)",
    )

    p_verify_l2_l3 = sub.add_parser(
        "verify-l2-l3",
        help="L2 pruned SQL ↔ L3 witness boundary (manifest lineage, re-prepare parity, nationality)",
    )
    p_verify_l2_l3.add_argument(
        "--l2-dir",
        type=Path,
        default=_PRUNED_OUT,
        help="Directory with L2_pruned.sql",
    )
    p_verify_l2_l3.add_argument(
        "--manifest",
        type=Path,
        default=None,
        help="import_manifest.json path (default: data/amiga/exports/import_manifest.json)",
    )

    p_apply_structure = sub.add_parser(
        "apply-structure",
        help="L4 structure overlay from disposition register (requires L3 witness)",
    )
    p_apply_structure.add_argument(
        "--from-disposition",
        action="store_true",
        help="Dispatch handlers from disposition_register.json (required)",
    )
    p_apply_structure.add_argument(
        "--recreate-structure",
        action="store_true",
        help="Drop and recreate L4 DDL bundle before apply",
    )
    p_apply_structure.add_argument("--tournament-id", type=int, default=None)
    p_apply_structure.add_argument("--limit", type=int, default=None)
    p_apply_structure.add_argument("--dry-run", action="store_true")

    p_verify_structure = sub.add_parser(
        "verify-structure",
        help="Assert L4 STOP gate: Homburg + pure_rr smoke + pending_review clean",
    )

    p_export_pack = sub.add_parser(
        "export-pack",
        help="Export community pack: mirror | ground | structure | product | all",
    )
    p_export_pack.add_argument(
        "pack",
        choices=(*ALL_PACKS, "all"),
        help="Pack profile (all = every pack)",
    )
    p_export_pack.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_export_pack.add_argument(
        "--out-root",
        type=Path,
        default=_DEFAULT_PACKS_ROOT,
        help="Output root (default: data/amiga/exports/packs)",
    )
    p_export_pack.add_argument(
        "--refresh-pristine",
        action="store_true",
        help="Re-run import-pristine before mirror pack",
    )

    p_seal_day0 = sub.add_parser(
        "seal-day0",
        help="Seal L3 witness ground from ko2amiga_db to data/amiga/day0/ (D0-1)",
    )
    p_seal_day0.add_argument(
        "--out-dir",
        type=Path,
        default=_DAY0_OUT,
        help="Output directory (default: data/amiga/day0)",
    )
    p_seal_day0.add_argument(
        "--version",
        type=str,
        default=None,
        help="Manifest version id (default: day0-YYYY-MM-DD)",
    )

    p_seed_work = sub.add_parser(
        "seed-work",
        help="Seed ko2amiga_work from data/amiga/day0/ L3 archive (W-1)",
    )
    p_seed_work.add_argument(
        "--day0-dir",
        type=Path,
        default=_SEED_DAY0_DIR,
        help="Day 0 archive directory (default: data/amiga/day0)",
    )
    p_seed_work.add_argument(
        "--include-schema-part",
        action="store_true",
        help="Also run day0_01_schema.sql (default: skip; apply_schema already ran)",
    )
    p_seed_work.add_argument(
        "--no-recreate",
        action="store_true",
        help="Do not drop existing tables before apply_schema",
    )

    p_simul = sub.add_parser(
        "simul",
        help="Modern simul on ko2amiga_work: L4 + L5 replay + verify (S-1)",
    )
    p_simul.add_argument("--dry-run", action="store_true")
    p_simul.add_argument(
        "--skip-structure",
        action="store_true",
        help="Skip L4 disposition dispatch (dev only)",
    )
    p_simul.add_argument(
        "--apply-structure",
        action="store_true",
        help="Force L4 disposition dispatch even when fixtures exist",
    )
    p_simul.add_argument(
        "--skip-video",
        action="store_true",
        help="Skip video align and verify-tournament-videos-work",
    )
    p_simul.add_argument(
        "--skip-verify",
        action="store_true",
        help="Replay smoke only — skip modern verify suite",
    )
    p_simul.add_argument(
        "--recreate-schema",
        action="store_true",
        help="apply_schema(drop_existing=True) — destructive dev only",
    )

    p_apply_structure_work = sub.add_parser(
        "apply-structure-work",
        help="L4 structure overlay on ko2amiga_work from disposition register",
    )
    p_apply_structure_work.add_argument("--tournament-id", type=int, default=None)
    p_apply_structure_work.add_argument("--limit", type=int, default=None)
    p_apply_structure_work.add_argument("--dry-run", action="store_true")

    p_parity = sub.add_parser(
        "parity",
        help="P-1: compare ko2amiga_work vs frozen ko2amiga_db (counts + checksum)",
    )
    p_parity.add_argument(
        "--no-checksum",
        action="store_true",
        help="Row counts + scalar rows only (faster smoke)",
    )

    p_verify_structure_work = sub.add_parser(
        "verify-structure-work",
        help="L4-1: verify-structure on ko2amiga_work (disposition STOP gate)",
    )

    p_seal_video_oracle = sub.add_parser(
        "seal-video-oracle",
        help="V-1.0: snapshot oracle video catalog to data/amiga/oracle/tournament_videos/",
    )
    p_seed_video_work = sub.add_parser(
        "seed-video-work",
        help="V-1.2: copy shared editorial video files into work compartment",
    )
    p_seed_video_work.add_argument(
        "--force",
        action="store_true",
        help="Overwrite existing work review.csv / sidecar files",
    )
    p_align_video_work = sub.add_parser(
        "align-video-work",
        help="V-1: sync work video catalog to ko2amiga_work + rebuild work manifest",
    )
    p_align_video_work.add_argument("--dry-run", action="store_true")
    p_verify_video_work = sub.add_parser(
        "verify-tournament-videos-work",
        help="V-1: verify work tournament_videos.json against ko2amiga_work",
    )
    p_promote_video_deploy = sub.add_parser(
        "promote-video-deploy",
        help="PROMOTE-1: copy work video manifest to site/public_html deploy paths",
    )

    p_verify_export_pack = sub.add_parser(
        "verify-export-pack",
        help="Verify export pack manifest + STOP gate (structure/product)",
    )
    p_verify_export_pack.add_argument("pack", choices=ALL_PACKS)
    p_verify_export_pack.add_argument("--pack-root", type=Path, default=_DEFAULT_PACKS_ROOT)
    p_verify_export_pack.add_argument(
        "--no-live-db",
        action="store_true",
        help="Skip row-count parity against ko2amiga_db",
    )

    p_parity = sub.add_parser(
        "standings-parity",
        help="Compare derived standings to Access Tables (reference only)",
    )
    p_parity.add_argument("--tournament", help="Single tournament (omit with --sweep)")
    p_parity.add_argument("--scope", choices=("overall", "group"), default="overall")
    p_parity.add_argument("--scope-key", default="")
    p_parity.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_parity.add_argument("--top", type=int, default=10)
    p_parity.add_argument(
        "--sweep",
        action="store_true",
        help="Sweep all tournaments with Access reference standings",
    )
    p_parity.add_argument("--tournament-id", type=int, default=None)
    p_parity.add_argument("--fail-fast", action="store_true")
    p_parity.add_argument("--only-failures", action="store_true")
    p_parity.add_argument("--report", type=Path, default=None)

    sub.add_parser("verify-track-b", help="Post-import smoke: extra column + Elo draw rule")

    sub.add_parser(
        "verify-chronology",
        help="Assert 0 backward game_date transitions in canonical order",
    )

    sub.add_parser(
        "verify-rating-events",
        help="Assert tournament finalize rating invariants (contract § 5.9)",
    )

    sub.add_parser(
        "verify-player-participation",
        help="Assert participation + totals parity (player universe contract §8)",
    )

    sub.add_parser(
        "verify-event-snapshots",
        help="Assert event snapshot + current invariants (snapshot policy §8)",
    )

    sub.add_parser(
        "verify-realm-snapshots",
        help="Assert realm snapshot + generalstats invariants (realm-snapshot policy §7)",
    )
    sub.add_parser(
        "verify-community-stats",
        help="Assert community stats snapshots + facts (community-stats policy §11)",
    )
    sub.add_parser(
        "verify-perfect-event",
        help="Assert perfect-event flags, honours totals, catalog stats, and HoF holder",
    )

    sub.add_parser(
        "verify-hof-geo-year",
        help="Assert geo/year player scalars + HoF holders (hof-tournament-geo policy)",
    )
    sub.add_parser(
        "verify-hof-holder-projection",
        help="Assert HoF holder projection vs independent oracles (stored-field semantics Phase B)",
    )
    sub.add_parser(
        "verify-hof-peak-rating-holder",
        help="Assert HoF peak row uses career PeakRating (BiggestPeakRating retired)",
    )
    sub.add_parser(
        "verify-stored-id-date-pairs",
        help="Assert rise/honours id-date pairing + career-best replay (stored-field semantics Phase C)",
    )

    sub.add_parser(
        "verify-player-slice",
        help="Assert world_cup slice tables vs WC participation/game oracles",
    )

    sub.add_parser(
        "verify-country-slice",
        help="Assert world_cup country slice tables vs compute oracle",
    )

    sub.add_parser(
        "verify-wc-hof",
        help="Assert WC Hall of Fame snapshots/present vs amiga_games + slice oracles",
    )

    sub.add_parser(
        "verify-player-matchups",
        help="Assert H2H summary parity vs amiga_games (player universe contract §8)",
    )

    sub.add_parser(
        "verify-import-manifest",
        help="Assert import_manifest.json and catalog overrides in MySQL",
    )

    sub.add_parser(
        "verify-country-registry",
        help="Assert witness country tokens and choosable flag SVGs match country_registry.json",
    )

    sub.add_parser(
        "verify-player-create",
        help="Assert live_ops amiga_players rows and player_source column",
    )

    sub.add_parser(
        "verify-running-tournament-boundary",
        help="Assert live-ops running tournaments have zero amiga_games until Make official",
    )

    p_promote = sub.add_parser(
        "promote-running-tournament",
        help="Promote running fixture scores into amiga_games (Make official L3 step)",
    )
    p_promote.add_argument("--tournament-id", type=int, required=True)
    p_promote.add_argument("--dry-run", action="store_true")

    p_build_registry = sub.add_parser(
        "build-country-registry",
        help="Build data/amiga/country_registry.json from flag-icons country.json",
    )
    p_build_registry.add_argument("--source", type=Path, default=None)
    p_build_registry.add_argument("--output", type=Path, default=None)

    p_sync_flags = sub.add_parser(
        "sync-country-flags",
        help="Copy vendored flag-icons 4x3 SVGs into site/public_html/img/flags/amiga/",
    )
    p_sync_flags.add_argument("--dry-run", action="store_true")
    p_sync_flags.add_argument("--all-registry-rows", action="store_true")

    sub.add_parser(
        "verify-scoring-contract",
        help="Structural oracle for L4b stage scoring contracts",
    )

    p_php_standings = sub.add_parser(
        "verify-php-standings-parity",
        help="PHP vs Python standings executor parity (SC-5)",
    )
    p_php_standings.add_argument("--tournament-id", type=int, default=None)
    p_php_standings.add_argument("--sample", type=int, default=5)
    p_php_standings.add_argument("--sweep", action="store_true")

    p_rtb_standings = sub.add_parser(
        "verify-rtb-standings-parity",
        help="RTB fixture broadcast vs amiga_games/L5 parity (SC-8)",
    )
    p_rtb_standings.add_argument("--tournament-id", type=int, default=None)
    p_rtb_standings.add_argument("--sample", type=int, default=5)
    p_rtb_standings.add_argument("--sweep", action="store_true")

    p_backfill_stage = sub.add_parser(
        "backfill-standings-stage-id",
        help="SC-9 rebuild L5 standings to populate stage_id",
    )
    p_backfill_stage.add_argument("--tournament-id", type=int, default=None)
    p_backfill_stage.add_argument("--dry-run", action="store_true")

    p_verify_stage = sub.add_parser(
        "verify-standings-stage-id",
        help="SC-9 L5 stage_id dual-write oracle",
    )
    p_verify_stage.add_argument("--tournament-id", type=int, default=None)
    p_verify_stage.add_argument("--sample", type=int, default=10)
    p_verify_stage.add_argument("--sweep", action="store_true")

    p_backfill_ext = sub.add_parser(
        "backfill-match-extensions",
        help="SC-11 backfill structured ET/pens from witness extra text",
    )
    p_backfill_ext.add_argument("--dry-run", action="store_true")

    sub.add_parser(
        "verify-match-extensions",
        help="SC-11 structured match extensions oracle",
    )

    p_list_ext_review = sub.add_parser(
        "list-extension-review",
        help="SC-11 games needing human ET/pens witness review",
    )
    p_list_ext_review.add_argument("--tournament-id", type=int, default=None)
    p_list_ext_review.add_argument("--include-verified", action="store_true")

    p_backfill_scoring = sub.add_parser(
        "backfill-scoring-contracts",
        help="SC-6 explicit L4b contracts on catalog tournaments/stages",
    )
    p_backfill_scoring.add_argument("--tournament-id", type=int, default=None)
    p_backfill_scoring.add_argument("--dry-run", action="store_true")

    p_freeze_scoring = sub.add_parser(
        "freeze-scoring-contracts",
        help="SC-7 freeze L4b contracts on already-finalized tournaments",
    )
    p_freeze_scoring.add_argument("--tournament-id", type=int, default=None)
    p_freeze_scoring.add_argument("--dry-run", action="store_true")

    sub.add_parser(
        "verify-tournament-formats",
        help="Assert imported tournaments with games have league/cup format flags",
    )

    sub.add_parser(
        "audit-catalog-dates",
        help="Scan Access for chrono/date inversions; fail if uncorrected",
    )

    sub.add_parser(
        "write-staging-export-tables",
        help="Write site/public_html/data/amiga/staging_export_tables.json from registry",
    )

    p_audit_staging_export = sub.add_parser(
        "audit-staging-export",
        help="Preflight: registry vs schema bundles + JSON manifest + work DB tables",
    )
    p_audit_staging_export.add_argument(
        "--database",
        default="ko2amiga_work",
        help="MySQL schema to check (default: ko2amiga_work)",
    )
    p_audit_staging_export.add_argument(
        "--skip-db",
        action="store_true",
        help="Registry/JSON checks only (no live DB)",
    )

    p_structure = sub.add_parser(
        "structure",
        help="Tournament structure spec registry (list / verify)",
    )
    p_structure.add_argument("structure_args", nargs=argparse.REMAINDER)

    p_tournament_structure = sub.add_parser(
        "tournament-structure",
        help="Legacy tournament structure materialize / verify",
    )
    p_tournament_structure.add_argument("tournament_structure_args", nargs=argparse.REMAINDER)

    p_standings_rebuild = sub.add_parser(
        "standings-rebuild",
        help="Rebuild amiga_tournament_standings for one tournament from games",
    )
    p_standings_rebuild.add_argument("--tournament-id", type=int, required=True)

    p_marathons = sub.add_parser(
        "audit-suspicious-marathons",
        help="JSON report: NULL phases + uneven/non-RR game counts in Access",
    )
    p_marathons.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_marathons.add_argument("--min-games", type=int, default=10)
    p_marathons.add_argument("--min-players", type=int, default=4)
    p_marathons.add_argument("--out", type=Path, default=None)

    p_part_tournament = sub.add_parser(
        "participation-refresh-tournament",
        help="Incremental participation + totals for one tournament (live finalize hook)",
    )
    p_part_tournament.add_argument("--tournament-id", type=int, required=True)
    p_part_tournament.add_argument(
        "--skip-standings",
        action="store_true",
        help="Standings/catalog already refreshed (PHP finalize path)",
    )
    p_part_tournament.add_argument("--dry-run", action="store_true")

    p_finish_snap = sub.add_parser(
        "refresh-event-finish-snapshots",
        help="Rewrite event_finish_position on snapshots from tiers A–E (+ Tier E overrides)",
    )
    p_finish_snap.add_argument("--tournament-id", type=int, required=True)
    p_finish_snap.add_argument("--dry-run", action="store_true")

    p_honours = sub.add_parser(
        "honours-parity-sample",
        help="Reference report: derived WC medals vs Access added_players (top 20)",
    )
    p_honours.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_honours.add_argument("--limit", type=int, default=20)

    p_fixtures = sub.add_parser(
        "fixtures",
        help="Internal stage/fixture operations for future live tournaments",
    )
    p_fixtures.add_argument("fixture_args", nargs=argparse.REMAINDER)

    p_builder = sub.add_parser(
        "build-tournament",
        help="Internal builder for new fixture-backed tournaments",
    )
    p_builder.add_argument("builder_args", nargs=argparse.REMAINDER)

    p_players = sub.add_parser(
        "players",
        help="Internal KOA-aware player naming and creation (no public UI)",
    )
    p_players.add_argument("player_args", nargs=argparse.REMAINDER)

    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")

    if args.cmd == "import":
        if not args.recreate_schema and not args.incremental:
            log.error(
                "import requires --recreate-schema or --incremental; "
                "for sign-off use: python -m scripts.amiga prove"
            )
            return 1
        if args.incremental:
            log.warning(
                "incremental import: schema unchanged — not the proof path; use prove"
            )
        stats = import_all(
            mdb=args.mdb,
            l1_dir=args.l1_dir,
            l2_dir=args.l2_dir,
            recreate_schema=args.recreate_schema,
        )
        log.info("Import complete: %s", stats)
        return 0

    if args.cmd == "replay":
        run_replay(dry_run=args.dry_run, limit=args.limit)
        return 0

    if args.cmd == "finalize-tournament":
        result = run_finalize_tournament(
            tournament_id=args.tournament_id,
            dry_run=args.dry_run,
        )
        log.info("finalize-tournament complete: %s", result)
        return 0

    if args.cmd == "run":
        stats = import_all(
            mdb=args.mdb,
            l1_dir=args.l1_dir,
            l2_dir=args.l2_dir,
            recreate_schema=True,
        )
        log.info("Import complete: %s", stats)
        run_replay(dry_run=args.dry_run, limit=args.limit)
        return 0

    if args.cmd == "import-pristine":
        stats = run_import_pristine(
            mdb=args.mdb,
            out_dir=args.out_dir,
            verify=not args.no_verify,
        )
        log.info(
            "import-pristine OK: tables=%s rows=%s sql=%s manifest=%s",
            stats["tables"],
            stats["rows_total"],
            stats["sql_path"],
            stats["manifest_path"],
        )
        return 0

    if args.cmd == "verify-pristine":
        errors = verify_pristine_manifest(args.mdb, args.manifest)
        if errors:
            for err in errors:
                log.error("%s", err)
            return 1
        log.info("verify-pristine OK: %s", args.manifest)
        return 0

    if args.cmd == "import-prune":
        stats = run_import_prune(
            l1_dir=args.l1_dir,
            out_dir=args.out_dir,
            verify=not args.no_verify,
        )
        log.info(
            "import-prune OK: retained=%s identity_rows=%s pruned=%s rows_kept=%s sql=%s manifest=%s",
            stats["retained_tables"],
            stats.get("witness_identity_rows"),
            stats["pruned_tables"],
            stats["rows_retained"],
            stats["sql_path"],
            stats["manifest_path"],
        )
        return 0

    if args.cmd == "verify-prune":
        errors = verify_prune_manifest(args.l1_manifest, args.manifest)
        if errors:
            for err in errors:
                log.error("%s", err)
            return 1
        log.info("verify-prune OK: %s", args.manifest)
        return 0

    if args.cmd == "import-witness":
        stats = import_witness(l2_dir=args.l2_dir, recreate_ground=args.recreate_ground)
        log.info("import-witness complete: %s", stats)
        return 0

    if args.cmd == "verify-witness":
        manifest = args.manifest or default_manifest_path(Path(__file__).resolve().parents[2])
        errors = verify_witness(manifest_path=manifest)
        if errors:
            for err in errors:
                log.error("%s", err)
            return 1
        log.info("verify-witness OK: %s", manifest)
        return 0

    if args.cmd == "verify-l2-l3":
        manifest = args.manifest or default_manifest_path(Path(__file__).resolve().parents[2])
        return verify_l2_l3_boundary_main(["--l2-dir", str(args.l2_dir), "--manifest", str(manifest)])

    if args.cmd == "apply-structure":
        stats = run_apply_structure(
            from_disposition=args.from_disposition,
            recreate_structure=args.recreate_structure,
            tournament_id=args.tournament_id,
            limit=args.limit,
            dry_run=args.dry_run,
        )
        log.info("apply-structure complete: %s", stats.to_dict())
        return 0

    if args.cmd == "verify-structure":
        errors = verify_structure()
        if errors:
            for err in errors:
                log.error("%s", err)
            return 1
        log.info("verify-structure OK")
        return 0

    if args.cmd == "seed-work":
        stats = seed_work_from_day0(
            day0_dir=args.day0_dir,
            skip_schema_part=not args.include_schema_part,
            recreate=not args.no_recreate,
        )
        log.info(
            "seed-work OK: version=%s db=%s tournaments=%s players=%s games=%s parts=%s",
            stats["version"],
            stats["database"],
            stats["l3_counts"]["tournaments"],
            stats["l3_counts"]["amiga_players"],
            stats["l3_counts"]["amiga_games"],
            len(stats["loaded_parts"]),
        )
        return 0

    if args.cmd == "simul":
        return run_simul(
            dry_run=args.dry_run,
            skip_structure=args.skip_structure,
            apply_structure=args.apply_structure,
            skip_video=args.skip_video,
            skip_verify=args.skip_verify,
            recreate_schema=args.recreate_schema,
        )

    if args.cmd == "apply-structure-work":
        stats = run_apply_structure_work(
            tournament_id=args.tournament_id,
            limit=args.limit,
            dry_run=args.dry_run,
        )
        log.info("apply-structure-work complete: %s", stats.to_dict())
        return 0

    if args.cmd == "parity":
        run_parity(checksum=not args.no_checksum)
        return 0

    if args.cmd == "verify-structure-work":
        return run_verify_structure_work()

    if args.cmd == "seal-video-oracle":
        seal_video_oracle()
        return 0

    if args.cmd == "seed-video-work":
        seed_work_video_catalog(force=args.force)
        return 0

    if args.cmd == "align-video-work":
        return run_video_align_work(dry_run=args.dry_run)

    if args.cmd == "verify-tournament-videos-work":
        return run_verify_tournament_videos_work()

    if args.cmd == "promote-video-deploy":
        from scripts.amiga.modern.video_catalog import promote_work_video_deploy

        promote_work_video_deploy()
        return 0

    if args.cmd == "seal-day0":
        stats = seal_day0(out_dir=args.out_dir, version=args.version)
        log.info(
            "seal-day0 OK: version=%s tournaments=%s players=%s games=%s parts=%s",
            stats["version"],
            stats["row_counts"]["tournaments"],
            stats["row_counts"]["amiga_players"],
            stats["row_counts"]["amiga_games"],
            stats["sql_parts"],
        )
        return 0

    if args.cmd == "export-pack":
        if args.pack == "all":
            results = export_all_packs(
                out_root=args.out_root,
                mdb=args.mdb,
                refresh_pristine=args.refresh_pristine,
            )
            for stats in results:
                log.info(
                    "export-pack %s OK: %s rows=%s files=%s",
                    stats["pack"],
                    stats.get("out_dir"),
                    stats.get("rows_total", stats.get("table_count", "—")),
                    len(stats.get("files", [])),
                )
        else:
            stats = export_pack(
                args.pack,
                out_root=args.out_root,
                mdb=args.mdb,
                refresh_pristine=args.refresh_pristine,
            )
            log.info("export-pack %s OK: %s", stats["pack"], stats)
        return 0

    if args.cmd == "verify-export-pack":
        errors = verify_export_pack(
            args.pack,
            pack_root=args.pack_root,
            check_live_db=not args.no_live_db,
        )
        if errors:
            for err in errors:
                log.error("%s", err)
            return 1
        log.info("verify-export-pack OK: %s", args.pack)
        return 0

    if args.cmd == "prove":
        return run_prove(
            mdb=args.mdb,
            l1_dir=args.l1_dir,
            l2_dir=args.l2_dir,
            dry_run=args.dry_run,
            limit=args.limit,
            skip_structure=args.skip_structure,
            skip_l1_l2=args.skip_l1_l2,
        )

    if args.cmd == "verify-track-b":
        return verify_track_b_main()

    if args.cmd == "verify-chronology":
        return verify_chronology_main()

    if args.cmd == "verify-rating-events":
        return verify_rating_events_main()

    if args.cmd == "verify-event-snapshots":
        return verify_event_snapshots_main()

    if args.cmd == "verify-realm-snapshots":
        return verify_realm_snapshots_main()

    if args.cmd == "verify-community-stats":
        from scripts.amiga.verify_community_stats import main as verify_community_stats_main

        return verify_community_stats_main()

    if args.cmd == "verify-perfect-event":
        from scripts.amiga.verify_perfect_event import main as verify_perfect_event_main

        return verify_perfect_event_main()

    if args.cmd == "verify-hof-geo-year":
        return verify_hof_geo_year_main()

    if args.cmd == "verify-hof-holder-projection":
        return verify_hof_holder_projection_main()

    if args.cmd == "verify-hof-peak-rating-holder":
        from scripts.amiga.verify_hof_peak_rating_holder import main as verify_hof_peak_rating_holder_main

        return verify_hof_peak_rating_holder_main()

    if args.cmd == "verify-stored-id-date-pairs":
        return verify_stored_id_date_pairs_main()

    if args.cmd == "verify-player-participation":
        return verify_player_participation_main()

    if args.cmd == "verify-player-slice":
        return verify_player_slice_main()

    if args.cmd == "verify-country-slice":
        return verify_country_slice_main()

    if args.cmd == "verify-wc-hof":
        return verify_wc_hof_main()

    if args.cmd == "verify-player-matchups":
        return verify_player_matchups_main()

    if args.cmd == "verify-import-manifest":
        return verify_import_manifest_main()

    if args.cmd == "verify-country-registry":
        from scripts.amiga.verify_country_registry import main as verify_country_registry_main

        return verify_country_registry_main()

    if args.cmd == "verify-player-create":
        from scripts.amiga.verify_player_create import main as verify_player_create_main

        return verify_player_create_main()

    if args.cmd == "verify-scoring-contract":
        from scripts.amiga.verify_scoring_contract import main as verify_scoring_contract_main

        return verify_scoring_contract_main()

    if args.cmd == "verify-php-standings-parity":
        from scripts.amiga.verify_php_standings_parity import main as verify_php_standings_parity_main

        parity_argv: list[str] = []
        if args.tournament_id is not None:
            parity_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.sample != 5:
            parity_argv.extend(["--sample", str(args.sample)])
        if args.sweep:
            parity_argv.append("--sweep")
        return verify_php_standings_parity_main(parity_argv)

    if args.cmd == "verify-rtb-standings-parity":
        from scripts.amiga.verify_rtb_standings_parity import main as verify_rtb_standings_parity_main

        rtb_argv: list[str] = []
        if args.tournament_id is not None:
            rtb_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.sample != 5:
            rtb_argv.extend(["--sample", str(args.sample)])
        if args.sweep:
            rtb_argv.append("--sweep")
        return verify_rtb_standings_parity_main(rtb_argv)

    if args.cmd == "backfill-standings-stage-id":
        from scripts.amiga.backfill_standings_stage_id import main as backfill_standings_stage_id_main

        backfill_stage_argv: list[str] = []
        if args.tournament_id is not None:
            backfill_stage_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.dry_run:
            backfill_stage_argv.append("--dry-run")
        return backfill_standings_stage_id_main(backfill_stage_argv)

    if args.cmd == "verify-standings-stage-id":
        from scripts.amiga.verify_standings_stage_id import main as verify_standings_stage_id_main

        verify_stage_argv: list[str] = []
        if args.tournament_id is not None:
            verify_stage_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.sample != 10:
            verify_stage_argv.extend(["--sample", str(args.sample)])
        if args.sweep:
            verify_stage_argv.append("--sweep")
        return verify_standings_stage_id_main(verify_stage_argv)

    if args.cmd == "backfill-match-extensions":
        from scripts.amiga.backfill_match_extensions import main as backfill_match_extensions_main

        ext_argv: list[str] = []
        if args.dry_run:
            ext_argv.append("--dry-run")
        return backfill_match_extensions_main(ext_argv)

    if args.cmd == "verify-match-extensions":
        from scripts.amiga.verify_match_extensions import main as verify_match_extensions_main

        return verify_match_extensions_main([])

    if args.cmd == "list-extension-review":
        from scripts.amiga.list_extension_review_candidates import main as list_extension_review_main

        review_argv: list[str] = []
        if args.tournament_id is not None:
            review_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.include_verified:
            review_argv.append("--include-verified")
        return list_extension_review_main(review_argv)

    if args.cmd == "backfill-scoring-contracts":
        from scripts.amiga.backfill_scoring_contracts import main as backfill_scoring_contracts_main

        backfill_argv: list[str] = []
        if args.tournament_id is not None:
            backfill_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.dry_run:
            backfill_argv.append("--dry-run")
        return backfill_scoring_contracts_main(backfill_argv)

    if args.cmd == "freeze-scoring-contracts":
        from scripts.amiga.freeze_scoring_contracts import main as freeze_scoring_contracts_main

        freeze_argv: list[str] = []
        if args.tournament_id is not None:
            freeze_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.dry_run:
            freeze_argv.append("--dry-run")
        return freeze_scoring_contracts_main(freeze_argv)

    if args.cmd == "verify-running-tournament-boundary":
        from scripts.amiga.verify_running_tournament_boundary import main as verify_running_tournament_boundary_main

        return verify_running_tournament_boundary_main()

    if args.cmd == "promote-running-tournament":
        from scripts.amiga.config import load_amiga_db_config
        from scripts.amiga.promote_running_tournament import promote_running_tournament
        from scripts.amiga.replay import _connect

        cfg = load_amiga_db_config()
        conn = _connect(cfg)
        try:
            result = promote_running_tournament(conn, args.tournament_id, dry_run=args.dry_run)
        finally:
            conn.close()
        print(result)
        return 0

    if args.cmd == "build-country-registry":
        from scripts.amiga.build_country_registry import main as build_country_registry_main

        br_argv: list[str] = []
        if args.source is not None:
            br_argv.extend(["--source", str(args.source)])
        if args.output is not None:
            br_argv.extend(["--output", str(args.output)])
        return build_country_registry_main(br_argv)

    if args.cmd == "sync-country-flags":
        from scripts.amiga.sync_country_flag_svgs import main as sync_country_flag_svgs_main

        sf_argv: list[str] = []
        if args.dry_run:
            sf_argv.append("--dry-run")
        if args.all_registry_rows:
            sf_argv.append("--all-registry-rows")
        return sync_country_flag_svgs_main(sf_argv)

    if args.cmd == "verify-tournament-formats":
        return tournament_format_main([])

    if args.cmd == "audit-catalog-dates":
        return audit_catalog_dates_main()

    if args.cmd == "write-staging-export-tables":
        return main_write_staging_export_tables()

    if args.cmd == "audit-staging-export":
        audit_argv: list[str] = ["--database", args.database]
        if args.skip_db:
            audit_argv.append("--skip-db")
        return main_audit_staging_export(audit_argv)

    if args.cmd == "structure":
        return structure_main(args.structure_args or ["list"])

    if args.cmd == "tournament-structure":
        return tournament_structure_main(args.tournament_structure_args or [])

    if args.cmd == "standings-rebuild":
        conn = standings_connect()
        try:
            row_count = rebuild_standings_for_tournament(conn, args.tournament_id)
            log.info(
                "standings-rebuild complete tournament_id=%s rows=%s",
                args.tournament_id,
                row_count,
            )
        finally:
            conn.close()
        return 0

    if args.cmd == "audit-suspicious-marathons":
        marathon_argv: list[str] = ["--mdb", str(args.mdb)]
        if args.min_games != 10:
            marathon_argv.extend(["--min-games", str(args.min_games)])
        if args.min_players != 4:
            marathon_argv.extend(["--min-players", str(args.min_players)])
        if args.out is not None:
            marathon_argv.extend(["--out", str(args.out)])
        return audit_suspicious_marathons_main(marathon_argv)

    if args.cmd == "participation-refresh-tournament":
        part_rows, totals_players = run_participation_refresh_tournament(
            args.tournament_id,
            skip_standings=args.skip_standings,
            dry_run=args.dry_run,
        )
        log.info(
            "participation-refresh-tournament complete: tournament_id=%s participation=%s totals_players=%s",
            args.tournament_id,
            part_rows,
            totals_players,
        )
        return 0

    if args.cmd == "refresh-event-finish-snapshots":
        updated = run_refresh_event_finish_snapshots(
            args.tournament_id,
            dry_run=args.dry_run,
        )
        log.info(
            "refresh-event-finish-snapshots complete: tournament_id=%s updated=%s",
            args.tournament_id,
            updated,
        )
        return 0

    if args.cmd == "honours-parity-sample":
        honours_argv: list[str] = []
        if args.mdb != _DEFAULT_MDB:
            honours_argv.extend(["--mdb", str(args.mdb)])
        if args.limit != 20:
            honours_argv.extend(["--limit", str(args.limit)])
        return honours_parity_sample_main(honours_argv)

    if args.cmd == "fixtures":
        return tournament_fixtures_main(args.fixture_args)

    if args.cmd == "build-tournament":
        return tournament_builder_main(args.builder_args)

    if args.cmd == "players":
        return player_registry_main(args.player_args)

    if args.cmd == "standings-parity":
        parity_argv: list[str] = [
            "--scope",
            args.scope,
            "--scope-key",
            args.scope_key,
            "--mdb",
            str(args.mdb),
            "--top",
            str(args.top),
        ]
        if args.sweep:
            parity_argv.append("--sweep")
        if args.tournament:
            parity_argv.extend(["--tournament", args.tournament])
        if args.tournament_id is not None:
            parity_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.fail_fast:
            parity_argv.append("--fail-fast")
        if args.only_failures:
            parity_argv.append("--only-failures")
        if args.report is not None:
            parity_argv.extend(["--report", str(args.report)])
        return standings_parity_main(parity_argv)

    return 1


if __name__ == "__main__":
    sys.exit(main())
