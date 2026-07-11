"""Derive participation placement from standings scopes.

Event finish derivation for ``event_finish_position`` (honours rules tiers A–D).

Target ``derive_event_finish_position`` → ``event_finish_position`` per
``docs/amiga-tournament-honours-rules.md`` tiers A–E + ``best_knockout_phase``.
"""

from __future__ import annotations

import re
from collections import defaultdict
from typing import Any

from scripts.amiga.tournament_honours import (
    compute_wc_podium_finish_from_standings,
    is_world_cup_tournament,
    knockout_scope_label,
)

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
    if text == "finals":
        return "final"
    if re.match(r"^(\d+)(?:st|nd|rd|th)\s+place\s+finals$", text):
        return re.sub(r"finals$", "final", text)
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
    """World Cup: one group rank per player (lexicographically first league scope)."""
    by_player: dict[int, tuple[str, int]] = {}
    for row in standing_rows:
        if str(row.get("scope_type") or "") != "league":
            continue
        player_id = int(row["player_id"])
        scope_key = str(row.get("scope_key") or "")
        position = int(row["position"])
        current = by_player.get(player_id)
        if current is None or scope_key < current[0]:
            by_player[player_id] = (scope_key, position)
    return {pid: pos for pid, (_key, pos) in by_player.items()}


def resolve_primary_league_standings(
    standing_rows: list[dict[str, Any]],
) -> dict[int, int]:
    """
    Primary league table for honours Tier B/C (policy amiga-standings-scope-policy §3).

    Resolution: ``league`` + empty key → single labeled scope → largest scope (lex tie-break).
    """
    league_rows = [
        r for r in standing_rows if str(r.get("scope_type") or "") == "league"
    ]
    if not league_rows:
        return {}

    empty_key_rows = [
        r for r in league_rows if str(r.get("scope_key") or "") == ""
    ]
    if empty_key_rows:
        return {
            int(row["player_id"]): int(row["position"]) for row in empty_key_rows
        }

    by_key: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in league_rows:
        scope_key = str(row.get("scope_key") or "")
        if scope_key != "":
            by_key[scope_key].append(row)

    if not by_key:
        return {}

    if len(by_key) == 1:
        only_key = next(iter(by_key))
        return {
            int(row["player_id"]): int(row["position"]) for row in by_key[only_key]
        }

    chosen_key = min(by_key.keys(), key=lambda k: (-len(by_key[k]), k))
    return {
        int(row["player_id"]): int(row["position"]) for row in by_key[chosen_key]
    }


