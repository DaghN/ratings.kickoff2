"""World Cup per-event awards + single-WC peaks (WCH-2).

Applied once per World Cup finalize, mutating the cumulative ``slice_accum`` dicts
*before* ``persist_world_cup_slices_at_tournament`` writes them, so the values land
in ``amiga_player_slice_totals`` / ``amiga_player_slice_at_event``:

  - §4.7 awards: +1 ``best_attack_awards`` to the participant with the highest event
    GF/g, +1 ``best_defense_awards`` to the lowest event GA/g. No minimum games
    (``games >= 1`` to have an average); ties -> lowest ``player_id`` (WCH7/WCH9).
  - §4.8 single-WC peaks: per participant, update ``best_single_wc_gf_per_game`` /
    ``best_single_wc_ga_per_game`` (+ anchor ``*_tournament_id``) when this event's
    average strictly beats the stored peak (GF/g higher, GA/g lower).

Verify (WCH-4) recomputes both independently from ``amiga_games``.
"""

from __future__ import annotations

from typing import Any

from scripts.amiga.player_tournament_participation import participation_avg_goals_per_game

__all__ = [
    "event_average",
    "compute_event_award_winners",
    "apply_wc_slice_awards_and_peaks",
]


def event_average(goals: int, games: int) -> float | None:
    """Per-game average rounded to 4 d.p. (half-up) — matches slice decimal(6,4)."""
    return participation_avg_goals_per_game(int(goals), int(games))


def compute_event_award_winners(
    participation_rows: list[dict[str, Any]],
) -> tuple[int | None, int | None]:
    """Return (attack_winner_pid, defense_winner_pid) for one World Cup event.

    Attack winner: max event GF/g. Defense winner: min event GA/g. Only participants
    with ``games >= 1`` are eligible. Ties broken by lowest ``player_id``.
    """
    attack_best: tuple[float, int] | None = None   # (-gf_per_game, pid) minimised
    defense_best: tuple[float, int] | None = None  # (ga_per_game, pid) minimised
    for row in participation_rows:
        games = int(row.get("games") or 0)
        if games <= 0:
            continue
        pid = int(row["player_id"])
        gf_pg = event_average(int(row.get("goals_for") or 0), games)
        ga_pg = event_average(int(row.get("goals_against") or 0), games)
        if gf_pg is not None:
            key = (-gf_pg, pid)
            if attack_best is None or key < attack_best:
                attack_best = key
        if ga_pg is not None:
            key = (ga_pg, pid)
            if defense_best is None or key < defense_best:
                defense_best = key
    attack_pid = attack_best[1] if attack_best is not None else None
    defense_pid = defense_best[1] if defense_best is not None else None
    return attack_pid, defense_pid


def _update_peak(slice_row: dict[str, Any], event_row: dict[str, Any], tournament_id: int) -> None:
    games = int(event_row.get("games") or 0)
    if games <= 0:
        return
    gf_pg = event_average(int(event_row.get("goals_for") or 0), games)
    ga_pg = event_average(int(event_row.get("goals_against") or 0), games)
    if gf_pg is not None:
        cur = slice_row.get("best_single_wc_gf_per_game")
        if cur is None or gf_pg > float(cur):
            slice_row["best_single_wc_gf_per_game"] = gf_pg
            slice_row["best_single_wc_gf_per_game_tournament_id"] = int(tournament_id)
    if ga_pg is not None:
        cur = slice_row.get("best_single_wc_ga_per_game")
        if cur is None or ga_pg < float(cur):
            slice_row["best_single_wc_ga_per_game"] = ga_pg
            slice_row["best_single_wc_ga_per_game_tournament_id"] = int(tournament_id)


def apply_wc_slice_awards_and_peaks(
    slice_accum: dict[int, dict[str, Any]],
    participation_rows: list[dict[str, Any]],
    tournament_id: int,
) -> None:
    """Mutate cumulative WC slice dicts for this World Cup's participants."""
    for row in participation_rows:
        pid = int(row["player_id"])
        slice_row = slice_accum.get(pid)
        if slice_row is None:
            continue
        _update_peak(slice_row, row, tournament_id)

    attack_pid, defense_pid = compute_event_award_winners(participation_rows)
    if attack_pid is not None and attack_pid in slice_accum:
        slice_accum[attack_pid]["best_attack_awards"] = (
            int(slice_accum[attack_pid].get("best_attack_awards") or 0) + 1
        )
    if defense_pid is not None and defense_pid in slice_accum:
        slice_accum[defense_pid]["best_defense_awards"] = (
            int(slice_accum[defense_pid].get("best_defense_awards") or 0) + 1
        )