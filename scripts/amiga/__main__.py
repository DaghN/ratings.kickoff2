#!/usr/bin/env python3
"""CLI: python -m scripts.amiga import | replay | run"""

from __future__ import annotations

import argparse
import logging
import sys
from pathlib import Path

from scripts.amiga.finalize_tournament import run_finalize_tournament
from scripts.amiga.refinalize import run_refinalize_from, run_reopen_tournament
from scripts.amiga.refinalize_smoke import main as refinalize_smoke_main
from scripts.amiga.import_access import _DEFAULT_MDB, import_all
from scripts.amiga.replay import run_replay
from scripts.amiga.honours_parity_sample import main as honours_parity_sample_main
from scripts.amiga.player_tournament_participation import run_participation_rebuild
from scripts.amiga.tournament_catalog_stats import run_catalog_stats_rebuild
from scripts.amiga.tournament_builder import main as tournament_builder_main
from scripts.amiga.tournament_format import main as tournament_format_main
from scripts.amiga.tournament_fixtures import main as tournament_fixtures_main
from scripts.amiga.standings_parity import main as standings_parity_main
from scripts.amiga.verify_track_b import main as verify_track_b_main
from scripts.amiga.verify_chronology import main as verify_chronology_main
from scripts.amiga.verify_player_participation import main as verify_player_participation_main
from scripts.amiga.verify_rating_events import main as verify_rating_events_main
from scripts.amiga.verify_import_manifest import main as verify_import_manifest_main
from scripts.amiga.audit_catalog_dates import main as audit_catalog_dates_main
from scripts.amiga.tournament_structure.audit import main as audit_suspicious_marathons_main
from scripts.amiga.tournament_structure.verify import main as structure_main
from scripts.amiga.player_registry import main as player_registry_main

log = logging.getLogger("scripts.amiga")


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Amiga realm import + Elo replay (ko2amiga_db)")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_import = sub.add_parser("import", help="Load Access ground truth into MySQL")
    p_import.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_import.add_argument("--recreate-schema", action="store_true", help="Apply 001_core.sql first")

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

    p_reopen = sub.add_parser(
        "reopen-tournament",
        help="Clear finalize markers + T derived rows (requires refinalize-from for global stats)",
    )
    p_reopen.add_argument("--tournament-id", type=int, required=True)
    p_reopen.add_argument("--dry-run", action="store_true")

    p_refinalize = sub.add_parser(
        "refinalize-from",
        help="Rebuild-forward: refinalize tournament T and all later tournaments",
    )
    p_refinalize.add_argument("--tournament-id", type=int, required=True)
    p_refinalize.add_argument("--dry-run", action="store_true")

    p_smoke = sub.add_parser(
        "refinalize-smoke",
        help="Smoke test: tweak one goal + refinalize-from (default: last tournament)",
    )
    p_smoke.add_argument("--tournament-id", type=int, default=None)
    p_smoke.add_argument("--game-id", type=int, default=None)
    p_smoke.add_argument("--dry-run", action="store_true")

    p_run = sub.add_parser("run", help="import + replay")
    p_run.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    p_run.add_argument("--recreate-schema", action="store_true")
    p_run.add_argument("--dry-run", action="store_true")
    p_run.add_argument("--limit", type=int, default=None)

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

    p_participation = sub.add_parser(
        "participation-rebuild",
        help="Rebuild amiga_player_tournament_participation + totals from standings",
    )
    p_participation.add_argument("--dry-run", action="store_true")

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
        stats = import_all(mdb=args.mdb, recreate_schema=args.recreate_schema)
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

    if args.cmd == "reopen-tournament":
        result = run_reopen_tournament(
            tournament_id=args.tournament_id,
            dry_run=args.dry_run,
        )
        log.info("reopen-tournament complete: %s", result)
        return 0

    if args.cmd == "refinalize-from":
        result = run_refinalize_from(
            tournament_id=args.tournament_id,
            dry_run=args.dry_run,
        )
        log.info("refinalize-from complete: %s", result)
        return 0

    if args.cmd == "refinalize-smoke":
        smoke_argv = []
        if args.tournament_id is not None:
            smoke_argv.extend(["--tournament-id", str(args.tournament_id)])
        if args.game_id is not None:
            smoke_argv.extend(["--game-id", str(args.game_id)])
        if args.dry_run:
            smoke_argv.append("--dry-run")
        return refinalize_smoke_main(smoke_argv)

    if args.cmd == "run":
        stats = import_all(mdb=args.mdb, recreate_schema=args.recreate_schema)
        log.info("Import complete: %s", stats)
        run_replay(dry_run=args.dry_run, limit=args.limit)
        return 0

    if args.cmd == "verify-track-b":
        return verify_track_b_main()

    if args.cmd == "verify-chronology":
        return verify_chronology_main()

    if args.cmd == "verify-rating-events":
        return verify_rating_events_main()

    if args.cmd == "verify-player-participation":
        return verify_player_participation_main()

    if args.cmd == "verify-import-manifest":
        return verify_import_manifest_main()

    if args.cmd == "verify-tournament-formats":
        return tournament_format_main([])

    if args.cmd == "audit-catalog-dates":
        return audit_catalog_dates_main()

    if args.cmd == "structure":
        return structure_main(args.structure_args or ["list"])

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

    if args.cmd == "participation-rebuild":
        participation_rows, totals_rows = run_participation_rebuild(dry_run=args.dry_run)
        log.info(
            "participation-rebuild complete: participation=%s totals=%s",
            participation_rows,
            totals_rows,
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
