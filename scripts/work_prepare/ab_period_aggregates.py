"""P5 aggregate tables layer-5 parity."""

from __future__ import annotations

import logging

import pymysql

log = logging.getLogger(__name__)

P5_TABLES: tuple[tuple[str, tuple[str, ...], tuple[str, ...]], ...] = (
    (
        "server_daily_activity",
        "parity_ab_server_daily_activity_php",
        ("activity_day",),
        ("rated_games", "active_players"),
    ),
    (
        "player_period_league",
        "parity_ab_player_period_league_php",
        ("period_type", "period_start", "player_id"),
        (
            "played",
            "wins",
            "draws",
            "losses",
            "goals_for",
            "goals_against",
            "goal_difference",
            "points",
        ),
    ),
    (
        "player_matchup_summary",
        "parity_ab_player_matchup_summary_php",
        ("player_id", "opponent_id"),
        ("games", "wins", "draws", "losses", "goals_for", "goals_against"),
    ),
    (
        "server_period_game_totals",
        "parity_ab_server_period_game_totals_php",
        ("period_type", "period_start"),
        ("rated_games", "total_goals", "draws", "double_digit_games", "clean_sheets"),
    ),
    (
        "server_period_matchups",
        "parity_ab_server_period_matchups_php",
        ("period_type", "period_start", "player_a", "player_b"),
        ("games",),
    ),
)


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


def create_period_aggregate_snapshots(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    for source, snapshot, _keys, _vals in P5_TABLES:
        _copy_table_snapshot(conn, source, snapshot, dry_run=dry_run)


def _diff_table(
    conn: pymysql.connections.Connection,
    live: str,
    snapshot: str,
    key_cols: tuple[str, ...],
    value_cols: tuple[str, ...],
    *,
    max_report: int = 8,
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


def diff_period_aggregate_layers(
    conn: pymysql.connections.Connection,
    *,
    max_report: int = 8,
) -> tuple[int, list[str]]:
    total_bad = 0
    all_lines: list[str] = []
    for source, snapshot, key_cols, value_cols in P5_TABLES:
        n_bad, lines = _diff_table(
            conn,
            source,
            snapshot,
            key_cols,
            value_cols,
            max_report=max_report,
        )
        total_bad += n_bad
        all_lines.extend(lines)

    return total_bad, all_lines


def drop_period_aggregate_snapshots(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            for _source, snapshot, _keys, _vals in P5_TABLES:
                cur.execute(f"DROP TABLE IF EXISTS `{snapshot}`")
    if not dry_run:
        conn.commit()
