"""Post-game A/B orchestrator — Mode A parity gate (docs/post-game-php-development.md §7–9).

Default: zero-derived → PHP replay-to → sanity → snapshot → Python ladder run → diff layer 1.

  python -m scripts.work_prepare ab-post-game --target local-work --limit 100
"""

from __future__ import annotations

import argparse
import logging
import os
import shutil
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pymysql

from .ab_chronology import list_game_ids_chronological
from .ab_period_activity import (
    create_period_activity_snapshots,
    diff_period_activity_layers,
    drop_period_activity_snapshots,
)
from .ab_period_aggregates import (
    create_period_aggregate_snapshots,
    diff_period_aggregate_layers,
    drop_period_aggregate_snapshots,
)
from .ab_milestones import (
    P6_PARITY_EXCLUDE_KEYS,
    create_milestones_snapshot,
    diff_milestones_layer,
    drop_milestones_snapshot,
)
from .ab_generalstats import (
    SNAPSHOT_TABLE as GENERALSTATS_SNAPSHOT_TABLE,
    create_generalstats_snapshot,
    diff_generalstats_layer,
    drop_generalstats_snapshot,
)
from .ab_playertable import (
    SNAPSHOT_TABLE as PLAYERTABLE_SNAPSHOT_TABLE,
    checkpoint_player_ids,
    create_playertable_snapshot,
    diff_playertable_layer,
    drop_playertable_snapshot,
)
from .ab_layers import (
    FLOAT_TOLERANCE,
    LAYER_REGISTRY,
    RATEDRESULTS_DERIVED,
    RATEDRESULTS_FLOAT,
    parse_layers_arg,
)
from .db import connect
from .guards import assert_mutate_work_target
from .parity import print_parity_report, run_parity_checks
from .paths import REPO_ROOT
from .prepare import prepare_full
from .targets import WorkTarget
from .zero_derived import zero_derived

log = logging.getLogger(__name__)

SNAPSHOT_TABLE = "parity_ab_ratedresults_php"

# work_prepare profile → (ladder --target, ladder.ini relative to repo)
_LADDER_BY_PROFILE: dict[str, tuple[str, str]] = {
    "local-work": ("sandbox", "site/config/ladder-work.ini"),
}


@dataclass(frozen=True)
class AbPostGameOptions:
    target: WorkTarget
    limit: int | None
    until_game_id: int | None
    layers: tuple[int, ...]
    prepare_mode: str  # zero-only | full | skip
    run_ground_parity: bool
    run_sanity: bool
    keep_snapshot: bool
    dry_run: bool
    php_bin: str | None


_PHP_CANDIDATES = (
    Path(r"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"),
    Path(r"C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"),
    Path(r"C:\laragon\bin\php\php-8.2.12-Win32-vs16-x64\php.exe"),
)


def _resolve_php_bin(explicit: str | None) -> str:
    if explicit:
        return explicit
    env = os.environ.get("K2_PHP_BIN")
    if env:
        return env
    found = shutil.which("php")
    if found:
        return found
    for path in _PHP_CANDIDATES:
        if path.is_file():
            return str(path)
    raise SystemExit(
        "PHP executable not found. Add php to PATH, set K2_PHP_BIN, or install Laragon PHP "
        "(e.g. C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe)."
    )


def _ladder_ini_path(repo: Path, profile: str) -> Path:
    if profile not in _LADDER_BY_PROFILE:
        raise SystemExit(
            f"ab-post-game for profile {profile!r} is not configured. "
            f"Supported: {', '.join(sorted(_LADDER_BY_PROFILE))}."
        )
    _target_name, rel = _LADDER_BY_PROFILE[profile]
    ini = repo / rel
    if not ini.is_file():
        raise SystemExit(
            f"Missing ladder ini: {ini}\n"
            f"Copy {repo / rel}.example to {ini.name} under site/config/."
        )
    return ini


def _run_subprocess(cmd: list[str], *, step: str, dry_run: bool) -> None:
    log.info("[%s] %s", step, " ".join(cmd))
    if dry_run:
        return
    proc = subprocess.run(cmd, cwd=None)
    if proc.returncode != 0:
        raise SystemExit(f"{step} failed (exit {proc.returncode})")


