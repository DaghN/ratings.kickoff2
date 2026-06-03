"""Migrate work — apply ops/sql/migrations via apply_local.ps1 (legacy) or use ops run_prepare migrate-work."""

from __future__ import annotations

import logging
import subprocess

from .guards import assert_mutate_work_target
from .paths import REPO_ROOT
from .targets import WorkTarget

log = logging.getLogger(__name__)


def migrate_work(target: WorkTarget, *, dry_run: bool = False) -> None:
    assert_mutate_work_target(target)
    work = target.work_database
    log.info("migrate_work profile=%s database=%s dry_run=%s", target.profile, work, dry_run)
    if dry_run:
        return

    script = REPO_ROOT / "schema" / "apply_local.ps1"
    if not script.is_file():
        raise SystemExit(f"Missing {script}")

    cmd = [
        "powershell",
        "-ExecutionPolicy",
        "Bypass",
        "-File",
        str(script),
        "-Database",
        work,
    ]
    log.info("Running: %s", " ".join(cmd))
    subprocess.run(cmd, check=True, cwd=str(REPO_ROOT))
    log.info("[OK] migrations applied to %s", work)
