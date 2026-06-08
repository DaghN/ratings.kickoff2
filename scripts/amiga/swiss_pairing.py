"""Minimal Swiss pairing helpers (v1 — seed order round 1, score groups later)."""

from __future__ import annotations

import math
from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class SwissPairing:
    round_no: int
    match_no: int
    player_a_id: int
    player_b_id: int


def swiss_round_count(player_count: int) -> int:
    """Default Swiss length: ceil(log2(n)), minimum 1."""
    if player_count < 2:
        return 0
    return max(1, math.ceil(math.log2(player_count)))


def _pair_key(a: int, b: int) -> tuple[int, int]:
    return (min(a, b), max(a, b))


def swiss_round1_pairings(player_ids: list[int]) -> tuple[list[SwissPairing], int | None]:
    """Round 1: pair by seed order (1v2, 3v4, …). Lowest seed bye if odd count."""
    if len(player_ids) < 2:
        raise ValueError("at least two players required")
    ordered = list(player_ids)
    bye_player: int | None = None
    if len(ordered) % 2 == 1:
        bye_player = ordered.pop()

    pairings: list[SwissPairing] = []
    match_no = 1
    for idx in range(0, len(ordered), 2):
        pairings.append(
            SwissPairing(
                round_no=1,
                match_no=match_no,
                player_a_id=ordered[idx],
                player_b_id=ordered[idx + 1],
            )
        )
        match_no += 1
    return pairings, bye_player


def swiss_round_pairings(
    *,
    round_no: int,
    player_ids: list[int],
    points: dict[int, int],
    goals_for: dict[int, int],
    goals_against: dict[int, int],
    played_pairs: set[tuple[int, int]],
) -> tuple[list[SwissPairing], int | None]:
    """Later rounds: sort by Swiss score, greedy pair avoiding rematches when possible."""
    if round_no < 2:
        raise ValueError("use swiss_round1_pairings for round 1")
    remaining = list(player_ids)
    bye_player: int | None = None
    if len(remaining) % 2 == 1:
        # Bye to lowest score (then lowest GD); deterministic tie-break on id.
        remaining.sort(
            key=lambda pid: (
                points.get(pid, 0),
                goals_for.get(pid, 0) - goals_against.get(pid, 0),
                goals_for.get(pid, 0),
                -pid,
            )
        )
        bye_player = remaining.pop(0)

    remaining.sort(
        key=lambda pid: (
            -points.get(pid, 0),
            -(goals_for.get(pid, 0) - goals_against.get(pid, 0)),
            -goals_for.get(pid, 0),
            pid,
        )
    )

    pairings: list[SwissPairing] = []
    match_no = 1
    used: set[int] = set()

    for pid in remaining:
        if pid in used:
            continue
        partner: int | None = None
        for candidate in remaining:
            if candidate == pid or candidate in used:
                continue
            if _pair_key(pid, candidate) not in played_pairs:
                partner = candidate
                break
        if partner is None:
            for candidate in reversed(remaining):
                if candidate == pid or candidate in used:
                    continue
                partner = candidate
                break
        if partner is None:
            raise ValueError(f"no partner found for player_id={pid} in round {round_no}")
        used.add(pid)
        used.add(partner)
        pairings.append(
            SwissPairing(
                round_no=round_no,
                match_no=match_no,
                player_a_id=pid,
                player_b_id=partner,
            )
        )
        match_no += 1

    return pairings, bye_player


def collect_played_pairs(
    games: list[dict],
) -> set[tuple[int, int]]:
    """From game rows with player_a_id / player_b_id."""
    out: set[tuple[int, int]] = set()
    for g in games:
        out.add(_pair_key(int(g["player_a_id"]), int(g["player_b_id"])))
    return out


def standings_totals(
    games: list[dict],
) -> tuple[dict[int, int], dict[int, int], dict[int, int]]:
    """Points, GF, GA per player from played games (3/1/0)."""
    points: dict[int, int] = {}
    gf: dict[int, int] = {}
    ga: dict[int, int] = {}

    def bump(pid: int, pts: int, for_goals: int, against_goals: int) -> None:
        points[pid] = points.get(pid, 0) + pts
        gf[pid] = gf.get(pid, 0) + for_goals
        ga[pid] = ga.get(pid, 0) + against_goals

    for g in games:
        a, b = int(g["player_a_id"]), int(g["player_b_id"])
        ga_a, ga_b = int(g["goals_a"]), int(g["goals_b"])
        if ga_a > ga_b:
            bump(a, 3, ga_a, ga_b)
            bump(b, 0, ga_b, ga_a)
        elif ga_b > ga_a:
            bump(b, 3, ga_b, ga_a)
            bump(a, 0, ga_a, ga_b)
        else:
            bump(a, 1, ga_a, ga_b)
            bump(b, 1, ga_b, ga_a)
    return points, gf, ga
