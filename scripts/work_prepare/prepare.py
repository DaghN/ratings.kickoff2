"""Orchestrate full or fast prepare."""

from __future__ import annotations

import logging

from .migrate import migrate_work
from .refresh import refresh_work
from .seed_catalog import seed_milestone_definitions
from .seed_lobby import seed_lobby_milestones
from .targets import WorkTarget
from .zero_derived import zero_derived

log = logging.getLogger(__name__)


def prepare_full(target: WorkTarget, *, dry_run: bool = False) -> None:
    log.info(
        "=== prepare full (refresh → migrate → seed catalog → zero derived → seed lobby) profile=%s ===",
        target.profile,
    )
    refresh_work(target, dry_run=dry_run)
    migrate_work(target, dry_run=dry_run)
    seed_milestone_definitions(target, dry_run=dry_run)
    zero_derived(target, dry_run=dry_run)


def prepare_fast(target: WorkTarget, *, dry_run: bool = False) -> None:
    log.info("=== prepare fast (zero derived → seed lobby) profile=%s ===", target.profile)
    zero_derived(target, dry_run=dry_run)
