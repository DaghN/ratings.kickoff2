"""playertable layer-2 parity (P2 career columns) for ab-post-game."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from .ab_layers import FLOAT_TOLERANCE

log = logging.getLogger(__name__)

SNAPSHOT_TABLE = "parity_ab_playertable_php"

# Career columns written by P2 PHP / Python replay (exclude identity / prefs / Display import).
PLAYERTABLE_PARITY_COLUMNS: tuple[str, ...] = (
    "Rating",
    "NumberGames",
    "NumberWins",
    "NumberDraws",
    "NumberLosses",
    "WinRatio",
    "DrawRatio",
    "LossRatio",
    "GoalsFor",
    "GoalsAgainst",
    "AverageGoalsFor",
    "AverageGoalsAgainst",
    "GoalRatio",
    "MostGoalsScored",
    "LeastGoalsScored",
    "MostGoalsConceded",
    "LeastGoalsConceded",
    "BiggestWinDifference",
    "BiggestDrawSum",
    "BiggestLossDifference",
    "SmallestSumOfGoals",
    "BiggestSumOfGoals",
    "DoubleDigits",
    "CleanSheets",
    "DoubleDigitsConceded",
    "CleanSheetsConceded",
    "DoubleDigitsRatio",
    "CleanSheetsRatio",
    "DoubleDigitsConcededRatio",
    "CleanSheetsConcededRatio",
    "DifferentOpponents",
    "DifferentVictims",
    "DoubleDigitsVictims",
    "CleanSheetsVictims",
    "MostGoalsConcededVictims",
    "LeastGoalsScoredVictims",
    "BiggestLossVictims",
    "DifferentCulprits",
    "DoubleDigitsCulprits",
    "CleanSheetsCulprits",
    "MostGoalsScoredCulprits",
    "LeastGoalsConcededCulprits",
    "BiggestWinCulprits",
    "SumOfOpponentsRating",
    "AverageOpponentRating",
    "HighestRatedVictim",
    "LowestRatedCulprit",
    "CurrentRatingAscent",
    "BiggestRatingAscent",
    "CurrentRatingDescent",
    "BiggestRatingDescent",
    "LowestRating",
    "PeakRating",
    "WinningStreak",
    "DrawingStreak",
    "LosingStreak",
    "NonWinStreak",
    "NonDrawStreak",
    "NonLossStreak",
    "LongestWinningStreak",
    "LongestDrawingStreak",
    "LongestLosingStreak",
    "LongestNonWinStreak",
    "LongestNonDrawStreak",
    "LongestNonLossStreak",
    "ScoreStreak",
    "MerchantStreak",
    "ExactTenGoalStreak",
    "WinMarginOneStreak",
    "LossMarginOneStreak",
    "LastGame",
    "LastGameGameID",
    "LastWinGameID",
    "LastDrawGameID",
    "LastLossGameID",
    "LowestRatingGameID",
    "PeakRatingGameID",
    "MostGoalsScoredGameID",
    "LeastGoalsScoredGameID",
    "MostGoalsConcededGameID",
    "LeastGoalsConcededGameID",
    "BiggestWinGameID",
    "BiggestDrawGameID",
    "BiggestLossGameID",
    "SmallestSumOfGoalsGameID",
    "BiggestSumOfGoalsGameID",
    "MostGoalsScoredVictimID",
    "LeastGoalsConcededVictimID",
    "BiggestWinVictimID",
    "MostGoalsConcededCulpritID",
    "LeastGoalsScoredCulpritID",
    "BiggestLossCulpritID",
    "HighestRatedVictimGameID",
    "LowestRatedCulpritGameID",
)

PLAYERTABLE_FLOAT = frozenset(
    {
        "Rating",
        "WinRatio",
        "DrawRatio",
        "LossRatio",
        "AverageGoalsFor",
        "AverageGoalsAgainst",
        "GoalRatio",
        "DoubleDigitsRatio",
        "CleanSheetsRatio",
        "DoubleDigitsConcededRatio",
        "CleanSheetsConcededRatio",
        "SumOfOpponentsRating",
        "AverageOpponentRating",
        "HighestRatedVictim",
        "LowestRatedCulprit",
        "CurrentRatingAscent",
        "BiggestRatingAscent",
        "CurrentRatingDescent",
        "BiggestRatingDescent",
        "LowestRating",
        "PeakRating",
    }
)


def checkpoint_player_ids(
    conn: pymysql.connections.Connection,
    game_ids: list[int],
) -> list[int]:
    if not game_ids:
        return []
    placeholders = ", ".join(["%s"] * len(game_ids))
    sql = (
        f"SELECT DISTINCT pid FROM ("
        f"SELECT idA AS pid FROM ratedresults WHERE id IN ({placeholders}) "
        f"UNION SELECT idB AS pid FROM ratedresults WHERE id IN ({placeholders})"
        f") t ORDER BY pid"
    )
    params = game_ids + game_ids
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return [int(r["pid"]) for r in cur.fetchall()]


def _values_equal(col: str, php_val: Any, py_val: Any) -> bool:
    if php_val is None and py_val is None:
        return True
    if php_val is None or py_val is None:
        return False
    if col in PLAYERTABLE_FLOAT:
        return abs(float(php_val) - float(py_val)) <= FLOAT_TOLERANCE
    if col == "LastGame":
        return str(php_val)[:19] == str(py_val)[:19]
    return int(php_val) == int(py_val)


def create_playertable_snapshot(
    conn: pymysql.connections.Connection,
    player_ids: list[int],
    *,
    dry_run: bool,
) -> None:
    if not player_ids:
        raise SystemExit("No players in checkpoint for playertable snapshot.")

    col_defs = ["player_id INT NOT NULL PRIMARY KEY"]
    for col in PLAYERTABLE_PARITY_COLUMNS:
        if col in PLAYERTABLE_FLOAT:
            col_defs.append(f"`{col}` DOUBLE NULL")
        elif col == "LastGame":
            col_defs.append(f"`{col}` DATETIME NULL")
        else:
            col_defs.append(f"`{col}` BIGINT NULL")
    ddl = f"CREATE TABLE `{SNAPSHOT_TABLE}` ({', '.join(col_defs)})"

    cols_sql = ", ".join(f"`{c}`" for c in PLAYERTABLE_PARITY_COLUMNS)
    select_cols = ", ".join(f"p.`{c}`" for c in PLAYERTABLE_PARITY_COLUMNS)
    placeholders = ", ".join(["%s"] * len(player_ids))

    with conn.cursor() as cur:
        log.info("snapshot: DROP TABLE IF EXISTS %s", SNAPSHOT_TABLE)
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
            cur.execute(ddl)
            insert_sql = (
                f"INSERT INTO `{SNAPSHOT_TABLE}` (player_id, {cols_sql}) "
                f"SELECT p.ID, {select_cols} FROM playertable p "
                f"WHERE p.ID IN ({placeholders})"
            )
            cur.execute(insert_sql, player_ids)
    if not dry_run:
        conn.commit()
    log.info("snapshot: %s players in %s", len(player_ids), SNAPSHOT_TABLE)


def diff_playertable_layer(
    conn: pymysql.connections.Connection,
    player_ids: list[int],
    *,
    max_report: int = 15,
) -> tuple[int, list[str]]:
    if not player_ids:
        return 0, []

    cols = ", ".join(f"p.`{c}` AS py_{c}" for c in PLAYERTABLE_PARITY_COLUMNS)
    snap_cols = ", ".join(f"s.`{c}` AS php_{c}" for c in PLAYERTABLE_PARITY_COLUMNS)
    placeholders = ", ".join(["%s"] * len(player_ids))

    sql = f"""
        SELECT p.ID AS player_id, {cols}, {snap_cols}
        FROM playertable p
        INNER JOIN `{SNAPSHOT_TABLE}` s ON s.player_id = p.ID
        WHERE p.ID IN ({placeholders})
        ORDER BY p.ID ASC
    """

    mismatches = 0
    lines: list[str] = []

    with conn.cursor() as cur:
        cur.execute(sql, player_ids)
        rows = cur.fetchall()

    if len(rows) != len(player_ids):
        return 1, [
            f"diff join: expected {len(player_ids)} players, got {len(rows)}"
        ]

    for row in rows:
        pid = int(row["player_id"])
        for col in PLAYERTABLE_PARITY_COLUMNS:
            php_val = row[f"php_{col}"]
            py_val = row[f"py_{col}"]
            if _values_equal(col, php_val, py_val):
                continue
            mismatches += 1
            if len(lines) < max_report:
                lines.append(
                    f"player_id={pid} {col}: php={php_val!r} python={py_val!r}"
                )
            break

    return mismatches, lines


def drop_playertable_snapshot(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{SNAPSHOT_TABLE}`")
    if not dry_run:
        conn.commit()
