"""Seed milestone_definitions catalog from data/milestones_definitions_seed.json."""

from __future__ import annotations

import json
import logging
from pathlib import Path

from .db import connect
from .guards import assert_mutate_work_target
from .paths import REPO_ROOT
from .targets import WorkTarget

log = logging.getLogger(__name__)

SEED_PATH = REPO_ROOT / "data" / "milestones_definitions_seed.json"

TIER_BAND_PRODUCT = {
    "aspirational": "aspirational",
    "dedicated": "veteran",
    "accomplished": "key",
    "legendary": "legendary",
}


def seed_milestone_definitions(target: WorkTarget, *, dry_run: bool = False) -> None:
    assert_mutate_work_target(target)
    if not SEED_PATH.is_file():
        raise SystemExit(f"Missing seed file: {SEED_PATH}")

    payload = json.loads(SEED_PATH.read_text(encoding="utf-8"))
    rows = payload["definitions"]
    expected = int(payload.get("milestone_count", len(rows)))

    log.info(
        "seed_milestone_definitions profile=%s rows=%s dry_run=%s",
        target.profile,
        len(rows),
        dry_run,
    )
    if dry_run:
        return

    conn = connect(target)
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.tables "
                "WHERE table_schema = DATABASE() AND table_name = 'milestone_definitions'"
            )
            if int(cur.fetchone()["n"]) == 0:
                raise SystemExit(
                    "milestone_definitions table missing — run migrate-work before seed-catalog."
                )

            cur.execute("TRUNCATE TABLE milestone_definitions")
            sql = """
                INSERT INTO milestone_definitions (
                    milestone_key, display_name, tier_band, chart_token,
                    rule_short, description, sort_order, icon
                ) VALUES (%s, %s, %s, %s, %s, NULL, %s, NULL)
            """
            for i, row in enumerate(rows, start=1):
                seed_tier = str(row["tier_band"])
                tier = TIER_BAND_PRODUCT.get(seed_tier)
                if tier is None:
                    raise ValueError(f"Unknown tier_band in seed: {seed_tier!r}")
                cur.execute(
                    sql,
                    (
                        row["milestone_key"],
                        row["display_name"],
                        tier,
                        row["chart_token"],
                        row["rule_short"],
                        i,
                    ),
                )
            cur.execute("SELECT COUNT(*) AS n FROM milestone_definitions")
            n = int(cur.fetchone()["n"])
        conn.commit()
    finally:
        conn.close()

    if n != len(rows):
        raise SystemExit(f"milestone_definitions: expected {len(rows)} rows, got {n}")
    if n != expected:
        log.warning("Seed milestone_count=%s but loaded %s rows", expected, n)
    log.info("[OK] milestone_definitions seeded: %s rows", n)
