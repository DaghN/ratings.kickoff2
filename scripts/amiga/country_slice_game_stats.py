"""World Cup country slice — per-game network, domestic/international, rating pairs."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from scripts.amiga.country_slice_totals import (
    country_token_for_player,
    empty_country_world_cup_slice,
    finalize_country_slice_row,
)
from scripts.amiga.performance_rating import performance_rating_from_pairs
from scripts.amiga.player_geo_year import normalize_country


@dataclass
class CountryWorldCupSliceTracker:
    """Country-grain WC game accumulator (network sets + domestic/international)."""

    country_token: str
    row: dict[str, Any] = field(default_factory=empty_country_world_cup_slice)
    _opponent_countries_faced: set[str] = field(default_factory=set)
    _opponent_countries_beaten: set[str] = field(default_factory=set)
    _opponents: set[int] = field(default_factory=set)
    _victims: set[int] = field(default_factory=set)
    _dd_victims: set[int] = field(default_factory=set)
    _cs_victims: set[int] = field(default_factory=set)
    _perf_pairs: list[tuple[float, float]] = field(default_factory=list)
    _sum_opponent_rating: float = 0.0
    _player_games_from_loop: int = 0

    def seed_own_country(self) -> None:
        if self.country_token != "Unknown":
            self._opponent_countries_faced.add(self.country_token)

    def apply_player_game_perspective(
        self,
        *,
        opponent_id: int,
        opponent_country_token: str,
        goals_for: int,
        goals_against: int,
        actual_score: float,
        dd_for: bool,
        opponent_rating: float,
    ) -> None:
        won = actual_score == 1.0
        self._player_games_from_loop += 1
        self._sum_opponent_rating += float(opponent_rating)
        self._perf_pairs.append((float(opponent_rating), float(actual_score)))

        if opponent_country_token == self.country_token:
            self.row["domestic_games"] = int(self.row["domestic_games"]) + 1
        else:
            self.row["international_games"] = int(self.row["international_games"]) + 1

        self._opponents.add(opponent_id)
        if won:
            self._victims.add(opponent_id)

        opp_country = normalize_country(opponent_country_token)
        if opp_country:
            self._opponent_countries_faced.add(opp_country)
            if won:
                self._opponent_countries_beaten.add(opp_country)

        if dd_for:
            self.row["double_digits"] = int(self.row.get("double_digits") or 0) + 1
            self._dd_victims.add(opponent_id)
        if goals_against == 0:
            self._cs_victims.add(opponent_id)

    def flush_into(self, target: dict[str, Any]) -> None:
        target["domestic_games"] = int(self.row.get("domestic_games") or 0)
        target["international_games"] = int(self.row.get("international_games") or 0)
        target["opponent_countries_faced"] = len(self._opponent_countries_faced)
        target["opponent_countries_beaten"] = len(self._opponent_countries_beaten)
        target["different_opponents"] = len(self._opponents)
        target["different_victims"] = len(self._victims)
        target["double_digits_victims"] = len(self._dd_victims)
        target["clean_sheets_victims"] = len(self._cs_victims)

        games = int(target.get("games") or 0)
        if games > 0:
            target["average_opponent_rating"] = round(self._sum_opponent_rating / games, 4)
        else:
            target["average_opponent_rating"] = None

        perf = performance_rating_from_pairs(self._perf_pairs)
        target["performance_rating"] = round(perf, 4) if perf is not None else None

        finalize_country_slice_row(target)


def apply_wc_games_to_country_trackers(
    games: list[dict[str, Any]],
    player_countries: dict[int, str | None],
    trackers: dict[str, CountryWorldCupSliceTracker],
) -> None:
    from scripts.k2_rating_core.outcome import outcome_from_goals

    for game in games:
        id_a = int(game["idA"])
        id_b = int(game["idB"])
        goals_a = int(game["GoalsA"])
        goals_b = int(game["GoalsB"])
        rating_a = float(game.get("rating_a") or 0.0)
        rating_b = float(game.get("rating_b") or 0.0)
        outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
        score_b = 1.0 - outcome.actual_score if outcome.actual_score != 0.5 else 0.5

        token_a = country_token_for_player(player_countries, id_a)
        token_b = country_token_for_player(player_countries, id_b)

        if token_a not in trackers:
            trackers[token_a] = CountryWorldCupSliceTracker(country_token=token_a)
            trackers[token_a].seed_own_country()
        if token_b not in trackers:
            trackers[token_b] = CountryWorldCupSliceTracker(country_token=token_b)
            trackers[token_b].seed_own_country()

        trackers[token_a].apply_player_game_perspective(
            opponent_id=id_b,
            opponent_country_token=token_b,
            goals_for=goals_a,
            goals_against=goals_b,
            actual_score=outcome.actual_score,
            dd_for=bool(outcome.dd_player_a),
            opponent_rating=rating_b,
        )
        trackers[token_b].apply_player_game_perspective(
            opponent_id=id_a,
            opponent_country_token=token_a,
            goals_for=goals_b,
            goals_against=goals_a,
            actual_score=score_b,
            dd_for=bool(outcome.dd_player_b),
            opponent_rating=rating_a,
        )