def _run_php_replay(
    repo: Path,
    opts: AbPostGameOptions,
    game_count: int,
) -> None:
    php = _resolve_php_bin(opts.php_bin)
    runner = repo / "site/public_html/ops/run_process_game.php"
    cmd = [php, str(runner), "replay-to", "--target", opts.target.profile]
    if opts.limit is not None:
        cmd.extend(["--limit", str(opts.limit)])
    if opts.until_game_id is not None:
        cmd.extend(["--until-game-id", str(opts.until_game_id)])
    if opts.dry_run:
        cmd.append("--dry-run")
    _run_subprocess(cmd, step="php-replay", dry_run=opts.dry_run)
    log.info("php-replay: checkpoint covers %s games", game_count)


def _run_sanity_check(repo: Path, profile: str, game_count: int, *, dry_run: bool) -> None:
    if profile != "local-work":
        log.warning("sanity check skipped (only wired for local-work → sandbox)")
        return
    script = repo / "scripts/oneoff/verify_ratedresults_derived_rows.py"
    cmd = [
        sys.executable,
        str(script),
        "--target",
        "sandbox",
        "--limit",
        str(game_count),
    ]
    _run_subprocess(cmd, step="sanity-verify", dry_run=dry_run)


def _create_snapshot(
    conn: pymysql.connections.Connection,
    game_ids: list[int],
    *,
    dry_run: bool,
) -> None:
    if not game_ids:
        raise SystemExit("Checkpoint has zero games — nothing to snapshot.")

    cols_sql = ", ".join(f"`{c}`" for c in RATEDRESULTS_DERIVED)
    placeholders = ", ".join(["%s"] * len(game_ids))
    select_cols = ", ".join(f"r.`{c}`" for c in RATEDRESULTS_DERIVED)

    col_defs: list[str] = ["game_id INT NOT NULL PRIMARY KEY"]
    for c in RATEDRESULTS_DERIVED:
        if c in RATEDRESULTS_FLOAT:
            col_defs.append(f"`{c}` DOUBLE NULL")
        else:
            col_defs.append(f"`{c}` INT NULL")
    ddl = f"CREATE TABLE `{SNAPSHOT_TABLE}` ({', '.join(col_defs)})"

    with conn.cursor() as cur:
        log.info("snapshot: DROP TABLE IF EXISTS %s", SNAPSHOT_TABLE)
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
            cur.execute(ddl)
            insert_sql = (
                f"INSERT INTO `{SNAPSHOT_TABLE}` (game_id, {cols_sql}) "
                f"SELECT r.id, {select_cols} FROM ratedresults r "
                f"WHERE r.id IN ({placeholders})"
            )
            cur.execute(insert_sql, game_ids)
            cur.execute(f"SELECT COUNT(*) AS n FROM `{SNAPSHOT_TABLE}`")
            n = int(cur.fetchone()["n"])
            if n != len(game_ids):
                raise SystemExit(
                    f"Snapshot incomplete: expected {len(game_ids)} rows, got {n}"
                )
    if not dry_run:
        conn.commit()
    log.info("snapshot: %s rows in %s", len(game_ids), SNAPSHOT_TABLE)


def _run_python_oracle(
    repo: Path,
    profile: str,
    *,
    limit: int,
    dry_run: bool,
) -> None:
    if profile not in _LADDER_BY_PROFILE:
        raise SystemExit(f"Python oracle not configured for profile {profile!r}")

    ladder_target, _rel = _LADDER_BY_PROFILE[profile]
    ini = _ladder_ini_path(repo, profile)
    cmd = [
        sys.executable,
        "-m",
        "scripts.ladder",
        "run",
        "--target",
        ladder_target,
        "--ini",
        str(ini),
        "--limit",
        str(limit),
    ]
    if dry_run:
        cmd.append("--dry-run")
    _run_subprocess(cmd, step="python-oracle", dry_run=dry_run)


