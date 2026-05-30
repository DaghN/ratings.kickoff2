#!/usr/bin/env python3
"""
Apply milestone catalog copy patches (display_name / rule_short).

Updates data/milestones_definitions_seed.json and milestone_definitions in DB.

Usage (repo root):
  python scripts/oneoff/apply_milestone_catalog_copy_patch.py
  python scripts/oneoff/apply_milestone_catalog_copy_patch.py --seed-only
  python scripts/oneoff/apply_milestone_catalog_copy_patch.py --db-only
"""
from __future__ import annotations

import argparse
import json
import logging
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402

log = logging.getLogger("apply_milestone_catalog_copy_patch")
SEED = _REPO / "data" / "milestones_definitions_seed.json"
PATCHES = _REPO / "data" / "milestone_catalog_copy_patches.json"


def load_patches() -> list[dict]:
    payload = json.loads(PATCHES.read_text(encoding="utf-8"))
    patches = payload.get("patches")
    if not isinstance(patches, list) or not patches:
        raise SystemExit(f"No patches in {PATCHES.name}")
    return patches


def apply_to_seed(patches: list[dict]) -> int:
    payload = json.loads(SEED.read_text(encoding="utf-8"))
    by_key = {d["milestone_key"]: d for d in payload["definitions"]}
    n = 0
    for patch in patches:
        key = patch["milestone_key"]
        if key not in by_key:
            raise SystemExit(f"Unknown milestone_key in patch: {key}")
        row = by_key[key]
        if "display_name" in patch:
            row["display_name"] = patch["display_name"]
            n += 1
        if "rule_short" in patch:
            row["rule_short"] = patch["rule_short"]
            n += 1
    SEED.write_text(
        json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    log.info("Updated seed (%d field writes): %s", n, SEED.name)
    return n


def apply_to_db(patches: list[dict]) -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    for patch in patches:
        key = patch["milestone_key"]
        sets: list[str] = []
        params: list[str] = []
        if "display_name" in patch:
            sets.append("display_name = %s")
            params.append(patch["display_name"])
        if "rule_short" in patch:
            sets.append("rule_short = %s")
            params.append(patch["rule_short"])
        if not sets:
            continue
        params.append(key)
        sql = f"UPDATE milestone_definitions SET {', '.join(sets)} WHERE milestone_key = %s"
        cur.execute(sql, params)
        if cur.rowcount not in (0, 1):
            raise SystemExit(f"Unexpected rowcount for {key}: {cur.rowcount}")
        log.info("DB %s", key)
    con.commit()
    cur.close()
    con.close()


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--seed-only", action="store_true")
    parser.add_argument("--db-only", action="store_true")
    args = parser.parse_args()
    if args.seed_only and args.db_only:
        raise SystemExit("Use at most one of --seed-only, --db-only")

    patches = load_patches()
    log.info("Loaded %d patch rows from %s", len(patches), PATCHES.name)

    if not args.db_only:
        apply_to_seed(patches)
    if not args.seed_only:
        apply_to_db(patches)

    log.info("Done.")


if __name__ == "__main__":
    main()
