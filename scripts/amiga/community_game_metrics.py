"""Shared game-level metric helpers for community + World Cup stats writers."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any


def country_token(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).strip()
    return text if text else None


def year_key(event_date: Any) -> str | None:
    if event_date is None:
        return None
    return str(event_date.year)


def canonical_pair(player_a_id: int, player_b_id: int) -> tuple[int, int]:
    if player_a_id <= player_b_id:
        return player_a_id, player_b_id
    return player_b_id, player_a_id


@dataclass(frozen=True)
class RatedGameMetrics:
    player_a_id: int
    player_b_id: int
    goals_a: int
    goals_b: int
    sum_of_goals: int
    is_draw: bool
    dd_slots: int
    cs_slots: int
    is_high_scoring: bool
    is_low_scoring: bool
    is_blowout: bool
    margin: int
    phase: str | None
    game_id: int | None = None


def rated_game_metrics_from_row(row: dict[str, Any]) -> RatedGameMetrics:
    goals_a = int(row["goals_a"])
    goals_b = int(row["goals_b"])
    sum_of_goals = int(row.get("sum_of_goals") or goals_a + goals_b)
    actual = float(row.get("actual_score") or 0)
    is_draw = abs(actual - 0.5) < 1e-9
    dd_slots = int(row.get("dd_player_a") or 0) + int(row.get("dd_player_b") or 0)
    cs_slots = int(row.get("cs_player_a") or 0) + int(row.get("cs_player_b") or 0)
    margin = abs(goals_a - goals_b)
    phase_raw = row.get("phase")
    phase = str(phase_raw).strip() if phase_raw is not None and str(phase_raw).strip() else None
    return RatedGameMetrics(
        player_a_id=int(row["player_a_id"]),
        player_b_id=int(row["player_b_id"]),
        goals_a=goals_a,
        goals_b=goals_b,
        sum_of_goals=sum_of_goals,
        is_draw=is_draw,
        dd_slots=dd_slots,
        cs_slots=cs_slots,
        is_high_scoring=sum_of_goals >= 10,
        is_low_scoring=sum_of_goals <= 3,
        is_blowout=(not is_draw) and margin >= 5,
        margin=margin,
        phase=phase,
        game_id=int(row["game_id"]) if row.get("game_id") is not None else None,
    )


def is_knockout_phase(phase: str | None) -> bool:
    if not phase:
        return False
    text = phase.lower()
    return "knockout" in text or text in {"final", "semi final", "semi finals", "quarter final", "quarter finals"}


def rate(numerator: int, denominator: int) -> float | None:
    if denominator <= 0:
        return None
    return round(numerator / denominator, 8)
