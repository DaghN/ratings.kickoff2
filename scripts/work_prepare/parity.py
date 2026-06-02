"""Parity checks after prepare (read-only)."""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass

from .db import connect, database_exists
from .paths import REPO_ROOT
from .targets import WorkTarget

log = logging.getLogger(__name__)

SEED_PATH = REPO_ROOT / "data" / "milestones_definitions_seed.json"

REQUIRED_RATEDRESULTS_INDEXES = (
    "idx_ratedresults_idA",
    "idx_ratedresults_idB",
    "idx_ratedresults_date",
)


@dataclass
class ParityResult:
    name: str
    ok: bool
    detail: str


def _index_exists(cur, index_name: str) -> bool:
    cur.execute(
        "SELECT COUNT(*) AS n FROM information_schema.statistics "
        "WHERE table_schema = DATABASE() AND table_name = 'ratedresults' AND index_name = %s",
        (index_name,),
    )
    return int(cur.fetchone()["n"]) > 0


def run_parity_checks(target: WorkTarget) -> list[ParityResult]:
    results: list[ParityResult] = []
    work = target.work_database
    baseline = target.baseline_database

    if not database_exists(target, work):
        return [ParityResult("work_exists", False, f"{work} missing")]

    expected_milestones = 112
    if SEED_PATH.is_file():
        payload = json.loads(SEED_PATH.read_text(encoding="utf-8"))
        expected_milestones = int(payload.get("milestone_count", len(payload.get("definitions", []))))

    conn = connect(target)
    try:
        with conn.cursor() as cur:
            if database_exists(target, baseline):
                cur.execute(f"SELECT COUNT(*) AS n FROM `{baseline}`.ratedresults")
                base_games = int(cur.fetchone()["n"])
                cur.execute("SELECT COUNT(*) AS n FROM ratedresults")
                work_games = int(cur.fetchone()["n"])
                results.append(
                    ParityResult(
                        "ratedresults_count_vs_baseline",
                        work_games == base_games,
                        f"work={work_games} baseline={base_games}",
                    )
                )

                cur.execute(
                    f"""
                    SELECT COUNT(*) AS n
                    FROM `{work}`.ratedresults w
                    INNER JOIN `{baseline}`.ratedresults b ON b.id = w.id
                    WHERE w.idA <> b.idA OR w.idB <> b.idB OR w.Date <> b.Date
                    """
                )
                core_mismatches = int(cur.fetchone()["n"])
                results.append(
                    ParityResult(
                        "ratedresults_core_ids_match_baseline",
                        core_mismatches == 0,
                        f"idA/idB/Date mismatches={core_mismatches} (UTC session)",
                    )
                )

                cur.execute(
                    f"""
                    SELECT COUNT(*) AS n
                    FROM `{work}`.ratedresults w
                    INNER JOIN `{baseline}`.ratedresults b ON b.id = w.id
                    WHERE w.GoalsA <> b.GoalsA OR w.GoalsB <> b.GoalsB
                       OR (w.GoalsA IS NULL) <> (b.GoalsA IS NULL)
                       OR (w.GoalsB IS NULL) <> (b.GoalsB IS NULL)
                    """
                )
                goals_mismatches = int(cur.fetchone()["n"])
                results.append(
                    ParityResult(
                        "ratedresults_goals_match_baseline",
                        goals_mismatches == 0,
                        f"GoalsA/B mismatches={goals_mismatches}",
                    )
                )

                cur.execute(f"SELECT MIN(id) AS mn, MAX(id) AS mx FROM `{baseline}`.ratedresults")
                b_row = cur.fetchone()
                cur.execute("SELECT MIN(id) AS mn, MAX(id) AS mx FROM ratedresults")
                w_row = cur.fetchone()
                id_range_ok = b_row["mn"] == w_row["mn"] and b_row["mx"] == w_row["mx"]
                results.append(
                    ParityResult(
                        "ratedresults_id_range_vs_baseline",
                        id_range_ok,
                        f"work min/max id={w_row['mn']}/{w_row['mx']} baseline={b_row['mn']}/{b_row['mx']}",
                    )
                )

            for idx in REQUIRED_RATEDRESULTS_INDEXES:
                exists = _index_exists(cur, idx)
                results.append(
                    ParityResult(
                        f"index_{idx}",
                        exists,
                        "present" if exists else "MISSING",
                    )
                )

            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS "
                "WHERE table_schema = DATABASE() AND COLUMN_NAME LIKE 'KungFu%'"
            )
            kungfu_cols = int(cur.fetchone()["n"])
            results.append(
                ParityResult(
                    "kungfu_columns_absent",
                    kungfu_cols == 0,
                    f"KungFu% columns remaining={kungfu_cols}",
                )
            )

            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.COLUMNS "
                "WHERE table_schema = DATABASE() AND table_name = 'playertable' "
                "AND column_name = 'RecentAverageRating'"
            )
            recent_avg_col = int(cur.fetchone()["n"])
            results.append(
                ParityResult(
                    "recent_average_rating_column_absent",
                    recent_avg_col == 0,
                    "RecentAverageRating column "
                    + ("absent" if recent_avg_col == 0 else "still present"),
                )
            )

            cur.execute("SELECT COUNT(*) AS n FROM ratedresults WHERE NewRatingA IS NOT NULL")
            derived_rows = int(cur.fetchone()["n"])
            results.append(
                ParityResult(
                    "ratedresults_derived_cleared",
                    derived_rows == 0,
                    f"NewRatingA NOT NULL rows={derived_rows}",
                )
            )

            cur.execute(
                "SELECT COUNT(*) AS n FROM playertable WHERE Rating <> 1600 OR Rating IS NULL"
            )
            non_default_rating = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM playertable")
            players = int(cur.fetchone()["n"])
            results.append(
                ParityResult(
                    "playertable_rating_day_zero",
                    non_default_rating == 0,
                    f"players not at 1600: {non_default_rating} / {players}",
                )
            )

            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.tables "
                "WHERE table_schema = DATABASE() AND table_name = 'milestone_definitions'"
            )
            if int(cur.fetchone()["n"]) == 1:
                cur.execute("SELECT COUNT(*) AS n FROM milestone_definitions")
                md_count = int(cur.fetchone()["n"])
                results.append(
                    ParityResult(
                        "milestone_definitions_seeded",
                        md_count == expected_milestones,
                        f"rows={md_count} expected={expected_milestones}",
                    )
                )
            else:
                results.append(
                    ParityResult(
                        "milestone_definitions_seeded",
                        False,
                        "table missing",
                    )
                )

            for table in (
                "player_milestones",
                "player_period_games",
                "server_daily_activity",
            ):
                cur.execute(
                    "SELECT COUNT(*) AS n FROM information_schema.tables "
                    "WHERE table_schema = DATABASE() AND table_name = %s",
                    (table,),
                )
                if int(cur.fetchone()["n"]) == 0:
                    results.append(
                        ParityResult(f"{table}_empty_or_absent", True, "table missing (pre-migrate OK)")
                    )
                    continue
                cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
                n = int(cur.fetchone()["n"])
                results.append(ParityResult(f"{table}_empty", n == 0, f"rows={n}"))

            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()"
            )
            table_count = int(cur.fetchone()["n"])
            results.append(
                ParityResult(
                    "work_table_count",
                    table_count >= 5,
                    f"tables={table_count}",
                )
            )
    finally:
        conn.close()

    return results


def print_parity_report(results: list[ParityResult]) -> int:
    failed = 0
    for r in results:
        status = "PASS" if r.ok else "FAIL"
        log.info("[%s] %s — %s", status, r.name, r.detail)
        if not r.ok:
            failed += 1
    if failed:
        log.error("Parity: %s check(s) failed", failed)
        return 1
    log.info("Parity: all checks passed")
    return 0
