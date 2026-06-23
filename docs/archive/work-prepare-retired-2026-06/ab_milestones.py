"""player_milestones layer-6 parity (P6)."""

from __future__ import annotations

import logging

import pymysql

log = logging.getLogger(__name__)

MILESTONE_SNAPSHOT = "parity_ab_player_milestones_php"

# Not written by ProcessCompletedGame (register, day-close, rebuild).
P6_PARITY_EXCLUDE_KEYS: frozenset[str] = frozenset(
    {
        "perfect_day",
        "nightmare_day",
        "entered_arena",
    }
)

MILESTONE_COLS = (
    "milestone_key",
    "achieved_at",
    "value",
    "source_kind",
    "source_game_id",
    "source_league_kind",
    "source_period_type",
    "source_period_start",
)


def create_milestones_snapshot(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        log.info("snapshot: DROP TABLE IF EXISTS %s", MILESTONE_SNAPSHOT)
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{MILESTONE_SNAPSHOT}`")
            cur.execute(f"CREATE TABLE `{MILESTONE_SNAPSHOT}` LIKE `player_milestones`")
            cur.execute(f"INSERT INTO `{MILESTONE_SNAPSHOT}` SELECT * FROM `player_milestones`")
            cur.execute(f"SELECT COUNT(*) AS n FROM `{MILESTONE_SNAPSHOT}`")
            n = int(cur.fetchone()["n"])
    if not dry_run:
        conn.commit()
    log.info("snapshot: %s rows in %s", n, MILESTONE_SNAPSHOT)


def _parity_exclude_sql(alias: str) -> str:
    keys = ", ".join(f"'{k}'" for k in sorted(P6_PARITY_EXCLUDE_KEYS))
    return f"{alias}.milestone_key NOT IN ({keys})"


def diff_milestones_layer(
    conn: pymysql.connections.Connection,
    *,
    max_report: int = 12,
) -> tuple[int, list[str]]:
    cols = ", ".join(f"l.`{c}` AS py_{c}" for c in MILESTONE_COLS)
    snap_cols = ", ".join(f"s.`{c}` AS php_{c}" for c in MILESTONE_COLS)
    ex_l = _parity_exclude_sql("l")
    ex_s = _parity_exclude_sql("s")
    mismatches = 0
    lines: list[str] = []

    sql = f"""
        SELECT l.player_id, l.milestone_key, {cols}, {snap_cols}
        FROM `player_milestones` l
        INNER JOIN `{MILESTONE_SNAPSHOT}` s
          ON s.player_id = l.player_id AND s.milestone_key = l.milestone_key
        WHERE {ex_l} AND {ex_s} AND (
            l.achieved_at <> s.achieved_at
            OR l.value <> s.value
            OR l.source_kind <> s.source_kind
            OR NOT (l.source_game_id <=> s.source_game_id)
            OR NOT (l.source_league_kind <=> s.source_league_kind)
            OR NOT (l.source_period_type <=> s.source_period_type)
            OR NOT (l.source_period_start <=> s.source_period_start)
        )
        ORDER BY l.player_id ASC, l.milestone_key ASC
        LIMIT {max_report + 1}
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        for row in cur.fetchall():
            mismatches += 1
            if len(lines) < max_report:
                pid = int(row["player_id"])
                key = row["milestone_key"]
                for col in MILESTONE_COLS:
                    if row[f"py_{col}"] != row[f"php_{col}"]:
                        lines.append(
                            f"player_milestones.{col} [{pid}/{key}]: "
                            f"php={row[f'php_{col}']!r} python={row[f'py_{col}']!r}"
                        )
                        break

        cur.execute(f"SELECT COUNT(*) AS n FROM `player_milestones` WHERE {_parity_exclude_sql('player_milestones')}")
        live_n = int(cur.fetchone()["n"])
        cur.execute(
            f"SELECT COUNT(*) AS n FROM `{MILESTONE_SNAPSHOT}` WHERE {_parity_exclude_sql(MILESTONE_SNAPSHOT)}"
        )
        snap_n = int(cur.fetchone()["n"])

    if live_n != snap_n:
        mismatches += abs(live_n - snap_n)
        if len(lines) < max_report:
            lines.append(f"row count player_milestones: php={snap_n} python={live_n}")

    only_php_sql = f"""
        SELECT s.player_id, s.milestone_key
        FROM `{MILESTONE_SNAPSHOT}` s
        LEFT JOIN `player_milestones` l
          ON l.player_id = s.player_id AND l.milestone_key = l.milestone_key
        WHERE l.player_id IS NULL AND {_parity_exclude_sql('s')}
        LIMIT {max_report + 1}
    """
    only_py_sql = f"""
        SELECT l.player_id, l.milestone_key
        FROM `player_milestones` l
        LEFT JOIN `{MILESTONE_SNAPSHOT}` s
          ON s.player_id = l.player_id AND s.milestone_key = l.milestone_key
        WHERE s.player_id IS NULL AND {_parity_exclude_sql('l')}
        LIMIT {max_report + 1}
    """
    with conn.cursor() as cur:
        cur.execute(only_php_sql)
        for row in cur.fetchall():
            mismatches += 1
            if len(lines) < max_report:
                lines.append(
                    f"missing in python: player_id={row['player_id']} key={row['milestone_key']}"
                )
        cur.execute(only_py_sql)
        for row in cur.fetchall():
            mismatches += 1
            if len(lines) < max_report:
                lines.append(
                    f"missing in php: player_id={row['player_id']} key={row['milestone_key']}"
                )

    return mismatches, lines


def drop_milestones_snapshot(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        if not dry_run:
            cur.execute(f"DROP TABLE IF EXISTS `{MILESTONE_SNAPSHOT}`")
    if not dry_run:
        conn.commit()
