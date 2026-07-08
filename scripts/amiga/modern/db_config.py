"""Work DB config — ko2amiga_work credentials."""

from __future__ import annotations

import os

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.modern.constants import ORACLE_DB, WORK_DB
from scripts.k2_rating_core.config import DbConfig


def activate_work_database_env() -> None:
    os.environ["KO2AMIGA_DATABASE"] = WORK_DB


def clear_work_database_env() -> None:
    os.environ.pop("KO2AMIGA_DATABASE", None)


def load_work_db_config() -> DbConfig:
    activate_work_database_env()
    cfg = load_amiga_db_config()
    if cfg.database != WORK_DB:
        raise SystemExit(f"Refusing work config: expected {WORK_DB!r}, got {cfg.database!r}")
    return cfg


def load_oracle_db_config() -> DbConfig:
    clear_work_database_env()
    cfg = load_amiga_db_config()
    from dataclasses import replace

    return replace(cfg, database=ORACLE_DB)