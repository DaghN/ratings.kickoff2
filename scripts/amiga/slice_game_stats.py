"""World Cup slice V2 — per-game texture, DD/CS, network, and geo (finalize only)."""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from scripts.amiga.player_geo_year import normalize_country
from scripts.amiga.slice_totals import empty_world_cup_slice, slice_from_totals_row
from scripts.k2_rating_core.outcome import outcome_from_goals

V2_SCALAR_KEYS: tuple[str, ...] = (
    "goal_ratio",
    "most_goals_scored",
    "most_goals_conceded",
    "biggest_win_difference",
    "biggest_loss_difference",
    "biggest_sum_of_goals",
    "biggest_draw_sum",
    "double_digits",
    "clean_sheets",
    "double_digits_ratio",
    "clean_sheets_ratio",
    "double_digits_conceded",
    "clean_sheets_conceded",
    "double_digits_conceded_ratio",
    "clean_sheets_conceded_ratio",
    "opponent_countries_faced",
    "opponent_countries_beaten",
    "opponent_countries_beaten_by",
    "different_opponents",
    "different_victims",
    "double_digits_victims",
    "clean_sheets_victims",
    "different_culprits",
    "double_digits_culprits",
    "clean_sheets_culprits",
)


def _ratio_db(num: int, den: int) -> float | None:
    if den <= 0:
        return None
    return round(num / den, 4)


def _goal_ratio(goals_for: int, goals_against: int) -> float | None:
    if goals_against <= 0:
        return None
    return round(goals_for / goals_against, 8)


@dataclass
class WorldCupSliceTracker:
    """In-memory WC slice state including network/geo sets (not persisted)."""

    row: dict[str, Any] = field(default_factory=empty_world_cup_slice)
    _opponent_countries_faced: set[str] = field(default_factory=set)
    _opponent_countries_beaten: set[str] = field(default_factory=set)
    _opponent_countries_beaten_by: set[str] = field(default_factory=set)
    _opponents: set[int] = field(default_factory=set)
    _victims: set[int] = field(default_factory=set)
    _dd_victims: set[int] = field(default_factory=set)
    _cs_victims: set[int] = field(default_factory=set)
    _culprits: set[int] = field(default_factory=set)
    _dd_culprits: set[int] = field(default_factory=set)
    _cs_culprits: set[int] = field(default_factory=set)

    @classmethod
    def from_totals_row(cls, row: dict[str, Any] | None) -> WorldCupSliceTracker:
        base = slice_from_totals_row(row) if row else empty_world_cup_slice()
        return cls(row=base)

    def apply_perspective(
        self,
        *,
        opponent_id: int,
        opponent_country: Any,
        goals_for: int,
        goals_against: int,
        actual_score: float,
        dd_for: bool,
    ) -> None:
        won = actual_score == 1.0
        drew = actual_score == 0.5
        lost = actual_score == 0.0
        margin = abs(goals_for - goals_against)
        sum_of_goals = goals_for + goals_against

        self._opponents.add(opponent_id)
        if won:
            self._victims.add(opponent_id)
        if lost:
            self._culprits.add(opponent_id)
        opp_country = normalize_country(opponent_country)
        if opp_country:
            self._opponent_countries_faced.add(opp_country)
            if won:
                self._opponent_countries_beaten.add(opp_country)
            if lost:
                self._opponent_countries_beaten_by.add(opp_country)

        if dd_for:
            self.row["double_digits"] = int(self.row["double_digits"]) + 1
            self._dd_victims.add(opponent_id)
        if goals_against >= 10:
            self.row["double_digits_conceded"] = int(self.row["double_digits_conceded"]) + 1
            self._dd_culprits.add(opponent_id)
        if goals_against == 0:
            self.row["clean_sheets"] = int(self.row["clean_sheets"]) + 1
            self._cs_victims.add(opponent_id)
        if goals_for == 0:
            self.row["clean_sheets_conceded"] = int(self.row["clean_sheets_conceded"]) + 1
            self._cs_culprits.add(opponent_id)

        if goals_for >= 1 and goals_for > int(self.row["most_goals_scored"]):
            self.row["most_goals_scored"] = goals_for
        if goals_against > int(self.row["most_goals_conceded"]):
            self.row["most_goals_conceded"] = goals_against
        if won and margin > int(self.row["biggest_win_difference"]):
            self.row["biggest_win_difference"] = margin
        if lost and margin > int(self.row["biggest_loss_difference"]):
            self.row["biggest_loss_difference"] = margin
        if drew and sum_of_goals > int(self.row["biggest_draw_sum"]):
            self.row["biggest_draw_sum"] = sum_of_goals
        if sum_of_goals > int(self.row["biggest_sum_of_goals"]):
            self.row["biggest_sum_of_goals"] = sum_of_goals

    def flush_v2_into(self, target: dict[str, Any]) -> None:
        self._sync_network_geo_counts()
        self._recompute_ratios()
        for key in V2_SCALAR_KEYS:
            target[key] = self.row[key]

    def _sync_network_geo_counts(self) -> None:
        self.row["opponent_countries_faced"] = len(self._opponent_countries_faced)
        self.row["opponent_countries_beaten"] = len(self._opponent_countries_beaten)
        self.row["opponent_countries_beaten_by"] = len(self._opponent_countries_beaten_by)
        self.row["different_opponents"] = len(self._opponents)
        self.row["different_victims"] = len(self._victims)
        self.row["double_digits_victims"] = len(self._dd_victims)
        self.row["clean_sheets_victims"] = len(self._cs_victims)
        self.row["different_culprits"] = len(self._culprits)
        self.row["double_digits_culprits"] = len(self._dd_culprits)
        self.row["clean_sheets_culprits"] = len(self._cs_culprits)

    def _recompute_ratios(self) -> None:
        games = int(self.row.get("games") or 0)
        gf = int(self.row.get("goals_for") or 0)
        ga = int(self.row.get("goals_against") or 0)
        self.row["goal_ratio"] = _goal_ratio(gf, ga)
        dd = int(self.row.get("double_digits") or 0)
        cs = int(self.row.get("clean_sheets") or 0)
        ddc = int(self.row.get("double_digits_conceded") or 0)
        csc = int(self.row.get("clean_sheets_conceded") or 0)
        self.row["double_digits_ratio"] = _ratio_db(dd, games)
        self.row["clean_sheets_ratio"] = _ratio_db(cs, games)
        self.row["double_digits_conceded_ratio"] = _ratio_db(ddc, games)
        self.row["clean_sheets_conceded_ratio"] = _ratio_db(csc, games)

    def v2_oracle_values(self) -> dict[str, Any]:
        self._sync_network_geo_counts()
        self._recompute_ratios()
        return {key: self.row[key] for key in V2_SCALAR_KEYS}


