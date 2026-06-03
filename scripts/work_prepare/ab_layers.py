"""Parity layer registry for post-game A/B (docs/post-game-php-development.md §9)."""

from __future__ import annotations

from dataclasses import dataclass

from scripts.ladder.constants import RATEDRESULTS_CLEAR

# Layer 1 — ratedresults derived (P1)
RATEDRESULTS_DERIVED: tuple[str, ...] = RATEDRESULTS_CLEAR

RATEDRESULTS_FLOAT = frozenset(
    {
        "RatingA",
        "RatingB",
        "RatingDifference",
        "ExpectedScoreA",
        "ExpectedScoreB",
        "AdjustmentA",
        "AdjustmentB",
        "NewRatingA",
        "NewRatingB",
        "ActualScore",
    }
)

FLOAT_TOLERANCE = 0.001


@dataclass(frozen=True)
class ParityLayerSpec:
    layer_id: int
    name: str
    shipped: bool
    description: str


LAYER_REGISTRY: dict[int, ParityLayerSpec] = {
    0: ParityLayerSpec(
        0,
        "ground_truth",
        True,
        "ratedresults facts (prepare parity, not PHP vs Python)",
    ),
    1: ParityLayerSpec(
        1,
        "ratedresults_derived",
        True,
        "Elo + outcome derived columns (P1)",
    ),
    2: ParityLayerSpec(
        2,
        "playertable_career",
        True,
        "playertable career columns for players in checkpoint (P2)",
    ),
    3: ParityLayerSpec(
        3,
        "generalstatstable",
        True,
        "generalstatstable id=1 aggregates + PG-004 holders (P3)",
    ),
    4: ParityLayerSpec(
        4,
        "period_activity",
        True,
        "player_period_games + player_peak_period_games (P4)",
    ),
    5: ParityLayerSpec(
        5,
        "period_aggregates",
        True,
        "server_daily_activity, player_period_league, matchups, server period totals (P5)",
    ),
    6: ParityLayerSpec(
        6,
        "player_milestones",
        True,
        "player_milestones game-triggered keys (P6)",
    ),
}

PHASE_LAYERS: dict[str, tuple[int, ...]] = {
    "p1": (1,),
    "p2": (1, 2),
    "p3": (1, 2, 3),
    "p4": (1, 2, 3, 4),
    "p5": (1, 2, 3, 4, 5),
    "p6": (1, 2, 3, 4, 5, 6),
    "auto": (1,),
}


def parse_layers_arg(phase: str | None, layers_csv: str | None) -> tuple[int, ...]:
    if layers_csv:
        out: list[int] = []
        for part in layers_csv.split(","):
            part = part.strip()
            if not part:
                continue
            layer_id = int(part)
            if layer_id not in LAYER_REGISTRY:
                raise SystemExit(f"Unknown parity layer {layer_id}")
            out.append(layer_id)
        return tuple(sorted(set(out)))

    key = (phase or "auto").lower()
    if key not in PHASE_LAYERS:
        known = ", ".join(sorted(PHASE_LAYERS))
        raise SystemExit(f"Unknown --phase {phase!r}. Expected one of: {known}")
    return PHASE_LAYERS[key]
