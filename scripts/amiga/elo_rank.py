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
    "peak_elo_rank",
    "peak_elo_rank_tournament_id",
)


def assign_elo_ranks(ratings: dict[int, float]) -> dict[int, int]:
    """Unique ranks 1..N; tie-break player_id ASC (rating LB parity)."""
    ordered = sorted(ratings.items(), key=lambda item: (-item[1], item[0]))
    return {player_id: rank for rank, (player_id, _) in enumerate(ordered, start=1)}


def compute_peak_elo_rank(
    rank: int,
    tournament_id: int,
    prior_peak: int | None,
    prior_peak_tournament_id: int | None,
) -> tuple[int, int]:
    """Running career-best rank; first attainment wins on ties."""
    if prior_peak is None or rank < prior_peak:
        return rank, tournament_id
    if prior_peak_tournament_id is None:
        return prior_peak, tournament_id
    return prior_peak, prior_peak_tournament_id


def load_prior_peak_elo_ranks(
    conn: pymysql.connections.Connection,
    player_ids: list[int],
) -> dict[int, tuple[int | None, int | None]]:
    if not player_ids:
        return {}

    ids = ", ".join(str(int(pid)) for pid in player_ids)
    sql = (
        "SELECT player_id, peak_elo_rank, peak_elo_rank_tournament_id "
        f"FROM amiga_player_current WHERE player_id IN ({ids})"
    )
    out: dict[int, tuple[int | None, int | None]] = {}
    with conn.cursor() as cur:
        cur.execute(sql)
        for row in cur.fetchall():
            peak = row.get("peak_elo_rank")
            peak_tid = row.get("peak_elo_rank_tournament_id")
            out[int(row["player_id"])] = (
                int(peak) if peak is not None else None,
                int(peak_tid) if peak_tid is not None else None,
            )
    return out


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
    """Write timeline rows + refresh ``amiga_player_current`` rank + peak fields."""
    del participant_ids  # all players in ``ranks`` receive current-row updates
    if not ranks:
        return

    player_ids = list(ranks.keys())
    prior_peaks = load_prior_peak_elo_ranks(conn, player_ids)

    rank_rows: list[dict[str, Any]] = []
    current_updates: dict[int, tuple[int, int, int]] = {}
    for pid, rank in ranks.items():
        prior_peak, prior_peak_tid = prior_peaks.get(pid, (None, None))
        peak, peak_tid = compute_peak_elo_rank(
            int(rank),
            int(tournament_id),
            prior_peak,
            prior_peak_tid,
        )
        rank_rows.append(
            {
                "player_id": int(pid),
                "tournament_id": int(tournament_id),
                "event_date": event_date,
                "event_chrono": event_chrono,
                "elo_rank": int(rank),
                "peak_elo_rank": peak,
                "peak_elo_rank_tournament_id": peak_tid,
            }
        )
        current_updates[int(pid)] = (int(rank), peak, peak_tid)

    sql = elo_rank_at_event_upsert_sql()
    with conn.cursor() as cur:
        cur.executemany(sql, rank_rows)

        case_rank = " ".join(
            f"WHEN {pid} THEN {vals[0]}" for pid, vals in current_updates.items()
        )
        case_peak = " ".join(
            f"WHEN {pid} THEN {vals[1]}" for pid, vals in current_updates.items()
        )
        case_peak_tid = " ".join(
            f"WHEN {pid} THEN {vals[2]}" for pid, vals in current_updates.items()
        )
        id_list = ", ".join(str(int(pid)) for pid in current_updates)
        cur.execute(
            f"UPDATE amiga_player_current SET "
            f"elo_rank = CASE player_id {case_rank} END, "
            f"peak_elo_rank = CASE player_id {case_peak} END, "
            f"peak_elo_rank_tournament_id = CASE player_id {case_peak_tid} END "
            f"WHERE player_id IN ({id_list})"
        )
    conn.commit()
