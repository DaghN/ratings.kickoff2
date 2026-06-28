"""Persist cumulative matchup rows at tournament finalize."""

from __future__ import annotations

import logging
from typing import Any

import pymysql

from scripts.amiga.matchup_cumulative import MatchupCumulative

log = logging.getLogger(__name__)

_PAIR_COLS = (
    "games",
    "wins",
    "draws",
    "losses",
    "goals_for",
    "goals_against",
    "max_goals_for",
    "max_goals_against",
    "min_goals_for",
    "min_goals_against",
    "max_win_margin",
    "max_loss_margin",
    "max_draw_goals",
    "max_goal_sum",
    "min_goal_sum",
    "dd_wins",
    "dd_losses",
    "cs_wins",
    "cs_losses",
    "performance_rating",
)


def persist_matchup_at_event(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    event_date: Any,
    event_chrono: int,
    matchups: MatchupCumulative,
    participant_ids: set[int],
) -> int:
    """Write cumulative pair rows for each participant as of this tournament."""
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id = %s",
            (tournament_id,),
        )

    rows: list[dict[str, Any]] = []
    for pid in sorted(participant_ids):
        for oid, totals in sorted(matchups.pairs_for_player(pid).items()):
            row = totals.to_row(pid, oid)
            row["as_of_tournament_id"] = tournament_id
            row["event_date"] = event_date
            row["event_chrono"] = event_chrono
            rows.append(row)

    if not rows:
        conn.commit()
        return 0

    col_list = ", ".join(
        ["player_id", "opponent_id", "as_of_tournament_id", "event_date", "event_chrono", *_PAIR_COLS]
    )
    placeholders = ", ".join(["%s"] * (5 + len(_PAIR_COLS)))
    sql = f"INSERT INTO amiga_player_matchup_at_event ({col_list}) VALUES ({placeholders})"
    values = [
        (
            r["player_id"],
            r["opponent_id"],
            r["as_of_tournament_id"],
            r["event_date"],
            r["event_chrono"],
            *[r[c] for c in _PAIR_COLS],
        )
        for r in rows
    ]
    with conn.cursor() as cur:
        cur.executemany(sql, values)
    conn.commit()
    log.info(
        "persist_matchup_at_event: tournament_id=%s rows=%s participants=%s",
        tournament_id,
        len(rows),
        len(participant_ids),
    )
    return len(rows)


def upsert_matchup_summary(
    conn: pymysql.connections.Connection,
    matchups: MatchupCumulative,
    participant_ids: set[int],
) -> int:
    """Refresh present summary for each participant's cumulative directed pairs."""
    values: list[tuple[Any, ...]] = []
    for pid in sorted(participant_ids):
        for oid, totals in sorted(matchups.pairs_for_player(pid).items()):
            row = totals.to_row(pid, oid)
            values.append((pid, oid, *[row[c] for c in _PAIR_COLS]))

    if not values:
        return 0

    col_list = ", ".join(["player_id", "opponent_id", *_PAIR_COLS])
    updates = ", ".join(f"{c}=VALUES({c})" for c in _PAIR_COLS)
    sql = (
        f"INSERT INTO amiga_player_matchup_summary ({col_list}) VALUES "
        f"({', '.join(['%s'] * (2 + len(_PAIR_COLS)))}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )
    with conn.cursor() as cur:
        cur.executemany(sql, values)
    conn.commit()
    return len(values)
