"""Refresh work — clone baseline → work (MySQL dump pipe)."""

from __future__ import annotations

import logging
import subprocess

from .db import database_exists
from .guards import assert_refresh_target
from .paths import find_mysql_exe, find_mysqldump_exe
from .targets import WorkTarget

log = logging.getLogger(__name__)


def refresh_work(target: WorkTarget, *, dry_run: bool = False) -> None:
    assert_refresh_target(target)
    work = target.work_database
    baseline = target.baseline_database

    if not database_exists(target, baseline):
        raise SystemExit(
            f"Baseline database {baseline!r} missing. Run scripts/setup_local_prod_sandbox.ps1 first."
        )

    mysql = find_mysql_exe()
    mysqldump = find_mysqldump_exe()

    log.info(
        "refresh_work profile=%s clone %s -> %s dry_run=%s",
        target.profile,
        baseline,
        work,
        dry_run,
    )
    if dry_run:
        return

    drop_sql = f"DROP DATABASE IF EXISTS `{work}`; CREATE DATABASE `{work}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
    subprocess.run([str(mysql), "-u", target.user, "-e", drop_sql], check=True)

    dump_cmd = [str(mysqldump), "-u", target.user]
    load_cmd = [str(mysql), "-u", target.user, work]
    if target.password:
        dump_cmd.append(f"-p{target.password}")
        load_cmd.insert(2, f"-p{target.password}")
    dump_cmd += [
        "--single-transaction",
        "--no-create-db",
        "--routines",
        "--events",
        baseline,
    ]

    proc_dump = subprocess.Popen(dump_cmd, stdout=subprocess.PIPE)
    try:
        subprocess.run(load_cmd, stdin=proc_dump.stdout, check=True)
    finally:
        if proc_dump.stdout:
            proc_dump.stdout.close()
        proc_dump.wait()
        if proc_dump.returncode not in (0, None):
            raise SystemExit(f"mysqldump failed (exit {proc_dump.returncode})")

    verify = subprocess.run(
        [
            str(mysql),
            "-u",
            target.user,
            "-N",
            "-e",
            f"SELECT COUNT(*) FROM `{work}`.ratedresults;",
        ],
        capture_output=True,
        text=True,
        check=True,
    )
    games = verify.stdout.strip()
    log.info("[OK] %s ready — ratedresults rows: %s", work, games)