def _knockout_or_placement_rows(standing_rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [
        r
        for r in standing_rows
        if str(r.get("scope_type") or "") in {"knockout", "placement"}
    ]


def is_main_final_label(label: str) -> bool:
    """Main-bracket Final only — not subsidiary cups (Silver Cup Final, etc.)."""
    return _normalize_knockout_label(label) == "final"


def placement_final_winner_loser_ranks(label: str) -> tuple[int, int] | None:
    """Nth-place final scopes (3rd, 5th, 7th, …) → winner rank N, loser N+1."""
    norm = _normalize_knockout_label(label)
    match = re.match(r"^(\d+)(?:st|nd|rd|th)\s+place\s+final$", norm)
    if not match:
        return None
    base = int(match.group(1))
    return base, base + 1


def is_third_place_final_label(label: str) -> bool:
    return placement_final_winner_loser_ranks(label) == (3, 4)


def is_semi_final_label(label: str) -> bool:
    norm = _normalize_knockout_label(label)
    return norm in {"semi final", "semi finals"}


def _has_third_place_final_scope(ko_rows: list[dict[str, Any]]) -> bool:
    for row in ko_rows:
        if str(row.get("scope_type") or "") != "knockout":
            continue
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        if is_third_place_final_label(label):
            return True
    return False


def is_subsidiary_cup_knockout_label(label: str) -> bool:
    """Silver/Bronze/KOA cup tracks — not main-bracket depth for best_knockout_phase."""
    norm = _normalize_knockout_label(label)
    return bool(re.match(r"^(?:silver|bronze|koa)\s+cup", norm))


def is_main_bracket_knockout_label(label: str) -> bool:
    """Main-bracket KO scopes only (excludes subsidiary cup finals)."""
    if is_subsidiary_cup_knockout_label(label):
        return False
    if knockout_round_depth(label) > 0:
        return True
    return placement_final_winner_loser_ranks(label) is not None


def derive_best_knockout_phase(
    standing_rows: list[dict[str, Any]],
    player_id: int,
) -> str | None:
    """
    Deepest main-bracket knockout round label for one player (display / diagnostics).

    Returns the phase label from standings scope keys (e.g. ``Semi Finals``, ``Final``).
    NULL when the player has no main-bracket knockout rows.
    """
    best_label: str | None = None
    best_depth = -1
    best_pos = 99

    for row in standing_rows:
        if str(row.get("scope_type") or "") != "knockout":
            continue
        if int(row["player_id"]) != int(player_id):
            continue
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        if not is_main_bracket_knockout_label(label):
            continue
        depth = knockout_round_depth(label)
        pos = int(row["position"])
        if depth > best_depth or (depth == best_depth and pos < best_pos):
            best_depth = depth
            best_pos = pos
            best_label = label

    return best_label


def _deepest_knockout_rank_key(
    ko_rows: list[dict[str, Any]],
    player_id: int,
) -> tuple[int, int]:
    """Sort key: deeper round first, then better position within the tie."""
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
    return deepest_depth, deepest_pos


def compute_tier_a_knockout_finish(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    """Tier A — pure knockout (no overall scope): Final 1/2, 3rd-place 3/4, shared semi bronze, 5+."""
    ko_rows = _knockout_or_placement_rows(standing_rows)
    if not ko_rows:
        return {}

    positions: dict[int, int] = {}
    all_players = {int(r["player_id"]) for r in ko_rows}

    def assign_knockout_podium(
        label_predicate,
        winner_rank: int,
        loser_rank: int,
    ) -> None:
        for row in ko_rows:
            if str(row.get("scope_type") or "") != "knockout":
                continue
            label = knockout_scope_label(str(row.get("scope_key") or ""))
            if not label_predicate(label):
                continue
            player_id = int(row["player_id"])
            pos = int(row["position"])
            if pos == 1 and player_id not in positions:
                positions[player_id] = winner_rank
            elif pos == 2 and player_id not in positions:
                positions[player_id] = loser_rank

    assign_knockout_podium(is_main_final_label, 1, 2)

    for row in ko_rows:
        if str(row.get("scope_type") or "") != "knockout":
            continue
        label = knockout_scope_label(str(row.get("scope_key") or ""))
        ranks = placement_final_winner_loser_ranks(label)
        if ranks is None:
            continue
        winner_rank, loser_rank = ranks
        player_id = int(row["player_id"])
        pos = int(row["position"])
        if pos == 1 and player_id not in positions:
            positions[player_id] = winner_rank
        elif pos == 2 and player_id not in positions:
            positions[player_id] = loser_rank

    if (
        not _has_third_place_final_scope(ko_rows)
        and 1 in positions.values()
        and 2 in positions.values()
    ):
        for row in ko_rows:
            if str(row.get("scope_type") or "") != "knockout":
                continue
            label = knockout_scope_label(str(row.get("scope_key") or ""))
            if not is_semi_final_label(label):
                continue
            player_id = int(row["player_id"])
            if int(row["position"]) == 2 and player_id not in positions:
                positions[player_id] = 3

    unassigned = sorted(all_players - set(positions.keys()))
    if not unassigned:
        return positions

    depth_rows = [
        (player_id, *_deepest_knockout_rank_key(ko_rows, player_id), player_id)
        for player_id in unassigned
    ]
    depth_rows.sort(key=lambda item: (-item[1], item[2], item[3]))

    next_rank = max(5, max(positions.values(), default=0) + 1)
    for player_id, _depth, _pos, _pid in depth_rows:
        positions[player_id] = next_rank
        next_rank += 1

    return positions


def compute_tier_b_league_cup_finish(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    """
    Tier B — league + cup: cup podium + shared semi bronze from main bracket;
    everyone else from primary league; cup assignments override league for KO players.
    """
    primary_league = resolve_primary_league_standings(standing_rows)
    ko_rows = _knockout_or_placement_rows(standing_rows)
    has_cup_knockout = any(str(r.get("scope_type") or "") == "knockout" for r in ko_rows)
    if not primary_league or not has_cup_knockout:
        return {}

    cup_finish = compute_tier_a_knockout_finish(standing_rows)
    finish = dict(primary_league)
    finish.update(cup_finish)
    return finish


def compute_tier_d_wc_finish(standing_rows: list[dict[str, Any]]) -> dict[int, int]:
    """
    Tier D — World Cup podium → ``event_finish_position`` 1 / 2 / 3.

    Below-podium entrants omitted (NULL). Group phase never copied to finish.
    """
    return compute_wc_podium_finish_from_standings(standing_rows)


def apply_finish_overrides(
    finish: dict[int, int | None],
    overrides: dict[int, int] | None,
) -> dict[int, int | None]:
    """Tier E — curated rows win over generic tier assignments."""
    if not overrides:
        return finish
    merged: dict[int, int | None] = dict(finish)
    for player_id, position in overrides.items():
        merged[int(player_id)] = int(position)
    return merged


def derive_event_finish_position(
    standing_rows: list[dict[str, Any]],
    *,
    tournament_name: str,
    has_league: bool = False,
    has_cup: bool = False,
    is_world_cup: bool | None = None,
    player_ids: list[int] | None = None,
    overrides: dict[int, int] | None = None,
) -> dict[int, int | None]:
    """
    Map player_id → event_finish_position (NULL when unknown / deferred tier).

    Tiers implemented: A (pure KO), B (league+cup), C (pure league), D (WC podium 1/2/3).
    Tier E: ``overrides`` from ``amiga_tournament_finish_override`` wins per player.
    """
    primary_league = resolve_primary_league_standings(standing_rows)
    wc_event = is_world_cup if is_world_cup is not None else is_world_cup_tournament(tournament_name)
    if wc_event:
        finish = compute_tier_d_wc_finish(standing_rows)
    elif has_league and has_cup and primary_league:
        finish = compute_tier_b_league_cup_finish(standing_rows)
    elif primary_league:
        finish = primary_league
    else:
        finish = compute_tier_a_knockout_finish(standing_rows)

    finish = apply_finish_overrides(finish, overrides)

    if player_ids is None:
        return finish

    out: dict[int, int | None] = {}
    for pid in player_ids:
        pid_int = int(pid)
        if overrides and pid_int in overrides:
            out[pid_int] = int(overrides[pid_int])
        elif overrides:
            out[pid_int] = None
        else:
            out[pid_int] = finish.get(pid_int)
    return out


def participation_is_winner(
    *,
    tournament_name: str,
    event_finish_position: int | None = None,
) -> bool:
    """Honours rules v2 §4.3 — ``event_finish_position = 1`` (all tournaments)."""
    del tournament_name
    return event_finish_position == 1
