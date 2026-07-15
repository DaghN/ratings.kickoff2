"""Sparse inverse victim/culprit count changelog at tournament finalize."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.k2_rating_core.player_state import PlayerState

log = logging.getLogger(__name__)

# metric enum -> (PlayerState attr, amiga_player_current column)
INVERSE_COUNT_METRICS: tuple[tuple[str, str, str], ...] = (
    ("mgs_culprits", "most_goals_scored_culprits", "MostGoalsScoredCulprits"),
    ("bw_culprits", "biggest_win_culprits", "BiggestWinCulprits"),
    ("mgc_victims", "most_goals_conceded_victims", "MostGoalsConcededVictims"),
    ("bl_victims", "biggest_loss_victims", "BiggestLossVictims"),
)

_UPSERT_SQL = """
INSERT INTO amiga_player_inverse_count_at_event
  (player_id, tournament_id, metric, value_after, event_date, event_chrono)
VALUES (%(player_id)s, %(tournament_id)s, %(metric)s, %(value_after)s, %(event_date)s, %(event_chrono)s)
ON DUPLICATE KEY UPDATE
  value_after = VALUES(value_after),
  event_date = VALUES(event_date),
  event_chrono = VALUES(event_chrono)
"""


def load_latest_inverse_changelog_values(
    conn: pymysql.connections.Connection,
) -> dict[tuple[int, str], int]:
    """Latest value_after per (player_id, metric) across all changelog rows."""
    sql = """
        SELECT player_id, metric, value_after
        FROM (
            SELECT player_id, metric, value_after,
                ROW_NUMBER() OVER (
                    PARTITION BY player_id, metric
                    ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC
                ) AS rn
            FROM amiga_player_inverse_count_at_event
        ) x
        WHERE rn = 1
    """
    out: dict[tuple[int, str], int] = {}
    with conn.cursor() as cur:
        cur.execute(sql)
        for row in cur.fetchall():
            out[(int(row["player_id"]), str(row["metric"]))] = int(row["value_after"])
    return out


def persist_inverse_count_changelog_at_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    players: dict[int, PlayerState],
    *,
    prev_values: dict[tuple[int, str], int] | None = None,
) -> int:
    """
    Write changelog rows for players whose inverse counts changed this finalize.

    Includes ghost players (did not participate) when credit transferred away.
    Updates amiga_player_current for all touched players' four inverse columns.

    When ``prev_values`` is provided (full simul), it is used and updated in place
    so each finalize avoids a full changelog window scan.
    """
    owned_prev = False
    if prev_values is None:
        prev_values = load_latest_inverse_changelog_values(conn)
        owned_prev = True

    rows: list[dict[str, Any]] = []
    current_updates: dict[int, dict[str, int]] = {}

    for pid, st in players.items():
        if st.games <= 0:
            continue
        for metric, attr, col in INVERSE_COUNT_METRICS:
            mem = int(getattr(st, attr))
            key = (pid, metric)
            last = prev_values.get(key)
            if last is None:
                if mem == 0:
                    continue
            elif mem == last:
                continue
            rows.append(
                {
                    "player_id": pid,
                    "tournament_id": int(tournament_id),
                    "metric": metric,
                    "value_after": mem,
                    "event_date": event_date,
                    "event_chrono": float(event_chrono),
                }
            )
            current_updates.setdefault(pid, {})[col] = mem
            prev_values[key] = mem

    if not rows:
        return 0

    with conn.cursor() as cur:
        cur.executemany(_UPSERT_SQL, rows)
        for pid, cols in current_updates.items():
            sets = ", ".join(f"`{c}` = %s" for c in cols)
            cur.execute(
                f"UPDATE amiga_player_current SET {sets} WHERE player_id = %s",
                [*cols.values(), pid],
            )
    conn.commit()

    del owned_prev  # silence unused when caller owns prev_values
    log.info(
        "persist_inverse_count_changelog: tournament_id=%s rows=%s players_current=%s",
        tournament_id,
        len(rows),
        len(current_updates),
    )
    return len(rows)