def _values_equal(col: str, php_val: Any, py_val: Any) -> bool:
    if php_val is None and py_val is None:
        return True
    if php_val is None or py_val is None:
        return False
    if col in RATEDRESULTS_FLOAT:
        return abs(float(php_val) - float(py_val)) <= FLOAT_TOLERANCE
    return int(php_val) == int(py_val)


def _diff_ratedresults_layer(
    conn: pymysql.connections.Connection,
    game_ids: list[int],
    *,
    max_report: int = 15,
) -> tuple[int, list[str]]:
    if not game_ids:
        return 0, ["no games in checkpoint"]

    cols = ", ".join(f"r.`{c}` AS py_{c}" for c in RATEDRESULTS_DERIVED)
    snap_cols = ", ".join(f"s.`{c}` AS php_{c}" for c in RATEDRESULTS_DERIVED)
    placeholders = ", ".join(["%s"] * len(game_ids))

    sql = f"""
        SELECT r.id AS game_id, {cols}, {snap_cols}
        FROM ratedresults r
        INNER JOIN `{SNAPSHOT_TABLE}` s ON s.game_id = r.id
        WHERE r.id IN ({placeholders})
        ORDER BY r.id ASC
    """

    mismatches = 0
    lines: list[str] = []

    with conn.cursor() as cur:
        cur.execute(sql, game_ids)
        rows = cur.fetchall()

    if len(rows) != len(game_ids):
        return 1, [
            f"diff join: expected {len(game_ids)} rows, got {len(rows)} "
            f"(snapshot or python replay missing games?)"
        ]

    for row in rows:
        gid = int(row["game_id"])
        for col in RATEDRESULTS_DERIVED:
            php_val = row[f"php_{col}"]
            py_val = row[f"py_{col}"]
            if _values_equal(col, php_val, py_val):
                continue
            mismatches += 1
            if len(lines) < max_report:
                lines.append(
                    f"game_id={gid} {col}: php={php_val!r} python={py_val!r} "
                    f"(tol={FLOAT_TOLERANCE} for floats)"
                )
            break

    return mismatches, lines


