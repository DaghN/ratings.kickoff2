#!/usr/bin/env python3
"""v0 surface sanity: PHP helper logic vs direct SQL on player_milestones."""
from __future__ import annotations

import json
import subprocess
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402

PHP = Path(r"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe")
PUBLIC = _REPO / "site" / "public_html"


def sql_counts(cur, player_id: int) -> dict[str, int]:
    cur.execute(
        """
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(md.tier_band = 'aspirational'), 0) AS aspirational,
            COALESCE(SUM(md.tier_band = 'veteran'), 0) AS dedicated,
            COALESCE(SUM(md.tier_band = 'key'), 0) AS accomplished,
            COALESCE(SUM(md.tier_band = 'legendary'), 0) AS legendary
        FROM player_milestones pm
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE pm.player_id = %s
        """,
        (player_id,),
    )
    row = cur.fetchone()
    return {k: int(row[k]) for k in row}


def sql_garden_unlocked(cur, player_id: int) -> int:
    cur.execute(
        "SELECT COUNT(*) AS n FROM player_milestones WHERE player_id = %s",
        (player_id,),
    )
    return int(cur.fetchone()["n"])


def php_cli(cmd: str, *args: str) -> dict | list:
    if not PHP.is_file():
        raise SystemExit(f"PHP not found: {PHP}")
    cli = _REPO / "scripts" / "oneoff" / "milestone_v0_sanity_cli.php"
    out = subprocess.check_output(
        [str(PHP), str(cli), cmd, *args],
        text=True,
        cwd=str(PUBLIC),
    )
    return json.loads(out.strip())


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()

    cur.execute("SELECT COUNT(*) AS n FROM milestone_definitions")
    defs_n = int(cur.fetchone()["n"])
    cur.execute("SELECT COUNT(DISTINCT milestone_key) AS n FROM player_milestones")
    keys_n = int(cur.fetchone()["n"])
    cur.execute(
        "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'dd_merchant_10'"
    )
    dd_sql = int(cur.fetchone()["n"])

    issues: list[str] = []

    print("=== Catalog / table health ===")
    print(f"milestone_definitions rows: {defs_n} (expect 112)")
    print(f"distinct milestone_key in unlocks: {keys_n} (expect <= 112)")
    if defs_n != 112:
        issues.append(f"definitions count {defs_n} != 112")
    if keys_n > defs_n:
        issues.append(f"unlock key types {keys_n} > definitions {defs_n}")
    if keys_n < defs_n - 1:
        issues.append(f"unlock key types {keys_n} < definitions {defs_n} - 1 (too many missing keys)")

    cur.execute(
        "SELECT COUNT(*) AS n FROM player_milestones WHERE source_kind IS NULL"
    )
    null_sk = int(cur.fetchone()["n"])
    print(f"source_kind NULL rows: {null_sk} (expect 0)")
    if null_sk:
        issues.append(f"{null_sk} rows with NULL source_kind")

    print("\n=== DD Merchant achievers (PHP vs SQL) ===")
    try:
        ach = php_cli("dd_count")
        dd_php = int(ach["count"])
        print(f"SQL count: {dd_sql}")
        print(f"PHP k2_milestone_dd_merchant_achievers: {dd_php}")
        if dd_sql != dd_php:
            issues.append(f"dd_merchant_10 SQL={dd_sql} PHP={dd_php}")
    except Exception as e:
        issues.append(f"PHP achievers check failed: {e}")
        print(f"PHP achievers check failed: {e}")

    print("\n=== Profile counts vs garden (sample players) ===")
    cur.execute(
        """
        SELECT ID, Name, NumberGames
        FROM playertable
        WHERE NumberGames >= 1
        ORDER BY NumberGames DESC
        LIMIT 5
        """
    )
    veterans = cur.fetchall()
    cur.execute(
        """
        SELECT ID, Name, NumberGames
        FROM playertable
        WHERE NumberGames >= 1
        ORDER BY NumberGames ASC
        LIMIT 3
        """
    )
    low = cur.fetchall()

    for label, rows in [("veteran", veterans), ("low-games", low)]:
        for r in rows:
            pid = int(r["ID"])
            name = r["Name"]
            sql_c = sql_counts(cur, pid)
            unlocked = sql_garden_unlocked(cur, pid)
            try:
                php_c = php_cli("player_counts", str(pid))
                garden = php_cli("garden_unlocked", str(pid))
            except Exception as e:
                issues.append(f"PHP profile/garden pid={pid}: {e}")
                print(f"  [{label}] {name} (id={pid}): PHP error {e}")
                continue

            ok = (
                sql_c["total"] == unlocked == php_c["total"] == garden["unlocked"]
                and sql_c == {
                    "total": php_c["total"],
                    "aspirational": php_c["aspirational"],
                    "dedicated": php_c["dedicated"],
                    "accomplished": php_c["accomplished"],
                    "legendary": php_c["legendary"],
                }
            )
            mark = "OK" if ok else "MISMATCH"
            print(
                f"  [{label}] {name} (id={pid}): total={sql_c['total']} "
                f"tiers A/D/Ac/L={sql_c['aspirational']}/{sql_c['dedicated']}/"
                f"{sql_c['accomplished']}/{sql_c['legendary']} [{mark}]"
            )
            if not ok:
                issues.append(
                    f"pid={pid} sql={sql_c} php={php_c} garden_unlocked={garden['unlocked']}"
                )

    print("\n=== Meta-leaderboard top 5 (PHP vs SQL) ===")
    cur.execute(
        """
        SELECT
            p.ID AS player_id,
            p.Name AS player_name,
            COUNT(pm.milestone_key) AS total,
            COALESCE(SUM(md.tier_band = 'aspirational'), 0) AS aspirational,
            COALESCE(SUM(md.tier_band = 'veteran'), 0) AS dedicated,
            COALESCE(SUM(md.tier_band = 'key'), 0) AS accomplished,
            COALESCE(SUM(md.tier_band = 'legendary'), 0) AS legendary
        FROM playertable p
        INNER JOIN player_milestones pm ON pm.player_id = p.ID
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE p.NumberGames >= 1
        GROUP BY p.ID, p.Name
        ORDER BY total DESC, aspirational DESC, dedicated DESC,
                 accomplished DESC, legendary DESC, p.Rating DESC
        LIMIT 5
        """
    )
    sql_top = cur.fetchall()
    try:
        php_top = php_cli("meta_top5")
    except Exception as e:
        issues.append(f"PHP meta leaderboard: {e}")
        php_top = []

    for i, sql_row in enumerate(sql_top):
        pid = int(sql_row["player_id"])
        if i >= len(php_top):
            issues.append(f"meta LB row {i} missing in PHP")
            continue
        pr = php_top[i]
        match = (
            int(pr["player_id"]) == pid
            and int(pr["total"]) == int(sql_row["total"])
            and int(pr["aspirational"]) == int(sql_row["aspirational"])
            and int(pr["dedicated"]) == int(sql_row["dedicated"])
            and int(pr["accomplished"]) == int(sql_row["accomplished"])
            and int(pr["legendary"]) == int(sql_row["legendary"])
        )
        mark = "OK" if match else "MISMATCH"
        print(
            f"  #{i+1} {sql_row['player_name']} (id={pid}): total={sql_row['total']} "
            f"[{mark}]"
        )
        if not match:
            issues.append(f"meta LB pid={pid} sql={dict(sql_row)} php={pr}")

    print("\n=== Tier sum identity (all players) ===")
    cur.execute(
        """
        SELECT player_id,
               COUNT(*) AS total,
               SUM(md.tier_band = 'aspirational') AS a,
               SUM(md.tier_band = 'veteran') AS d,
               SUM(md.tier_band = 'key') AS ac,
               SUM(md.tier_band = 'legendary') AS l
        FROM player_milestones pm
        JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        GROUP BY player_id
        HAVING total != a + d + ac + l
        LIMIT 5
        """
    )
    bad_sum = cur.fetchall()
    if bad_sum:
        issues.append(f"{len(bad_sum)}+ players with total != sum of tiers")
        print(f"  FAIL: {len(bad_sum)} players (showing up to 5)")
    else:
        print("  OK: total = sum of tier columns for every player")

    con.close()

    print("\n=== Summary ===")
    if issues:
        print(f"FAILED — {len(issues)} issue(s):")
        for x in issues:
            print(f"  - {x}")
        raise SystemExit(1)
    print(f"PASSED — PHP helpers match SQL; catalog {defs_n} definitions; {keys_n} unlock key types; DD count OK.")


if __name__ == "__main__":
    main()
