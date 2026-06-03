#!/usr/bin/env python3
"""
Load milestone_definitions from ops/data/milestones_definitions_seed.json (local/staging).

Usage (repo root):
  python scripts/oneoff/load_milestone_definitions.py
"""
from __future__ import annotations

import json
import logging
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402

log = logging.getLogger("load_milestone_definitions")
SEED = _REPO / "site" / "public_html" / "ops" / "data" / "milestones_definitions_seed.json"

# Seed tier labels (Phase 2) → product tier_band (Phase 3 / milestones-product-spec.md)
TIER_BAND_PRODUCT = {
    "aspirational": "aspirational",
    "dedicated": "veteran",
    "accomplished": "key",
    "legendary": "legendary",
}


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    payload = json.loads(SEED.read_text(encoding="utf-8"))
    rows = payload["definitions"]
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
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
            raise ValueError(f"Unknown tier_band in seed: {seed_tier!r} ({row['milestone_key']})")
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
    con.commit()
    cur.execute("SELECT COUNT(*) AS n FROM milestone_definitions")
    n = int(cur.fetchone()["n"])
    cur.close()
    con.close()
    if n != len(rows):
        raise SystemExit(f"Expected {len(rows)} rows, got {n}")
    log.info("Loaded %d milestone_definitions from %s", n, SEED.name)


if __name__ == "__main__":
    main()