def _ensure_tracker(
    pid: int,
    slice_accum: dict[int, dict[str, Any]],
    slice_trackers: dict[int, WorldCupSliceTracker],
    player_countries: dict[int, str | None],
) -> WorldCupSliceTracker:
    if pid not in slice_trackers:
        slice_trackers[pid] = WorldCupSliceTracker.from_totals_row(slice_accum.get(pid))
    return slice_trackers[pid]


def apply_world_cup_tournament_games(
    slice_accum: dict[int, dict[str, Any]],
    slice_trackers: dict[int, WorldCupSliceTracker],
    games: list[dict[str, Any]],
    player_countries: dict[int, str | None],
    participating_pids: set[int],
) -> None:
    game_pids = {int(g["idA"]) for g in games} | {int(g["idB"]) for g in games}
    prep_pids = participating_pids | game_pids
    for pid in prep_pids:
        tracker = _ensure_tracker(pid, slice_accum, slice_trackers, player_countries)
        if pid in slice_accum:
            tracker.row = slice_from_totals_row(slice_accum[pid])

    for game in games:
        id_a = int(game["idA"])
        id_b = int(game["idB"])
        goals_a = int(game["GoalsA"])
        goals_b = int(game["GoalsB"])
        outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
        score_b = 1.0 - outcome.actual_score if outcome.actual_score != 0.5 else 0.5

        tracker_a = _ensure_tracker(id_a, slice_accum, slice_trackers, player_countries)
        tracker_b = _ensure_tracker(id_b, slice_accum, slice_trackers, player_countries)

        tracker_a.apply_perspective(
            opponent_id=id_b,
            opponent_country=player_countries.get(id_b),
            goals_for=goals_a,
            goals_against=goals_b,
            actual_score=outcome.actual_score,
            dd_for=bool(outcome.dd_player_a),
        )
        tracker_b.apply_perspective(
            opponent_id=id_a,
            opponent_country=player_countries.get(id_a),
            goals_for=goals_b,
            goals_against=goals_a,
            actual_score=score_b,
            dd_for=bool(outcome.dd_player_b),
        )

    touched = participating_pids | {int(g["idA"]) for g in games} | {int(g["idB"]) for g in games}
    for pid in touched:
        if pid in slice_accum and pid in slice_trackers:
            slice_trackers[pid].flush_v2_into(slice_accum[pid])


def build_v2_oracle_for_player(
    v1_row: dict[str, Any],
    games: list[dict[str, Any]],
    player_countries: dict[int, str | None],
    player_id: int,
) -> dict[str, Any]:
    """Rebuild V2 fields for one player from ordered WC games (verify oracle)."""
    tracker = WorldCupSliceTracker.from_totals_row(v1_row)
    empty = empty_world_cup_slice()
    for key in V2_SCALAR_KEYS:
        tracker.row[key] = empty[key]
    for game in games:
        id_a = int(game["idA"])
        id_b = int(game["idB"])
        if player_id not in (id_a, id_b):
            continue
        goals_a = int(game["GoalsA"])
        goals_b = int(game["GoalsB"])
        outcome = outcome_from_goals(goals_a, goals_b, id_a, id_b)
        if player_id == id_a:
            tracker.apply_perspective(
                opponent_id=id_b,
                opponent_country=player_countries.get(id_b),
                goals_for=goals_a,
                goals_against=goals_b,
                actual_score=outcome.actual_score,
                dd_for=bool(outcome.dd_player_a),
            )
        else:
            score_b = 1.0 - outcome.actual_score if outcome.actual_score != 0.5 else 0.5
            tracker.apply_perspective(
                opponent_id=id_a,
                opponent_country=player_countries.get(id_a),
                goals_for=goals_b,
                goals_against=goals_a,
                actual_score=score_b,
                dd_for=bool(outcome.dd_player_b),
            )
    return tracker.v2_oracle_values()
