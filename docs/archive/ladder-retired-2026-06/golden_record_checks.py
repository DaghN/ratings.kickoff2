"""Golden record checks for generalstatstable Hall of Fame values.

Read-only diagnostics: compares stored generalstatstable record values against
expected values derived from the tie-policy contract.

Usage: python -m scripts.ladder.golden_record_checks
"""

from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config
from scripts.ladder.engine import connect


GOLDEN_RECORDS = [
    {
        "label": "LongestDrawingStreak: first holder keeps on tie",
        "column": "LongestDrawingStreak",
        "expected_value": 5,
        "expected_holder": "j1mpst3r",
        "column_id": "LongestDrawingStreakID",
        "expected_id": 376,
        "note": "Later 5-draw streaks (LORENZOL Aug 2021, Oscar Apr 2022) must NOT supersede first holder.",
    },
    {
        "label": "MostCleanSheetsVictims: first reach date preserved",
        "column": "MostCleanSheetsVictims",
        "expected_value": 76,
        "expected_holder": "FieryPhoenix",
        "column_id": "MostCleanSheetsVictimsID",
        "expected_id": 433,
        "note": "Fiery reached 76 on Jan 30 UTC. Later games must not update the date.",
    },
    {
        "label": "BiggestPeakRating: first reach date, not later repeats",
        "column": "BiggestPeakRating",
        "expected_holder": "Dagh",
        "column_id": "BiggestPeakRatingID",
        "expected_id": 291,
        "note": "Peak first reached May 14 UTC. Later games at same peak must not update date.",
    },
    {
        "label": "LongestWinningStreak: unique holder, no tie conflict",
        "column": "LongestWinningStreak",
        "expected_value": 70,
        "expected_holder": "GianniT",
        "column_id": "LongestWinningStreakID",
        "expected_id": 256,
        "note": "No other player reached 70; straightforward record.",
    },
    {
        "label": "LongestNonLossStreak: unique holder, no tie conflict",
        "column": "LongestNonLossStreak",
        "expected_value": 120,
        "expected_holder": "GianniT",
        "column_id": "LongestNonLossStreakID",
        "expected_id": 256,
        "note": "No other player reached 120; straightforward record.",
    },
]

DATE_CHECKS = [
    {
        "label": "LongestWinningStreak date = first reach (Gianni 2020), not last game",
        "column": "LongestWinningStreakDate",
        "expected_contains": "2020-11-23",
        "bad_contains": "2023-12-26",
        "note": "Staging/prod bug: date drifted to Gianni last game Dec 2023 when career max compared with >=.",
    },
    {
        "label": "LongestNonLossStreak date = first reach (Gianni 2022), not last game",
        "column": "LongestNonLossStreakDate",
        "expected_contains": "2022-02-16",
        "bad_contains": "2023-12-26",
        "note": "Same class of bug as LWS; see PG-004c in PG-004 snippet.",
    },
    {
        "label": "LongestDrawingStreak date must be UTC (not Estonia +3)",
        "column": "LongestDrawingStreakDate",
        "expected_contains": "2020-06-13",
        "bad_contains": "2020-06-14",
        "note": "Game was 2020-06-13 23:25:53 UTC. Estonia local would show Jun 14.",
    },
    {
        "label": "MostCleanSheetsVictims date must be Jan 30 UTC",
        "column": "MostCleanSheetsVictimsDate",
        "expected_contains": "2026-01-30",
        "bad_contains": "2026-03-13",
        "note": "Staging bug showed Mar 13 (Fiery's last game). Correct is Jan 30.",
    },
    {
        "label": "BiggestPeakRating date must be first reach (May 14)",
        "column": "BiggestPeakRatingDate",
        "expected_contains": "2026-05-14",
        "bad_contains": "2026-05-18",
        "note": "Peak repeated 40 times after first reach; date must not drift to last game.",
    },
    {
        "label": "MostDifferentOpponents date = first reach 103 (Eternalstudent), not last game",
        "column": "MostDifferentOpponentsDate",
        "expected_contains": "2026-05-04",
        "bad_contains": "2026-05-18",
        "note": "Staging C++ bug: date drifted to holder last game; correct is first UTC day count hit 103.",
    },
    {
        "label": "MostDifferentVictims date = first reach 101 (Eternalstudent), not last game",
        "column": "MostDifferentVictimsDate",
        "expected_contains": "2026-05-04",
        "bad_contains": "2026-05-18",
        "note": "Same defect class as MostDifferentOpponents; see staging-post-game-record-defects.md.",
    },
]


def run_checks() -> int:
    cfg = load_db_config(None)
    conn = connect(cfg, dry_run=True, target=None)
    failures = 0

    with conn.cursor() as cur:
        cur.execute("SELECT * FROM generalstatstable WHERE id = 1")
        row = cur.fetchone()

    if not row:
        print("FAIL: generalstatstable has no id=1 row")
        conn.close()
        return 1

    print("=== Golden Record Checks ===\n")

    for check in GOLDEN_RECORDS:
        col = check["column"]
        stored_value = row.get(col)
        stored_id = row.get(check["column_id"])
        stored_name = row.get(col.replace("ID", "Name") if "ID" not in col else check["column_id"].replace("ID", "Name"))
        name_col = check["column"] + "Name"
        stored_name = row.get(name_col, "?")

        ok = True
        reasons = []

        if "expected_value" in check and stored_value != check["expected_value"]:
            ok = False
            reasons.append(f"value={stored_value}, expected={check['expected_value']}")

        if stored_id != check["expected_id"]:
            ok = False
            reasons.append(f"holder_id={stored_id}, expected={check['expected_id']}")

        if stored_name != check["expected_holder"]:
            ok = False
            reasons.append(f"holder_name={stored_name!r}, expected={check['expected_holder']!r}")

        status = "PASS" if ok else "FAIL"
        if not ok:
            failures += 1
        print(f"[{status}] {check['label']}")
        if reasons:
            print(f"       {'; '.join(reasons)}")
        print(f"       note: {check['note']}")
        print()

    for check in DATE_CHECKS:
        col = check["column"]
        stored = str(row.get(col, ""))
        ok = check["expected_contains"] in stored
        bad = check.get("bad_contains") and check["bad_contains"] in stored

        if bad:
            status = "FAIL"
            failures += 1
            print(f"[FAIL] {check['label']}")
            print(f"       stored={stored!r} contains BAD value {check['bad_contains']!r}")
        elif ok:
            print(f"[PASS] {check['label']}")
            print(f"       stored={stored!r}")
        else:
            status = "WARN"
            print(f"[WARN] {check['label']}")
            print(f"       stored={stored!r}, expected to contain {check['expected_contains']!r}")

        print(f"       note: {check['note']}")
        print()

    print(f"--- {len(GOLDEN_RECORDS) + len(DATE_CHECKS)} checks, {failures} failures ---")
    conn.close()
    return 1 if failures > 0 else 0


if __name__ == "__main__":
    sys.exit(run_checks())
