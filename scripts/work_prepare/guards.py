"""Safety guards for prepare verbs."""

from __future__ import annotations

from .constants import PROTECTED_BASELINE_DATABASES, PROTECTED_DEV_DATABASE
from .targets import WorkTarget


def assert_refresh_target(target: WorkTarget) -> None:
    if target.work_database == PROTECTED_DEV_DATABASE:
        raise SystemExit(f"Refusing refresh: work database must not be {PROTECTED_DEV_DATABASE!r}.")
    if target.baseline_database == PROTECTED_DEV_DATABASE:
        raise SystemExit(f"Refusing refresh: baseline must not be {PROTECTED_DEV_DATABASE!r}.")
    if target.work_database in PROTECTED_BASELINE_DATABASES:
        raise SystemExit(f"Refusing refresh: cannot replace protected baseline DB {target.work_database!r}.")
    if target.work_database == target.baseline_database:
        raise SystemExit("Refusing refresh: work and baseline database names must differ.")


def assert_mutate_work_target(target: WorkTarget) -> None:
    """Migrate and zero-derived only touch work DB."""
    if target.work_database == PROTECTED_DEV_DATABASE:
        raise SystemExit(f"Refusing: work database must not be {PROTECTED_DEV_DATABASE!r}.")
    if target.work_database in PROTECTED_BASELINE_DATABASES:
        raise SystemExit(
            f"Refusing: cannot migrate or zero derived on protected baseline {target.work_database!r}."
        )
