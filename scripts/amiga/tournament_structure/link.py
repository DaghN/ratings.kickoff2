"""Link imported games to fixtures by player pair + chronological order."""

from __future__ import annotations

from dataclasses import dataclass

from scripts.amiga.tournament_structure.build import BuiltFixture, StructureBuildResult


@dataclass(frozen=True, slots=True)
class LinkResult:
    linked: int
    orphans: int


def _pair_key(player_a_id: int, player_b_id: int) -> tuple[int, int]:
    return (min(player_a_id, player_b_id), max(player_a_id, player_b_id))


def link_games_to_fixtures(
    game_rows: list[dict],
    *,
    tournament_id: int,
    build: StructureBuildResult,
) -> LinkResult:
    """Assign fixture_id on game_rows for one tournament (mutates in place)."""
    slots: list[BuiltFixture] = list(build.fixtures)
    linked = 0
    orphans = 0

    tour_games = [row for row in game_rows if int(row["tournament_id"]) == tournament_id]
    for row in tour_games:
        pair = _pair_key(int(row["player_a_id"]), int(row["player_b_id"]))
        match_idx: int | None = None
        for idx, slot in enumerate(slots):
            if _pair_key(slot.player_a_id, slot.player_b_id) == pair:
                match_idx = idx
                break
        if match_idx is None:
            orphans += 1
            continue
        slot = slots.pop(match_idx)
        row["fixture_id"] = slot.fixture_id
        linked += 1

    return LinkResult(linked=linked, orphans=orphans)
