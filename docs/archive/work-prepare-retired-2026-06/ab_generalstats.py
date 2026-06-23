"""generalstatstable layer-3 parity (P3) for ab-post-game."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from .ab_layers import FLOAT_TOLERANCE

log = logging.getLogger(__name__)

SNAPSHOT_TABLE = "parity_ab_generalstats_php"

# Server-wide aggregates + PG-004 holder columns (no ratio leaders on GST).
GENERALSTATS_PARITY_COLUMNS: tuple[str, ...] = (
    "NumberOfPlayers",
    "DifferentOpponentsAverage",
    "GamesPlayed",
    "GamesPlayedAverage",
    "NumberOfDecidedGames",
    "NumberOfDraws",
    "DecidedGamesRatio",
    "DrawsRatio",
    "GoalsScored",
    "GoalsPerGameAverage",
    "DoubleDigits",
    "CleanSheets",
    "DoubleDigitsRatio",
    "CleanSheetsRatio",
    "MostGamesPlayed",
    "MostGamesPlayedID",
    "MostGamesPlayedName",
    "MostGamesPlayedDate",
    "MostWins",
    "MostWinsID",
    "MostWinsName",
    "MostWinsDate",
    "MostGoalsScored",
    "MostGoalsScoredID",
    "MostGoalsScoredName",
    "MostGoalsScoredDate",
    "MostGoalsScoredInOneGame",
    "MostGoalsScoredInOneGameID",
    "MostGoalsScoredInOneGameName",
    "MostGoalsScoredInOneGameDate",
    "MostGoalsScoredInOneGameGameID",
    "BiggestWinDifference",
    "BiggestWinDifferenceID",
    "BiggestWinDifferenceName",
    "BiggestWinDifferenceDate",
    "BiggestWinDifferenceGameID",
    "BiggestDrawSum",
    "BiggestDrawSumIDA",
    "BiggestDrawSumIDB",
    "BiggestDrawSumNameA",
    "BiggestDrawSumNameB",
    "BiggestDrawSumDate",
    "BiggestDrawSumGameID",
    "BiggestSumOfGoals",
    "BiggestSumOfGoalsIDA",
    "BiggestSumOfGoalsIDB",
    "BiggestSumOfGoalsNameA",
    "BiggestSumOfGoalsNameB",
    "BiggestSumOfGoalsDate",
    "BiggestSumOfGoalsGameID",
    "MostDoubleDigits",
    "MostDoubleDigitsID",
    "MostDoubleDigitsName",
    "MostDoubleDigitsDate",
    "MostCleanSheets",
    "MostCleanSheetsID",
    "MostCleanSheetsName",
    "MostCleanSheetsDate",
    "MostDifferentOpponents",
    "MostDifferentOpponentsID",
    "MostDifferentOpponentsName",
    "MostDifferentOpponentsDate",
    "MostDifferentVictims",
    "MostDifferentVictimsID",
    "MostDifferentVictimsName",
    "MostDifferentVictimsDate",
    "MostDoubleDigitsVictims",
    "MostDoubleDigitsVictimsID",
    "MostDoubleDigitsVictimsName",
    "MostDoubleDigitsVictimsDate",
    "MostCleanSheetsVictims",
    "MostCleanSheetsVictimsID",
    "MostCleanSheetsVictimsName",
    "MostCleanSheetsVictimsDate",
    "BiggestRatingAscent",
    "BiggestRatingAscentID",
    "BiggestRatingAscentName",
    "BiggestRatingAscentDate",
    "BiggestPeakRating",
    "BiggestPeakRatingID",
    "BiggestPeakRatingName",
    "BiggestPeakRatingDate",
    "LongestWinningStreak",
    "LongestWinningStreakID",
    "LongestWinningStreakName",
    "LongestWinningStreakDate",
    "LongestDrawingStreak",
    "LongestDrawingStreakID",
    "LongestDrawingStreakName",
    "LongestDrawingStreakDate",
    "LongestNonLossStreak",
    "LongestNonLossStreakID",
    "LongestNonLossStreakName",
    "LongestNonLossStreakDate",
)

GENERALSTATS_FLOAT = frozenset(
    {
        "DifferentOpponentsAverage",
        "GamesPlayedAverage",
        "DecidedGamesRatio",
        "DrawsRatio",
        "GoalsPerGameAverage",
        "DoubleDigitsRatio",
        "CleanSheetsRatio",
        "BiggestRatingAscent",
        "BiggestPeakRating",
    }
)

GENERALSTATS_DATE = frozenset(
    {
        "MostGamesPlayedDate",
        "MostWinsDate",
        "MostGoalsScoredDate",
        "MostGoalsScoredInOneGameDate",
        "BiggestWinDifferenceDate",
        "BiggestDrawSumDate",
        "BiggestSumOfGoalsDate",
        "MostDoubleDigitsDate",
        "MostCleanSheetsDate",
        "MostDifferentOpponentsDate",
        "MostDifferentVictimsDate",
        "MostDoubleDigitsVictimsDate",
        "MostCleanSheetsVictimsDate",
        "BiggestRatingAscentDate",
        "BiggestPeakRatingDate",
        "LongestWinningStreakDate",
        "LongestDrawingStreakDate",
        "LongestNonLossStreakDate",
    }
)


def _existing_columns(conn: pymysql.connections.Connection) -> list[str]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generalstatstable'"
        )
        return [r["COLUMN_NAME"] for r in cur.fetchall()]


def create_generalstats_snapshot(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool,
) -> None:
    db_cols = set(_existing_columns(conn))
    cols = [c for c in GENERALSTATS_PARITY_COLUMNS if c in db_cols]
    missing = [c for c in GENERALSTATS_PARITY_COLUMNS if c not in db_cols]
    if missing:
        log.warning("generalstats parity: columns absent on DB (skipped): %s", ", ".join(missing[:5]))

    if not cols:
        raise SystemExit("No generalstatstable parity columns found on work DB.")

    cols_sql = ", ".join(f"`{c}`" for c in cols)
    def _col_def(col: str) -> str:
        if col in GENERALSTATS_FLOAT:
            return f"`{col}` DOUBLE NULL"
        if col in GENERALSTATS_DATE:
            return f"`{col}` DATETIME NULL"
        if "Name" in col:
            return f"`{col}` VARCHAR(32) NULL"
        return f"`{col}` BIGINT NULL"

    col_defs = ", ".join(_col_def(c) for c in cols)
    ddl = f"CREATE TABLE `{SNAPSHOT_TABLE}` (snapshot_id TINYINT NOT NULL PRIMARY KEY, {col_defs})"

    with conn.cursor() as cur:
        log.info("snapshot: DROP TABLE IF EXISTS %s", SNAPSHOT_TABLE)
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
            cur.execute(ddl)
            cur.execute(
                f"INSERT INTO `{SNAPSHOT_TABLE}` (snapshot_id, {cols_sql}) "
                f"SELECT 1, {cols_sql} FROM generalstatstable WHERE id = 1"
            )
    if not dry_run:
        conn.commit()
    log.info("snapshot: generalstatstable id=1 → %s (%s cols)", SNAPSHOT_TABLE, len(cols))


def _values_equal(col: str, php_val: Any, py_val: Any) -> bool:
    if php_val is None and py_val is None:
        return True
    if php_val is None or py_val is None:
        return False
    if col in GENERALSTATS_FLOAT:
        return abs(float(php_val) - float(py_val)) <= FLOAT_TOLERANCE
    if col in GENERALSTATS_DATE:
        return str(php_val)[:19] == str(py_val)[:19]
    if col.endswith("Name") or col.endswith("NameA") or col.endswith("NameB"):
        return str(php_val) == str(py_val)
    return int(php_val) == int(py_val)


def diff_generalstats_layer(
    conn: pymysql.connections.Connection,
    *,
    max_report: int = 15,
) -> tuple[int, list[str]]:
    db_cols = set(_existing_columns(conn))
    cols = [c for c in GENERALSTATS_PARITY_COLUMNS if c in db_cols]
    if not cols:
        return 1, ["no parity columns on generalstatstable"]

    select_py = ", ".join(f"g.`{c}` AS py_{c}" for c in cols)
    select_php = ", ".join(f"s.`{c}` AS php_{c}" for c in cols)
    sql = f"""
        SELECT {select_py}, {select_php}
        FROM generalstatstable g
        INNER JOIN `{SNAPSHOT_TABLE}` s ON s.snapshot_id = 1
        WHERE g.id = 1
    """

    mismatches = 0
    lines: list[str] = []

    with conn.cursor() as cur:
        cur.execute(sql)
        row = cur.fetchone()

    if row is None:
        return 1, ["generalstatstable id=1 missing after python replay"]

    for col in cols:
        php_val = row[f"php_{col}"]
        py_val = row[f"py_{col}"]
        if _values_equal(col, php_val, py_val):
            continue
        mismatches += 1
        if len(lines) < max_report:
            lines.append(f"generalstatstable.{col}: php={php_val!r} python={py_val!r}")

    return mismatches, lines


def drop_generalstats_snapshot(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
    if not dry_run:
        conn.commit()
