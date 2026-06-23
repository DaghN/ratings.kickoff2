"""Career Elo ladder rank at tournament finalize (LB sort: Rating DESC, player_id ASC)."""

from __future__ import annotations

from typing import Any

import pymysql

from scripts.k2_rating_core.player_state import PlayerState

ELO_RANK_AT_EVENT_COLUMNS: tuple[str, ...] = (
    "player_id",
    "tournament_id",
    "event_date",
    "event_chrono",
    "elo_rank",
)


def assign_elo_ranks(ratings: dict[int, float]) -> dict[int, int]:
    """Unique ranks 1..N; tie-break player_id ASC (rating LB parity)."""
    ordered = sorted(ratings.items(), key=lambda item: (-item[1], item[0]))
    return {player_id: rank for rank, (player_id, _) in enumerate(ordered, start=1)}


def load_career_ratings_through_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    players: dict[int, PlayerState],
    active_participant_ids: list[int],
) -> dict[int, float]:
    """
    Career ``Rating`` for every player with NumberGames > 0 after this tournament finalizes.

    Non-participants: latest snapshot strictly before this tournament.
    Active participants: in-memory ``PlayerState`` after the event batch.
    """
    sql = """
        SELECT x.player_id, x.Rating
        FROM (
            SELECT
                s.player_id,
                s.Rating,
                s.NumberGames,
                ROW_NUMBER() OVER (
                    PARTITION BY s.player_id
                    ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
                ) AS rn
            FROM amiga_player_event_snapshots s
            INNER JOIN tournaments tc ON tc.id = %s
            WHERE (
                s.event_date < tc.event_date
                OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono)
                OR (
                    s.event_date = tc.event_date
                    AND s.event_chrono = tc.chrono
                    AND s.tournament_id < tc.id
                )
            )
        ) x
        WHERE x.rn = 1 AND x.NumberGames > 0
    """
    ratings: dict[int, float] = {}
    with conn.cursor() as cur:
        cur.execute(sql, (tournament_id,))
        for row in cur.fetchall():
            rating = row.get("Rating")
            if rating is not None:
                ratings[int(row["player_id"])] = float(rating)

    for pid in active_participant_ids:
        state = players.get(pid)
        if state is not None and state.games > 0:
            ratings[pid] = float(state.rating)

    return ratings


def elo_rank_at_event_upsert_sql() -> str:
    cols = ", ".join(f"`{c}`" for c in ELO_RANK_AT_EVENT_COLUMNS)
    vals = ", ".join(f"%({c})s" for c in ELO_RANK_AT_EVENT_COLUMNS)
    updates = ", ".join(
        f"`{c}`=VALUES(`{c}`)" for c in ELO_RANK_AT_EVENT_COLUMNS if c not in ("player_id", "tournament_id")
    )
    return (
        f"INSERT INTO `amiga_player_elo_rank_at_event` ({cols}) VALUES ({vals}) "
        f"ON DUPLICATE KEY UPDATE {updates}"
    )


def persist_elo_ranks_at_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    event_date: Any,
    event_chrono: float,
    ranks: dict[int, int],
    *,
    participant_ids: set[int],
) -> None:
    """Write timeline rows + refresh ``amiga_player_current.elo_rank`` for non-participants."""
    if not ranks:
        return

    rank_rows = [
        {
            "player_id": pid,
            "tournament_id": tournament_id,
            "event_date": event_date,
            "event_chrono": event_chrono,
            "elo_rank": rank,
        }
        for pid, rank in ranks.items()
    ]
    sql = elo_rank_at_event_upsert_sql()
    with conn.cursor() as cur:
        cur.executemany(sql, rank_rows)

        non_participants = [pid for pid in ranks if pid not in participant_ids]
        if non_participants:
            case_parts = " ".join(f"WHEN {int(pid)} THEN {int(ranks[pid])}" for pid in non_participants)
            id_list = ", ".join(str(int(pid)) for pid in non_participants)
            cur.execute(
                f"UPDATE amiga_player_current SET elo_rank = CASE player_id {case_parts} END "
                f"WHERE player_id IN ({id_list})"
            )
    conn.commit()
