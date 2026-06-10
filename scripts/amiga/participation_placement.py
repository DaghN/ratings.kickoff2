"""Derive participation placement (overall_position) from standings scopes.

Participation grain is ground-truth games; placement is a derived view chosen by
event shape:

1. World Cup — primary group rank (not event finish; medals own podium UI).
2. Overall scope exists — league / marathon rank (mixed events: league phase only).
3. Otherwise — knockout bracket event finish from elimination scopes.
"""

from __future__ import annotations

import re
from typing import Any

from scripts.amiga.tournament_honours import is_world_cup_tournament, knockout_scope_label

# Higher depth = later / better round reached in the main bracket.
_KNOCKOUT_ROUND_DEPTH: dict[str, int] = {
    "round of 64": 10,
    "round of 32": 20,
    "round of 16": 30,
    "quarter final": 40,
    "quarter finals": 40,
    "semi final": 50,
    "semi finals": 50,
    "3rd place final": 55,
    "final": 60,
}


def _normalize_knockout_label(label: str) -> str:
    text = re.sub(r"\s+", " ", str(label or "").strip().lower())
    if _QUARTER_SEMI_SINGULAR_RE.match(text):
        return text + "s" if not text.endswith("s") else text
    return text


_QUARTER_SEMI_SINGULAR_RE = re.compile(r"^(?:quarter|semi)\s+final$", re.IGNORECASE)


def knockout_round_depth(label: str) -> int:
    """Sort key for elimination rounds (higher = deeper in main bracket)."""
    norm = _normalize_knockout_label(label)
    if norm in _KNOCKOUT_ROUND_DEPTH:
        return _KNOCKOUT_ROUND_DEPTH[norm]
    if norm.startswith("places "):
        return 5
    if re.match(r"^\d+(?:st|nd|rd|th)\s+place\s+final$", norm):
        return 55
    return 0


def _standing_rows_for_player(
    standing_rows: list[dict[str, Any]],
    player_id: int,
) -> list[dict[str, Any]]:
    return [r for r in standing_rows if int(r["player_id"]) == player_id]


def derive_wc_group_positions(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    """World Cup: one group rank per player (lexicographically first group scope)."""
    by_player: dict[int, tuple[str, int]] = {}
    for row in standing_rows:
        if str(row.get("scope_type") or "") != "group":
            continue
        player_id = int(row["player_id"])
        scope_key = str(row.get("scope_key") or "")
        position = int(row["position"])
        current = by_player.get(player_id)
        if current is None or scope_key < current[0]:
            by_player[player_id] = (scope_key, position)
    return {pid: pos for pid, (_key, pos) in by_player.items()}


def _overall_positions(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    return {
        int(row["player_id"]): int(row["position"])
        for row in standing_rows
        if str(row.get("scope_type") or "") == "overall"
        and str(row.get("scope_key") or "") == ""
    }


def compute_knockout_event_finish(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    """Bracket event finish when the tournament has no overall scope."""
    ko_rows = [
        r
        for r in standing_rows
        if str(r.get("scope_type") or "") in {"knockout", "placement"}
    ]
    if not ko_rows:
        return {}

    positions: dict[int, int] = {}
    all_players = {int(r["player_id"]) for r in ko_rows}

    def assign_final_podium(label: str, winner_rank: int, loser_rank: int) -> None:
        for row in ko_rows:
            if str(row.get("scope_type") or "") != "knockout":
                continue
            if _normalize_knockout_label(knockout_scope_label(str(row.get("scope_key") or ""))) != label:
                continue
            player_id = int(row["player_id"])
            pos = int(row["position"])
            if pos == 1 and player_id not in positions:
                positions[player_id] = winner_rank
            elif pos == 2 and player_id not in positions:
                positions[player_id] = loser_rank

    assign_final_podium("final", 1, 2)
    assign_final_podium("3rd place final", 3, 4)

    unassigned = sorted(all_players - set(positions.keys()))
    if not unassigned:
        return positions

    depth_rows: list[tuple[int, int, int, int]] = []
    for player_id in unassigned:
        player_rows = _standing_rows_for_player(ko_rows, player_id)
        deepest_depth = -1
        deepest_pos = 99
        for row in player_rows:
            label = knockout_scope_label(str(row.get("scope_key") or ""))
            depth = knockout_round_depth(label)
            pos = int(row["position"])
            if depth > deepest_depth or (depth == deepest_depth and pos < deepest_pos):
                deepest_depth = depth
                deepest_pos = pos
        depth_rows.append((player_id, deepest_depth, deepest_pos, player_id))

    depth_rows.sort(key=lambda item: (-item[1], item[2], item[3]))

    next_rank = max(positions.values(), default=0) + 1
    if next_rank < 3:
        next_rank = 3

    for player_id, _depth, _pos, _pid in depth_rows:
        positions[player_id] = next_rank
        next_rank += 1

    return positions


def derive_participation_positions(
    standing_rows: list[dict[str, Any]],
    *,
    tournament_name: str,
    player_ids: list[int] | None = None,
) -> dict[int, int]:
    """Map player_id → overall_position for participation rows."""
    if is_world_cup_tournament(tournament_name):
        base = derive_wc_group_positions(standing_rows)
    else:
        overall = _overall_positions(standing_rows)
        if overall:
            base = overall
        else:
            base = compute_knockout_event_finish(standing_rows)

    if player_ids is None:
        return base

    return {int(pid): int(base.get(int(pid), 0)) for pid in player_ids}


def participation_is_winner(
    *,
    tournament_name: str,
    overall_position: int,
    wc_medal: str = "none",
) -> bool:
    if is_world_cup_tournament(tournament_name):
        return wc_medal == "gold"
    return overall_position == 1