def _drop_snapshot(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
    if not dry_run:
        conn.commit()


def run_ab_post_game(opts: AbPostGameOptions) -> int:
    assert_mutate_work_target(opts.target)
    repo = REPO_ROOT

    if opts.limit is None and opts.until_game_id is None:
        raise SystemExit("Specify --limit N and/or --until-game-id G for the checkpoint.")

    for layer_id in opts.layers:
        spec = LAYER_REGISTRY[layer_id]
        if not spec.shipped and layer_id != 0:
            log.warning(
                "Layer %s (%s) not shipped in PHP yet — will be skipped",
                layer_id,
                spec.name,
            )

    # --- prepare ---
    if opts.prepare_mode == "full":
        log.info("=== prepare: full (refresh → migrate → seed → zero derived) ===")
        if not opts.dry_run:
            prepare_full(opts.target, dry_run=False)
            rc = print_parity_report(run_parity_checks(opts.target))
            if rc != 0:
                return rc
    elif opts.prepare_mode == "zero-only":
        log.info("=== prepare: zero-derived only ===")
        zero_derived(opts.target, dry_run=opts.dry_run)
        if opts.run_ground_parity and not opts.dry_run:
            rc = print_parity_report(run_parity_checks(opts.target))
            if rc != 0:
                log.error("Ground parity failed after zero-derived — aborting A/B")
                return rc
    elif opts.prepare_mode == "skip":
        log.info("=== prepare: skipped (--skip-prepare) ===")
    else:
        raise SystemExit(f"Unknown prepare_mode {opts.prepare_mode!r}")

    # game ids for checkpoint (after zero, before php)
    conn = connect(opts.target)
    try:
        game_ids = list_game_ids_chronological(
            conn,
            limit=opts.limit,
            until_game_id=opts.until_game_id,
        )
        if not game_ids:
            raise SystemExit("No games in checkpoint.")
        game_count = len(game_ids)
        log.info(
            "checkpoint: %s games (first id=%s, last id=%s)",
            game_count,
            game_ids[0],
            game_ids[-1],
        )
    finally:
        conn.close()

    # --- PHP sim ---
    _run_php_replay(repo, opts, game_count)

    if opts.run_sanity and 1 in opts.layers:
        _run_sanity_check(repo, opts.target.profile, game_count, dry_run=opts.dry_run)

    if opts.dry_run:
        log.info("dry-run: stopping before snapshot / python oracle")
        return 0

    player_ids: list[int] = []
    if 2 in opts.layers:
        conn_ids = connect(opts.target)
        try:
            player_ids = checkpoint_player_ids(conn_ids, game_ids)
            log.info("checkpoint players: %s with >=1 game in slice", len(player_ids))
        finally:
            conn_ids.close()

    conn = connect(opts.target)
    try:
        if 1 in opts.layers:
            _create_snapshot(conn, game_ids, dry_run=False)
        if 2 in opts.layers and player_ids:
            create_playertable_snapshot(conn, player_ids, dry_run=False)
        if 3 in opts.layers:
            create_generalstats_snapshot(conn, dry_run=False)
        if 4 in opts.layers:
            create_period_activity_snapshots(conn, dry_run=False)
        if 5 in opts.layers:
            create_period_aggregate_snapshots(conn, dry_run=False)
        if 6 in opts.layers:
            create_milestones_snapshot(conn, dry_run=False)

        # Python run resets DB then replays (oracle)
        _run_python_oracle(repo, opts.target.profile, limit=game_count, dry_run=False)

        exit_code = 0
        if 1 in opts.layers:
            n_bad, lines = _diff_ratedresults_layer(conn, game_ids)
            if n_bad == 0:
                log.info(
                    "[OK] layer 1 ratedresults_derived: %s games, 0 mismatches (tol=%s)",
                    game_count,
                    FLOAT_TOLERANCE,
                )
            else:
                exit_code = 1
                log.error(
                    "[FAIL] layer 1 ratedresults_derived: %s mismatch(es) in %s games",
                    n_bad,
                    game_count,
                )
                for line in lines:
                    log.error("  %s", line)
                if n_bad > len(lines):
                    log.error("  ... and more (showing first %s)", len(lines))

        if 2 in opts.layers and player_ids:
            n_bad, lines = diff_playertable_layer(conn, player_ids)
            if n_bad == 0:
                log.info(
                    "[OK] layer 2 playertable_career: %s players, 0 mismatches (tol=%s)",
                    len(player_ids),
                    FLOAT_TOLERANCE,
                )
            else:
                exit_code = 1
                log.error(
                    "[FAIL] layer 2 playertable_career: %s mismatch(es) in %s players",
                    n_bad,
                    len(player_ids),
                )
                for line in lines:
                    log.error("  %s", line)

        if 3 in opts.layers:
            n_bad, lines = diff_generalstats_layer(conn)
            if n_bad == 0:
                log.info("[OK] layer 3 generalstatstable: 0 mismatches (tol=%s)", FLOAT_TOLERANCE)
            else:
                exit_code = 1
                log.error("[FAIL] layer 3 generalstatstable: %s mismatch(es)", n_bad)
                for line in lines:
                    log.error("  %s", line)

        if 4 in opts.layers:
            n_bad, lines = diff_period_activity_layers(conn)
            if n_bad == 0:
                log.info("[OK] layer 4 period_activity: 0 mismatches")
            else:
                exit_code = 1
                log.error("[FAIL] layer 4 period_activity: %s mismatch(es)", n_bad)
                for line in lines:
                    log.error("  %s", line)

        if 5 in opts.layers:
            n_bad, lines = diff_period_aggregate_layers(conn)
            if n_bad == 0:
                log.info("[OK] layer 5 period_aggregates: 0 mismatches")
            else:
                exit_code = 1
                log.error("[FAIL] layer 5 period_aggregates: %s mismatch(es)", n_bad)
                for line in lines:
                    log.error("  %s", line)

        if 6 in opts.layers:
            log.info(
                "layer 6 diff excludes keys not in ProcessCompletedGame: %s",
                ", ".join(sorted(P6_PARITY_EXCLUDE_KEYS)),
            )
            n_bad, lines = diff_milestones_layer(conn)
            if n_bad == 0:
                log.info("[OK] layer 6 player_milestones: 0 mismatches")
            else:
                exit_code = 1
                log.error("[FAIL] layer 6 player_milestones: %s mismatch(es)", n_bad)
                for line in lines:
                    log.error("  %s", line)

        for layer_id in opts.layers:
            if layer_id in (0, 1, 2, 3, 4, 5, 6):
                continue
            spec = LAYER_REGISTRY[layer_id]
            if not spec.shipped:
                log.info("[SKIP] layer %s %s (not shipped)", layer_id, spec.name)

        if not opts.keep_snapshot:
            _drop_snapshot(conn, dry_run=False)
            if 2 in opts.layers:
                drop_playertable_snapshot(conn, dry_run=False)
            if 3 in opts.layers:
                drop_generalstats_snapshot(conn, dry_run=False)
            if 4 in opts.layers:
                drop_period_activity_snapshots(conn, dry_run=False)
            if 5 in opts.layers:
                drop_period_aggregate_snapshots(conn, dry_run=False)
            if 6 in opts.layers:
                drop_milestones_snapshot(conn, dry_run=False)
        else:
            log.info("keeping snapshot tables %s", SNAPSHOT_TABLE)
            if 2 in opts.layers:
                log.info("keeping snapshot table %s", PLAYERTABLE_SNAPSHOT_TABLE)
            if 3 in opts.layers:
                log.info("keeping snapshot table %s", GENERALSTATS_SNAPSHOT_TABLE)

        return exit_code
    finally:
        conn.close()


def build_options_from_args(args: argparse.Namespace) -> AbPostGameOptions:
    from .targets import load_target

    if args.full_prepare and args.skip_prepare:
        raise SystemExit("Use either --full-prepare or --skip-prepare, not both.")
    if args.full_prepare:
        prepare_mode = "full"
    elif args.skip_prepare:
        prepare_mode = "skip"
    else:
        prepare_mode = "zero-only"

    layers = parse_layers_arg(args.phase, args.layers)

    return AbPostGameOptions(
        target=load_target(args.target),
        limit=args.limit,
        until_game_id=args.until_game_id,
        layers=layers,
        prepare_mode=prepare_mode,
        run_ground_parity=not args.skip_ground_parity,
        run_sanity=not args.skip_sanity,
        keep_snapshot=args.keep_snapshot,
        dry_run=args.dry_run,
        php_bin=args.php_bin,
    )


def register_ab_post_game_parser(
    subparsers: Any,
    *,
    parents: list[argparse.ArgumentParser] | None = None,
) -> argparse.ArgumentParser:
    p = subparsers.add_parser(
        "ab-post-game",
        parents=parents or [],
        help="Mode A parity: zero-derived → PHP replay → Python oracle → diff (§9)",
    )
    p.add_argument("--limit", type=int, default=100)
    p.add_argument("--until-game-id", type=int, default=None)
    p.add_argument(
        "--phase",
        default="auto",
        help="Layer set alias: auto, p1, p2, … (default auto = shipped layers)",
    )
    p.add_argument(
        "--layers",
        default=None,
        help="Comma-separated layer ids (overrides --phase), e.g. 1 or 1,2",
    )
    p.add_argument(
        "--full-prepare",
        action="store_true",
        help="Full prepare instead of zero-derived only",
    )
    p.add_argument(
        "--skip-prepare",
        action="store_true",
        help="Assume DB already at day-zero (dangerous if dirty)",
    )
    p.add_argument(
        "--skip-ground-parity",
        action="store_true",
        help="Skip run_prepare parity checks after zero-derived",
    )
    p.add_argument("--skip-sanity", action="store_true", help="Skip verify_ratedresults_derived_rows.py")
    p.add_argument(
        "--keep-snapshot",
        action="store_true",
        help=f"Keep work table `{SNAPSHOT_TABLE}` after run",
    )
    p.add_argument("--php-bin", default=None, help="PHP executable (default: PATH or K2_PHP_BIN)")
    return p


def main_ab_post_game(args: argparse.Namespace) -> int:
    opts = build_options_from_args(args)
    return run_ab_post_game(opts)
