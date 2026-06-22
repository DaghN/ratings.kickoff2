#!/usr/bin/env python3
"""CLI: python -m scripts.amiga prove | import | replay | run"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.finalize_tournament import run_finalize_tournament
from scripts.amiga.import_access import _DEFAULT_MDB, import_all, import_witness
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
from scripts.amiga.player_matchup_summary import run_matchup_rebuild
from scripts.amiga.server_records import run_generalstats_rebuild
from scripts.amiga.performance_rating import run_performance_rating_rebuild
from scripts.amiga.player_tournament_participation import (
    run_participation_rebuild,
    run_participation_refresh_tournament,
)
from scripts.amiga.rebuild_event_snapshots import run_rebuild_event_snapshots
from scripts.amiga.tournament_catalog_stats import run_catalog_stats_rebuild
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
from scripts.amiga.verify_stored_id_date_pairs import main as verify_stored_id_date_pairs_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.import_manifest import default_manifest_path
from scripts.amiga.verify_witness import verify_witness
from scripts.amiga.export_packs import (
    ALL_PACKS,
    _DEFAULT_PACKS_ROOT,
    export_all_packs,
    export_pack,
)
from scripts.amiga.verify_export_pack import verify_export_pack
from scripts.amiga.verify_structure import verify_structure
from scripts.amiga.audit_catalog_dates import main as audit_catalog_dates_main
from scripts.amiga.tournament_structure.audit import main as audit_suspicious_marathons_main
from scripts.amiga.tournament_structure.materialize_legacy import main as tournament_structure_main
from scripts.amiga.tournament_structure.verify import main as structure_main
from scripts.amiga.tournament_standings import _connect as standings_connect, rebuild_standings_for_tournament
from scripts.amiga.player_registry import main as player_registry_main

log = logging.getLogger("scripts.amiga")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Amiga realm import + Elo replay (ko2amiga_db)")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_import = sub.add_parser("import", help="Load Access ground truth into MySQL")
    p_import.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
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
    p_run.add_argument("--dry-run", action="store_true")
    p_run.add_argument("--limit", type=int, default=None)

    p_prove = sub.add_parser(
        "prove",
        help="L3 witness → L4 structure → L5 replay → verify (holy Amiga loop / sign-off)",
    )
    p_prove.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
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
        help="L3 witness import from Access (corrections + ground rows; no L4 disposition)",
    )
    p_witness.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
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
        "verify-hof-geo-year",
        help="Assert geo/year player scalars + HoF holders (hof-tournament-geo policy)",
    )
    sub.add_parser(
        "verify-hof-holder-projection",
        help="Assert HoF holder projection vs independent oracles (stored-field semantics Phase B)",
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
        "verify-player-matchups",
        help="Assert H2H summary parity vs amiga_games (player universe contract §8)",
    )

    sub.add_parser(
        "verify-import-manifest",
        help="Assert import_manifest.json and catalog overrides in MySQL",
    )

    sub.add_parser(
        "verify-tournament-formats",
        help="Assert imported tournaments with games have league/cup format flags",
    )

    sub.add_parser(
        "audit-catalog-dates",
        help="Scan Access for chrono/date inversions; fail if uncorrected",
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

    p_catalog = sub.add_parser(
        "catalog-stats-rebuild",
        help="Rebuild amiga_tournament_catalog_stats (tournament index aggregates)",
    )
    p_catalog.add_argument("--dry-run", action="store_true")

    p_performance = sub.add_parser(
        "performance-rating-rebuild",
        help="Recompute amiga_rating_events.performance_rating from amiga_game_ratings",
    )
    p_performance.add_argument("--tournament-id", type=int, default=None)
    p_performance.add_argument("--dry-run", action="store_true")

    p_participation = sub.add_parser(
        "participation-rebuild",
        help="Rebuild amiga_player_tournament_participation + totals from standings",
    )
    p_participation.add_argument("--dry-run", action="store_true")

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

    p_event_snapshots = sub.add_parser(
        "rebuild-event-snapshots",
        help="Rebuild amiga_player_event_snapshots + amiga_player_current from history",
    )
    p_event_snapshots.add_argument("--dry-run", action="store_true")

    p_matchup = sub.add_parser(
        "matchup-rebuild",
        help="Rebuild amiga_player_matchup_summary from amiga_games",
    )
    p_matchup.add_argument("--dry-run", action="store_true")

    p_generalstats = sub.add_parser(
        "generalstats-rebuild",
        help="Rebuild amiga_generalstats row id=1 (server hall-of-fame)",
    )
    p_generalstats.add_argument("--dry-run", action="store_true")

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
        stats = import_all(mdb=args.mdb, recreate_schema=True)
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
        stats = import_witness(mdb=args.mdb, recreate_ground=args.recreate_ground)
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
            dry_run=args.dry_run,
            limit=args.limit,
            skip_structure=args.skip_structure,
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

    if args.cmd == "verify-hof-geo-year":
        return verify_hof_geo_year_main()

    if args.cmd == "verify-hof-holder-projection":
        return verify_hof_holder_projection_main()

    if args.cmd == "verify-stored-id-date-pairs":
        return verify_stored_id_date_pairs_main()

    if args.cmd == "verify-player-participation":
        return verify_player_participation_main()

    if args.cmd == "verify-player-slice":
        return verify_player_slice_main()

    if args.cmd == "verify-player-matchups":
        return verify_player_matchups_main()

    if args.cmd == "verify-import-manifest":
        return verify_import_manifest_main()

    if args.cmd == "verify-tournament-formats":
        return tournament_format_main([])

    if args.cmd == "audit-catalog-dates":
        return audit_catalog_dates_main()

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

    if args.cmd == "catalog-stats-rebuild":
        run_catalog_stats_rebuild(dry_run=args.dry_run)
        return 0

    if args.cmd == "performance-rating-rebuild":
        updated = run_performance_rating_rebuild(
            tournament_id=args.tournament_id,
            dry_run=args.dry_run,
        )
        log.info("performance-rating-rebuild complete: rating_events=%s", updated)
        return 0

    if args.cmd == "participation-rebuild":
        participation_rows, totals_rows = run_participation_rebuild(dry_run=args.dry_run)
        log.info(
            "participation-rebuild complete: participation=%s totals=%s",
            participation_rows,
            totals_rows,
        )
        return 0

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

    if args.cmd == "rebuild-event-snapshots":
        stats = run_rebuild_event_snapshots(dry_run=args.dry_run)
        log.info("rebuild-event-snapshots complete: %s", stats)
        return 0

    if args.cmd == "matchup-rebuild":
        rows = run_matchup_rebuild(dry_run=args.dry_run)
        log.info("matchup-rebuild complete: rows=%s", rows)
        return 0

    if args.cmd == "generalstats-rebuild":
        patch = run_generalstats_rebuild(dry_run=args.dry_run)
        log.info("generalstats-rebuild complete: GamesPlayed=%s", patch.get("GamesPlayed"))
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
