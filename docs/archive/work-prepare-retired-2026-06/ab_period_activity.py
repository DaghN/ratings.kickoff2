"""player_period_games + player_peak_period_games layer-4 parity."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

log = logging.getLogger(__name__)

PERIOD_SNAPSHOT = "parity_ab_player_period_games_php"
PEAK_SNAPSHOT = "parity_ab_player_peak_period_games_php"


def _copy_table_snapshot(
    conn: pymysql.connections.Connection,
    source: str,
    snapshot: str,
    *,
    dry_run: bool,
) -> int:
    with conn.cursor() as cur:
        log.info("snapshot: DROP TABLE IF EXISTS %s", snapshot)
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{snapshot}`")
            cur.execute(f"CREATE TABLE `{snapshot}` LIKE `{source}`")
            cur.execute(f"INSERT INTO `{snapshot}` SELECT * FROM `{source}`")
            cur.execute(f"SELECT COUNT(*) AS n FROM `{snapshot}`")
            n = int(cur.fetchone()["n"])
    if not dry_run:
        conn.commit()
    log.info("snapshot: %s rows in %s", n, snapshot)

    return n


def create_period_activity_snapshots(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    _copy_table_snapshot(conn, "player_period_games", PERIOD_SNAPSHOT, dry_run=dry_run)
    _copy_table_snapshot(conn, "player_peak_period_games", PEAK_SNAPSHOT, dry_run=dry_run)


def _diff_table(
    conn: pymysql.connections.Connection,
    live: str,
    snapshot: str,
    key_cols: tuple[str, ...],
    value_cols: tuple[str, ...],
    *,
    max_report: int = 10,
) -> tuple[int, list[str]]:
    key_sel = ", ".join(f"l.`{c}`" for c in key_cols)
    joins = " AND ".join(f"l.`{c}` = s.`{c}`" for c in key_cols)
    mismatches = 0
    lines: list[str] = []

    for vcol in value_cols:
        sql = f"""
            SELECT {key_sel}, l.`{vcol}` AS py_v, s.`{vcol}` AS php_v
            FROM `{live}` l
            INNER JOIN `{snapshot}` s ON {joins}
            WHERE l.`{vcol}` <> s.`{vcol}`
            LIMIT {max_report + 1}
        """
        with conn.cursor() as cur:
            cur.execute(sql)
            for row in cur.fetchall():
                mismatches += 1
                if len(lines) < max_report:
                    keys = ", ".join(f"{c}={row[c]}" for c in key_cols)
                    lines.append(f"{live}.{vcol} [{keys}]: php={row['php_v']!r} python={row['py_v']!r}")

    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS n FROM `{live}`")
        live_n = int(cur.fetchone()["n"])
        cur.execute(f"SELECT COUNT(*) AS n FROM `{snapshot}`")
        snap_n = int(cur.fetchone()["n"])

    if live_n != snap_n:
        mismatches += abs(live_n - snap_n)
        if len(lines) < max_report:
            lines.append(f"row count {live}: php={snap_n} python={live_n}")

    return mismatches, lines


def diff_period_activity_layers(
    conn: pymysql.connections.Connection,
    *,
    max_report: int = 10,
) -> tuple[int, list[str]]:
    period_bad, period_lines = _diff_table(
        conn,
        "player_period_games",
        PERIOD_SNAPSHOT,
        ("period_type", "period_start", "player_id"),
        ("games",),
        max_report=max_report,
    )
    peak_bad, peak_lines = _diff_table(
        conn,
        "player_peak_period_games",
        PEAK_SNAPSHOT,
        ("period_type", "player_id"),
        ("period_start", "games"),
        max_report=max_report,
    )

    return period_bad + peak_bad, period_lines + peak_lines


def drop_period_activity_snapshots(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{PERIOD_SNAPSHOT}`")
            cur.execute(f"DROP TABLE IF EXISTS `{PEAK_SNAPSHOT}`")
    if not dry_run:
        conn.commit()
