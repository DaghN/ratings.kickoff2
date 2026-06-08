"""Double-elimination bracket definitions and slot resolution (v1: 4 and 8 players)."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class DEFixtureDef:
    fixture_key: str
    stage_key: str
    phase_label: str
    slot_a: str
    slot_b: str


SUPPORTED_BRACKET_SIZES = frozenset({4, 8})


def _rounds_4() -> list[list[DEFixtureDef]]:
    return [
        [
            DEFixtureDef("wb-r1-m1", "winners", "Winners R1", "seed:1", "seed:4"),
            DEFixtureDef("wb-r1-m2", "winners", "Winners R1", "seed:2", "seed:3"),
        ],
        [
            DEFixtureDef("wb-final", "winners", "Winners Final", "winner:wb-r1-m1", "winner:wb-r1-m2"),
            DEFixtureDef("lb-r1-m1", "losers", "Losers R1", "loser:wb-r1-m1", "loser:wb-r1-m2"),
        ],
        [
            DEFixtureDef("lb-final", "losers", "Losers Final", "winner:lb-r1-m1", "loser:wb-final"),
        ],
        [
            DEFixtureDef("grand-final", "grand_final", "Grand Final", "winner:wb-final", "winner:lb-final"),
        ],
    ]


def _rounds_8() -> list[list[DEFixtureDef]]:
    return [
        [
            DEFixtureDef("wb-r1-m1", "winners", "Winners R1", "seed:1", "seed:8"),
            DEFixtureDef("wb-r1-m2", "winners", "Winners R1", "seed:4", "seed:5"),
            DEFixtureDef("wb-r1-m3", "winners", "Winners R1", "seed:2", "seed:7"),
            DEFixtureDef("wb-r1-m4", "winners", "Winners R1", "seed:3", "seed:6"),
        ],
        [
            DEFixtureDef("wb-r2-m1", "winners", "Winners SF", "winner:wb-r1-m1", "winner:wb-r1-m2"),
            DEFixtureDef("wb-r2-m2", "winners", "Winners SF", "winner:wb-r1-m3", "winner:wb-r1-m4"),
            DEFixtureDef("lb-r1-m1", "losers", "Losers R1", "loser:wb-r1-m1", "loser:wb-r1-m2"),
            DEFixtureDef("lb-r1-m2", "losers", "Losers R1", "loser:wb-r1-m3", "loser:wb-r1-m4"),
        ],
        [
            DEFixtureDef("wb-final", "winners", "Winners Final", "winner:wb-r2-m1", "winner:wb-r2-m2"),
            DEFixtureDef("lb-r2-m1", "losers", "Losers R2", "loser:wb-r2-m1", "winner:lb-r1-m1"),
            DEFixtureDef("lb-r2-m2", "losers", "Losers R2", "loser:wb-r2-m2", "winner:lb-r1-m2"),
        ],
        [
            DEFixtureDef("lb-r3-m1", "losers", "Losers SF", "winner:lb-r2-m1", "winner:lb-r2-m2"),
        ],
        [
            DEFixtureDef("lb-final", "losers", "Losers Final", "winner:lb-r3-m1", "loser:wb-final"),
        ],
        [
            DEFixtureDef("grand-final", "grand_final", "Grand Final", "winner:wb-final", "winner:lb-final"),
        ],
    ]


BRACKET_ROUNDS: dict[int, list[list[DEFixtureDef]]] = {
    4: _rounds_4(),
    8: _rounds_8(),
}


def expected_fixture_count(bracket_size: int) -> int:
    rounds = BRACKET_ROUNDS.get(bracket_size)
    if rounds is None:
        raise ValueError(f"unsupported bracket_size={bracket_size}")
    return sum(len(r) for r in rounds)


def bracket_round_count(bracket_size: int) -> int:
    return len(BRACKET_ROUNDS[bracket_size])


def initial_round_defs(bracket_size: int) -> list[DEFixtureDef]:
    return list(BRACKET_ROUNDS[bracket_size][0])


def next_round_defs(bracket_size: int, round_index: int) -> list[DEFixtureDef]:
    """round_index is 1-based next round to create (1 = second round in bracket)."""
    rounds = BRACKET_ROUNDS[bracket_size]
    if round_index < 1 or round_index >= len(rounds):
        return []
    return list(rounds[round_index])


def seed_map(player_ids: list[int]) -> dict[int, int]:
    return {idx + 1: pid for idx, pid in enumerate(player_ids)}


def match_winner(player_a_id: int, player_b_id: int, goals_a: int, goals_b: int) -> tuple[int, int]:
    if goals_a > goals_b:
        return player_a_id, player_b_id
    if goals_b > goals_a:
        return player_b_id, player_a_id
    raise ValueError(f"draw not allowed in elimination: {goals_a}-{goals_b}")


def resolve_slot(
    slot: str,
    *,
    seeds: dict[int, int],
    outcomes: dict[str, dict[str, int]],
) -> int:
    if slot.startswith("seed:"):
        seed_no = int(slot.split(":", 1)[1])
        if seed_no not in seeds:
            raise ValueError(f"unknown seed {seed_no}")
        return seeds[seed_no]
    kind, fixture_key = slot.split(":", 1)
    if fixture_key not in outcomes:
        raise ValueError(f"outcome for {fixture_key!r} not available")
    if kind == "winner":
        return int(outcomes[fixture_key]["winner_id"])
    if kind == "loser":
        return int(outcomes[fixture_key]["loser_id"])
    raise ValueError(f"unknown slot {slot!r}")


def resolve_fixture_players(
    fixture: DEFixtureDef,
    *,
    seeds: dict[int, int],
    outcomes: dict[str, dict[str, int]],
) -> tuple[int, int]:
    return (
        resolve_slot(fixture.slot_a, seeds=seeds, outcomes=outcomes),
        resolve_slot(fixture.slot_b, seeds=seeds, outcomes=outcomes),
    )
