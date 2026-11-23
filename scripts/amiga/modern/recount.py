"""Independent recount of the stored Amiga numbers: ``python -m scripts.amiga recount``.

Counts again from the ground and compares with what is stored. Ground here means the game results, the
players, the tournaments and the finish places. Finish places are taken as given.

Rules of this file (docs/amiga-recount-policy.md):

* No calculation code of the project is imported, so a mistake there cannot repeat itself here.
  ``test_recount.py`` checks the imports.
* A new stored number gets its recount here, written from the written rule, not from the calculation code.
  A stored column that is neither recounted nor listed in ``NOT_RECOUNTED`` makes the run fail.
* Reads only.
"""

from __future__ import annotations

import datetime as dt
import json
import time
from collections import Counter, defaultdict
from decimal import Decimal
from pathlib import Path
from typing import Any

# --- The written rules --------------------------------------------------------------------------------
START_RATING = 1600.0
ELO_FACTOR = 32
ELO_SCALE = 400
WIN_POINTS, DRAW_POINTS = 3, 1
DOUBLE_DIGITS = 10  # goals by one player in one game
HIGH_SCORING, LOW_SCORING = 10, 3  # goals in one game, both players together
BLOWOUT = 5  # winning margin
PERFECT_MIN_GAMES = 2
PERFORMANCE_MIN_GAMES = 2
RATIO_RECORD_MIN_GAMES = 20
NO_GOAL_RATIO = -1.0  # goal ratio of a player who never conceded
NO_LOSS_RATING = 5000.0  # "lowest rated opponent lost to" of a player who never lost

GROUND_TABLES = {
    "amiga_games", "amiga_players", "amiga_tournament_finish_override", "tournament_entrants",
    "tournament_fixtures", "tournament_format_templates", "tournament_stage_players",
    "tournament_stage_scoring_steps", "tournament_stages", "tournaments",
}
_PLACE = "finish places are taken as given"
_TABLES = "league tables: PHP and Python are compared in verify-php-standings-parity"
_WC_HOF = "World Cup records: verify-wc-hof recounts them itself"
_NO_PAGE = "no page shows it; not kept up for players who did not take part"
_PHASE = "depends on the stage structure, not on results alone"
_TEXT = "time or text, not a number"
_WC_BEST = {
    c: _WC_HOF
    for c in (
        "best_attack_awards", "best_defense_awards", "best_single_wc_gf_per_game",
        "best_single_wc_gf_per_game_tournament_id", "best_single_wc_ga_per_game",
        "best_single_wc_ga_per_game_tournament_id",
    )
}
#: table -> column -> reason ("*" = whole table)
NOT_RECOUNTED: dict[str, dict[str, str]] = {
    "amiga_wc_hof_present": {"*": _WC_HOF},
    "amiga_wc_hof_snapshots": {"*": _WC_HOF},
    "amiga_tournament_standings": {"*": _TABLES},
    "amiga_player_event_snapshots": {
        "event_finish_position": _PLACE, "best_knockout_phase": _PLACE, "finalized_at": _TEXT,
        "LeastGoalsScoredVictims": _NO_PAGE, "LeastGoalsConcededCulprits": _NO_PAGE,
    },
    "amiga_player_current": {
        "last_finalized_at": _TEXT, "LeastGoalsScoredVictims": _NO_PAGE, "LeastGoalsConcededCulprits": _NO_PAGE,
    },
    "amiga_player_slice_totals": dict(_WC_BEST),
    "amiga_player_slice_at_event": dict(_WC_BEST),
    "amiga_realm_snapshots": {"finalized_at": _TEXT},
    "amiga_community_stats_snapshots": {"finalized_at": _TEXT},
    "amiga_world_cup_stats": {
        "knockout_games": _PHASE, "group_games": _PHASE, "host_city": _TEXT, "finalized_at": _TEXT,
    },
    "amiga_tournament_catalog_stats": {
        c: _TABLES for c in ("standing_players", "standing_rows", "league_scopes", "knockout_ties")
    },
}

#: Ratings are stored with six decimals at every event, so a recount with full decimals drifts a little.
_SLACK = {
    **{c: 2e-3 for c in (
        "rating_a", "rating_b", "rating_difference", "adjustment_a", "adjustment_b", "Rating", "rating_before",
        "rating_delta", "rating_after", "PeakRating", "LowestRating", "HighestRatedVictim", "LowestRatedCulprit",
        "AverageOpponentRating", "average_opponent_rating",
    )},
    "expected_score_a": 2e-5, "expected_score_b": 2e-5, "SumOfOpponentsRating": 0.2,
    "performance_rating": 0.05, "career_best_performance_rating": 0.05,
    "DifferentOpponentsAverage": 1e-4,  # averaged to four decimals, stored with five
}


# --- Comparing ----------------------------------------------------------------------------------------
class Compare:
    """Counts compared values, and the places where recount and stored value differ."""

    def __init__(self, cur: Any, database: str) -> None:
        cur.execute(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c, NUMERIC_SCALE AS s, DATA_TYPE AS d, COLUMN_KEY AS k "
            "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s",
            (database,),
        )
        self.stored: dict[str, list[str]] = defaultdict(list)
        self.tolerance: dict[tuple[str, str], float] = {}
        for r in cur.fetchall():
            self.stored[r["t"]].append(r["c"])
            decimals = int(r["s"]) if r["d"] == "decimal" and r["s"] else (6 if r["d"] in ("float", "double") else 0)
            self.tolerance[(r["t"], r["c"])] = (1.01 * 10**-decimals if decimals else 0.0) + _SLACK.get(r["c"], 0.0)
        self.values = 0
        self.different: Counter[str] = Counter()
        self.example: dict[str, dict[str, str]] = {}
        self.covered: dict[str, set[str]] = defaultdict(set)

    def miss(self, table: str, column: str, key: Any, want: Any, got: Any) -> None:
        name = f"{table}.{column}"
        self.different[name] += 1
        self.example.setdefault(name, {"key": str(key), "recount": str(want), "stored": str(got)})

    def value(self, table: str, column: str, key: Any, want: Any, got: Any) -> None:
        self.values += 1
        if want == got:
            return
        if isinstance(want, dt.date) and str(want) == str(got):  # some dates are stored as text
            return
        # An empty cell and a zero mean the same in these tables.
        if isinstance(want, (int, float, type(None))) and isinstance(got, (int, float, Decimal, type(None))):
            if abs(float(want or 0) - float(got or 0)) <= self.tolerance[(table, column)]:
                return
        self.miss(table, column, key, want, got)

    def row(self, table: str, key: Any, want: dict[str, Any], got: dict[str, Any] | None) -> None:
        if got is None:
            self.miss(table, "(row)", key, "a row", "missing")
            return
        self.covered[table].update(want)
        for column, value in want.items():
            self.value(table, column, key, value, got[column])

    def rows(self, table: str, want: dict[Any, dict[str, Any]], got: dict[Any, dict[str, Any]]) -> None:
        for key, values in want.items():
            self.row(table, key, values, got.get(key))
        for key in got.keys() - want.keys():
            self.miss(table, "(row)", key, "no row", "a row")

    def equation(self, table: str, key: Any, results: list[tuple[float, float]], got: Any) -> None:
        """A performance rating is the rating whose expected score equals the score made."""
        self.values += 1
        self.covered[table].add("performance_rating")
        score = sum(s for _, s in results)
        if len(results) < PERFORMANCE_MIN_GAMES or score in (0, len(results)):
            if got is not None:
                self.miss(table, "performance_rating", key, None, got)
        elif got is None:
            self.miss(table, "performance_rating", key, "a rating", None)
        else:
            off = sum(expected(float(got), opp) for opp, _ in results) - score
            slope = sum(e * (1 - e) for e in (expected(float(got), opp) for opp, _ in results)) * 0.0057565
            if abs(off) > max(1e-4, slope * _SLACK["performance_rating"]):
                self.miss(table, "performance_rating", key, f"expected score off by {off:.4f}", got)

    def uncovered(self) -> list[str]:
        out = []
        for table, columns in sorted(self.stored.items()):
            if table in GROUND_TABLES:
                continue
            listed = NOT_RECOUNTED.get(table, {})
            if "*" in listed:
                continue
            for column in columns:
                if column != "id" and column not in self.covered[table] and column not in listed:
                    out.append(f"{table}.{column}")
        return out


def expected(rating: float, opponent: float) -> float:
    return 1.0 / (1.0 + 10 ** ((opponent - rating) / ELO_SCALE))


def performance(results: list[tuple[float, float]]) -> float | None:
    score = sum(s for _, s in results)
    if len(results) < PERFORMANCE_MIN_GAMES or score in (0, len(results)):
        return None
    low, high = -3000.0, 7000.0
    for _ in range(60):
        middle = (low + high) / 2
        if sum(expected(middle, opp) for opp, _ in results) < score:
            low = middle
        else:
            high = middle
    return low


def ratio(part: float, whole: float) -> float | None:
    return part / whole if whole else None


def goal_ratio(scored: int, conceded: int) -> float:
    return scored / conceded if conceded else NO_GOAL_RATIO


def country(value: Any) -> str | None:
    return (str(value).strip() or None) if value is not None else None


# --- Counting -----------------------------------------------------------------------------------------
class Side:
    """One game seen from one player."""

    __slots__ = ("id", "date", "me", "opp", "gf", "ga", "score", "opp_rating", "nation", "opp_nation")

    def __init__(self, game: dict, me: int, opp: int, gf: int, ga: int, opp_rating: float, nation: Any, opp_nation: Any):
        self.id, self.date, self.me, self.opp, self.gf, self.ga = game["id"], game["game_date"], me, opp, gf, ga
        self.score = 1.0 if gf > ga else 0.0 if gf < ga else 0.5
        self.opp_rating, self.nation, self.opp_nation = opp_rating, nation, opp_nation


class Tally:
    """Running totals over a series of games seen from one side (a career, World Cups only, a country)."""

    def __init__(self) -> None:
        self.n: Counter[str] = Counter()
        self.sets: dict[str, set] = defaultdict(set)
        self.best: dict[str, tuple[float, Side]] = {}  # the first game that reached the record
        self.rating_sum = 0.0

    def _record(self, name: str, value: float, side: Side, highest: bool = True) -> None:
        held = self.best.get(name)
        if held is None or (value > held[0] if highest else value < held[0]):
            self.best[name] = (value, side)

    def add(self, s: Side) -> None:
        won, drew, lost = s.score == 1.0, s.score == 0.5, s.score == 0.0
        self.n.update(
            games=1, wins=int(won), draws=int(drew), losses=int(lost), gf=s.gf, ga=s.ga,
            dd=int(s.gf >= DOUBLE_DIGITS), cs=int(s.ga == 0), dd_against=int(s.ga >= DOUBLE_DIGITS), cs_against=int(s.gf == 0),
        )
        self.rating_sum += s.opp_rating
        for name, met in (
            ("opponents", True), ("victims", won), ("culprits", lost), ("dd_victims", s.gf >= DOUBLE_DIGITS),
            ("dd_culprits", s.ga >= DOUBLE_DIGITS), ("cs_victims", s.ga == 0), ("cs_culprits", s.gf == 0),
        ):
            if met:
                self.sets[name].add(s.opp)
        if s.opp_nation:
            self.sets["nations_faced"].add(s.opp_nation)
            if won:
                self.sets["nations_beaten"].add(s.opp_nation)
            if lost:
                self.sets["nations_beaten_by"].add(s.opp_nation)
        if s.gf:
            self._record("most_scored", s.gf, s)
        if s.ga:
            self._record("most_conceded", s.ga, s)
        if s.gf + s.ga:
            self._record("most_goals", s.gf + s.ga, s)
        self._record("least_scored", s.gf, s, highest=False)
        self._record("least_conceded", s.ga, s, highest=False)
        self._record("least_goals", s.gf + s.ga, s, highest=False)
        if won:
            self._record("win", s.gf - s.ga, s)
            self._record("rated_victim", s.opp_rating, s)
        elif lost:
            self._record("loss", s.ga - s.gf, s)
            self._record("rated_culprit", s.opp_rating, s, highest=False)
        elif s.gf:
            self._record("draw", s.gf + s.ga, s)

    def count(self, name: str) -> int:
        return len(self.sets[name])

    def top(self, name: str) -> Any:
        return self.best[name][0] if name in self.best else None

    def game(self, name: str) -> Any:
        return self.best[name][1].id if name in self.best else None

    def against(self, name: str) -> Any:
        return self.best[name][1].opp if name in self.best else None


#: rise date prefix -> career column ("when did this number last go up")
RISES = {
    "tournaments_played": "tournaments_played", "event_gold": "event_gold", "perfect_events": "perfect_events",
    "countries_played_in": "countries_played_in", "opponent_countries_faced": "opponent_countries_faced",
    "opponent_countries_beaten": "opponent_countries_beaten", "number_games": "NumberGames",
    "number_wins": "NumberWins", "goals_for": "GoalsFor", "double_digits": "DoubleDigits",
    "clean_sheets": "CleanSheets", "different_opponents": "DifferentOpponents",
    "different_victims": "DifferentVictims", "double_digits_victims": "DoubleDigitsVictims",
    "clean_sheets_victims": "CleanSheetsVictims", "biggest_rating_ascent": "BiggestRatingAscent",
}
#: stored column -> the player's own record whose opponent is counted
INVERSE = {
    "MostGoalsScoredCulprits": ("mgs_culprits", "most_scored"), "BiggestWinCulprits": ("bw_culprits", "win"),
    "MostGoalsConcededVictims": ("mgc_victims", "most_conceded"), "BiggestLossVictims": ("bl_victims", "loss"),
}


class Career(Tally):
    def __init__(self) -> None:
        super().__init__()
        self.rating = START_RATING
        self.peak: tuple[float, int] | None = None
        self.low: tuple[float, int] | None = None
        self.best_rank: tuple[int, int] | None = None
        self.streak: Counter[str] = Counter()
        self.longest: Counter[str] = Counter()
        self.last: dict[str, Side] = {}
        self.events: Counter[str] = Counter()
        self.hosts: set[str] = set()
        self.year_games: Counter[int] = Counter()
        self.year_events: Counter[int] = Counter()
        self.best_performance: tuple[float, int] | None = None
        self.rise: dict[str, tuple[int, Any]] = {}
        self.values: dict[str, Any] = {}  # career columns after the latest event
        self.last_event: tuple[int, Any] = (0, None)

    def add(self, s: Side) -> None:
        super().add(s)
        won, drew, lost = s.score == 1.0, s.score == 0.5, s.score == 0.0
        for name, goes_on in (
            ("Winning", won), ("Drawing", drew), ("Losing", lost), ("NonWin", not won), ("NonDraw", not drew), ("NonLoss", not lost),
            ("Score", s.gf > 0), ("Merchant", s.gf >= DOUBLE_DIGITS), ("ExactTenGoal", s.gf == 10),
            ("WinMarginOne", won and s.gf - s.ga == 1), ("LossMarginOne", lost and s.ga - s.gf == 1),
        ):
            self.streak[name] = self.streak[name] + 1 if goes_on else 0
            self.longest[name] = max(self.longest[name], self.streak[name])
        self.last["game"] = s
        self.last["win" if won else "draw" if drew else "loss"] = s

    def last_id(self, name: str) -> Any:
        return self.last[name].id if name in self.last else None

    def columns(self) -> dict[str, Any]:
        n, g = self.n, self.n["games"]
        best_year = lambda years: min(years, key=lambda y: (-years[y], y)) if years else None  # noqa: E731
        out = {
            "Display": 1, "Rating": self.rating, "NumberGames": g, "NumberWins": n["wins"], "NumberDraws": n["draws"],
            "NumberLosses": n["losses"], "WinRatio": ratio(n["wins"], g), "DrawRatio": ratio(n["draws"], g),
            "LossRatio": ratio(n["losses"], g), "GoalsFor": n["gf"], "GoalsAgainst": n["ga"],
            "AverageGoalsFor": ratio(n["gf"], g), "AverageGoalsAgainst": ratio(n["ga"], g), "GoalRatio": goal_ratio(n["gf"], n["ga"]),
            "MostGoalsScored": self.top("most_scored"), "LeastGoalsScored": self.top("least_scored"),
            "MostGoalsConceded": self.top("most_conceded"), "LeastGoalsConceded": self.top("least_conceded"),
            "BiggestWinDifference": self.top("win"), "BiggestDrawSum": self.top("draw"), "BiggestLossDifference": self.top("loss"),
            "SmallestSumOfGoals": self.top("least_goals"), "BiggestSumOfGoals": self.top("most_goals"),
            "DoubleDigits": n["dd"], "CleanSheets": n["cs"], "DoubleDigitsConceded": n["dd_against"], "CleanSheetsConceded": n["cs_against"],
            "DoubleDigitsRatio": ratio(n["dd"], g), "CleanSheetsRatio": ratio(n["cs"], g),
            "DoubleDigitsConcededRatio": ratio(n["dd_against"], g), "CleanSheetsConcededRatio": ratio(n["cs_against"], g),
            "DifferentOpponents": self.count("opponents"), "DifferentVictims": self.count("victims"),
            "DoubleDigitsVictims": self.count("dd_victims"), "CleanSheetsVictims": self.count("cs_victims"),
            "DifferentCulprits": self.count("culprits"), "DoubleDigitsCulprits": self.count("dd_culprits"),
            "CleanSheetsCulprits": self.count("cs_culprits"),
            "SumOfOpponentsRating": self.rating_sum, "AverageOpponentRating": ratio(self.rating_sum, g),
            "HighestRatedVictim": self.top("rated_victim"),
            "LowestRatedCulprit": self.top("rated_culprit") or NO_LOSS_RATING,
            "CurrentRatingAscent": None, "BiggestRatingAscent": None, "CurrentRatingDescent": None, "BiggestRatingDescent": None,
            "PeakRating": self.peak[0], "peak_rating_tournament_id": self.peak[1],
            "LowestRating": self.low[0], "lowest_rating_tournament_id": self.low[1],
            "LastGame": self.last["game"].date, "LastGameGameID": self.last_id("game"), "LastWinGameID": self.last_id("win"),
            "LastDrawGameID": self.last_id("draw"), "LastLossGameID": self.last_id("loss"),
            "MostGoalsScoredGameID": self.game("most_scored"), "LeastGoalsScoredGameID": self.game("least_scored"),
            "MostGoalsConcededGameID": self.game("most_conceded"), "LeastGoalsConcededGameID": self.game("least_conceded"),
            "BiggestWinGameID": self.game("win"), "BiggestDrawGameID": self.game("draw"), "BiggestLossGameID": self.game("loss"),
            "SmallestSumOfGoalsGameID": self.game("least_goals"), "BiggestSumOfGoalsGameID": self.game("most_goals"),
            "MostGoalsScoredVictimID": self.against("most_scored"), "LeastGoalsConcededVictimID": self.against("least_conceded"),
            "BiggestWinVictimID": self.against("win"), "MostGoalsConcededCulpritID": self.against("most_conceded"),
            "LeastGoalsScoredCulpritID": self.against("least_scored"), "BiggestLossCulpritID": self.against("loss"),
            "HighestRatedVictimGameID": self.game("rated_victim"), "LowestRatedCulpritGameID": self.game("rated_culprit"),
            "tournaments_played": self.events["played"], "tournaments_won": self.events["gold"], "event_gold": self.events["gold"],
            "event_silver": self.events["silver"], "event_bronze": self.events["bronze"],
            "event_podiums": self.events["gold"] + self.events["silver"] + self.events["bronze"],
            "perfect_events": self.events["perfect"],
            "career_best_performance_rating": self.best_performance[0] if self.best_performance else None,
            "career_best_performance_tournament_id": self.best_performance[1] if self.best_performance else None,
            "peak_year_games_year": best_year(self.year_games), "peak_year_games": max(self.year_games.values(), default=None),
            "peak_year_tournaments_year": best_year(self.year_events), "peak_year_tournaments": max(self.year_events.values(), default=None),
            "countries_played_in": len(self.hosts), "opponent_countries_faced": self.count("nations_faced"),
            "opponent_countries_beaten": self.count("nations_beaten"), "opponent_countries_beaten_by": self.count("nations_beaten_by"),
        }
        for name in ("Winning", "Drawing", "Losing", "NonWin", "NonDraw", "NonLoss"):
            out[f"{name}Streak"], out[f"Longest{name}Streak"] = self.streak[name], self.longest[name]
        for name in ("Score", "Merchant", "ExactTenGoal", "WinMarginOne", "LossMarginOne"):
            out[f"{name}Streak"] = self.streak[name]
        return out

    def rise_columns(self) -> dict[str, Any]:
        out: dict[str, Any] = {}
        for prefix in RISES:
            event = self.rise.get(prefix, (None, None))
            out[f"{prefix}_last_rise_tournament_id"], out[f"{prefix}_last_rise_event_date"] = event
        return out


def world_cup_player_columns(t: Tally, medals: Counter[str]) -> dict[str, Any]:
    n, g = t.n, t.n["games"]
    return {
        "tournaments_played": medals["played"], "gold": medals["gold"], "silver": medals["silver"], "bronze": medals["bronze"],
        "podiums": medals["gold"] + medals["silver"] + medals["bronze"], "games": g, "wins": n["wins"], "draws": n["draws"],
        "losses": n["losses"], "goals_for": n["gf"], "goals_against": n["ga"], "points": WIN_POINTS * n["wins"] + DRAW_POINTS * n["draws"],
        "goal_ratio": goal_ratio(n["gf"], n["ga"]), "most_goals_scored": t.top("most_scored"), "most_goals_conceded": t.top("most_conceded"),
        "biggest_win_difference": t.top("win"), "biggest_loss_difference": t.top("loss"), "biggest_sum_of_goals": t.top("most_goals"),
        "biggest_draw_sum": t.top("draw"), "double_digits": n["dd"], "clean_sheets": n["cs"], "double_digits_ratio": ratio(n["dd"], g),
        "clean_sheets_ratio": ratio(n["cs"], g), "double_digits_conceded": n["dd_against"], "clean_sheets_conceded": n["cs_against"],
        "double_digits_conceded_ratio": ratio(n["dd_against"], g), "clean_sheets_conceded_ratio": ratio(n["cs_against"], g),
        "opponent_countries_faced": t.count("nations_faced"), "opponent_countries_beaten": t.count("nations_beaten"),
        "opponent_countries_beaten_by": t.count("nations_beaten_by"), "different_opponents": t.count("opponents"),
        "different_victims": t.count("victims"), "double_digits_victims": t.count("dd_victims"), "clean_sheets_victims": t.count("cs_victims"),
        "different_culprits": t.count("culprits"), "double_digits_culprits": t.count("dd_culprits"), "clean_sheets_culprits": t.count("cs_culprits"),
    }


class Pair:
    """Everything one player did against one opponent."""

    __slots__ = ("sides",)

    def __init__(self) -> None:
        self.sides: list[Side] = []

    def columns(self) -> dict[str, Any]:
        s = self.sides
        best = lambda values: max(values, default=None)  # noqa: E731
        return {
            "games": len(s), "wins": sum(x.score == 1 for x in s), "draws": sum(x.score == 0.5 for x in s), "losses": sum(x.score == 0 for x in s),
            "goals_for": sum(x.gf for x in s), "goals_against": sum(x.ga for x in s),
            "max_goals_for": max(x.gf for x in s), "max_goals_against": max(x.ga for x in s),
            "min_goals_for": min(x.gf for x in s), "min_goals_against": min(x.ga for x in s),
            "max_win_margin": best(x.gf - x.ga for x in s if x.score == 1), "max_loss_margin": best(x.ga - x.gf for x in s if x.score == 0),
            "max_draw_goals": best(x.gf for x in s if x.score == 0.5),
            "max_goal_sum": max(x.gf + x.ga for x in s), "min_goal_sum": min(x.gf + x.ga for x in s),
            "dd_wins": sum(x.gf >= DOUBLE_DIGITS for x in s), "dd_losses": sum(x.ga >= DOUBLE_DIGITS for x in s),
            "cs_wins": sum(x.ga == 0 for x in s), "cs_losses": sum(x.gf == 0 for x in s),
        }

    def results(self) -> list[tuple[float, float]]:
        return [(x.opp_rating, x.score) for x in self.sides]


class Realm:
    """Numbers of the whole community, in total and per year."""

    def __init__(self) -> None:
        self.n: Counter[str] = Counter()
        self.pairs: set[tuple[int, int]] = set()
        self.hosts: set[str] = set()
        self.facts: Counter[tuple] = Counter()
        self.members: dict[tuple, set] = defaultdict(set)
        self.known: set[int] = set()

    def fact(self, period: str, key: str, slice_type: str, slice_key: str, metric: str, basis: str, value: int = 1) -> None:
        if value:
            self.facts[(period, key, slice_type, slice_key, metric, basis)] += value

    def member(self, who: Any, *fact: str) -> None:
        self.members[fact].add(who)

    def add_game(self, year: str, host: str | None, sides: tuple[Side, Side], world_cup: bool) -> None:
        a, b = sides
        goals, dd, cs = a.gf + a.ga, sum(s.gf >= DOUBLE_DIGITS for s in sides), sum(s.ga == 0 for s in sides)
        pair = (min(a.me, b.me), max(a.me, b.me))
        self.n.update(games=1, draws=int(a.score == 0.5), goals=goals, dd=dd, cs=cs, world_cup=int(world_cup))
        self.pairs.add(pair)
        for metric, value in (
            ("games", 1), ("goals", goals), ("draws", int(a.score == 0.5)), ("double_digits", dd), ("clean_sheets", cs),
            ("high_scoring_games", int(goals >= HIGH_SCORING)), ("low_scoring_games", int(goals <= LOW_SCORING)),
        ):
            self.fact("year", year, "realm", "*", metric, "game", value)
        self.member(pair, "year", year, "realm", "*", "distinct_pairs", "game")
        if world_cup:
            self.fact("year", year, "world_cup", "*", "games", "game")
            self.fact("year", year, "world_cup", "*", "goals", "game", goals)
        if host:
            for period, key in (("year", year), ("all_time", "*")):
                self.fact(period, key, "host_country", host, "games", "game")
                self.fact(period, key, "host_country", host, "goals", "game", goals)
        for s in sides:
            self.member(s.me, "year", year, "realm", "*", "active_players", "game")
            if world_cup:
                self.member(s.me, "year", year, "world_cup", "*", "active_players", "game")
            if s.me not in self.known:
                self.known.add(s.me)
                self.fact("year", year, "realm", "*", "player_debuts", "game")
                if s.nation:
                    self.fact("year", year, "player_nationality", s.nation, "player_debuts", "participant")
            if not s.nation:
                continue
            self.member(s.nation, "year", year, "realm", "*", "distinct_nationalities", "game")
            for period, key in (("year", year), ("all_time", "*")):
                self.fact(period, key, "player_nationality", s.nation, "games", "participant")
                self.fact(period, key, "player_nationality", s.nation, "goals", "participant", s.gf)
                self.member(s.me, period, key, "player_nationality", s.nation, "active_players", "participant")
            if world_cup:
                self.member(s.nation, "year", year, "world_cup", "*", "distinct_nationalities", "game")
                self.member(s.me, "year", year, "player_nationality", s.nation, "wc_active_players", "participant")

    def add_tournament(self, year: str, host: str | None) -> None:
        self.n["tournaments"] += 1
        self.fact("year", year, "realm", "*", "tournaments", "game")
        if host:
            self.hosts.add(host)
            self.member(host, "year", year, "realm", "*", "distinct_host_countries", "game")
            for period, key in (("year", year), ("all_time", "*")):
                self.fact(period, key, "host_country", host, "tournaments", "game")

    def all_facts(self) -> dict[tuple, int]:
        return {**self.facts, **{k: len(v) for k, v in self.members.items()}}

    def headline(self, careers: dict[int, Career]) -> dict[str, Any]:
        n, games, players = self.n, self.n["games"], len(careers)
        decided = games - n["draws"]
        return {
            "NumberOfPlayers": players, "DifferentOpponentsAverage": ratio(sum(c.count("opponents") for c in careers.values()), players),
            "GamesPlayed": games, "GamesPlayedAverage": ratio(2 * games, players), "NumberOfDecidedGames": decided,
            "NumberOfDraws": n["draws"], "DecidedGamesRatio": ratio(decided, games), "DrawsRatio": ratio(n["draws"], games),
            "GoalsScored": n["goals"], "GoalsPerGameAverage": ratio(n["goals"], games), "DoubleDigits": n["dd"], "CleanSheets": n["cs"],
            "DoubleDigitsRatio": ratio(n["dd"], games), "CleanSheetsRatio": ratio(n["cs"], games), "TournamentsFinalized": n["tournaments"],
            "DistinctHostCountries": len(self.hosts), "WcGamesPlayed": n["world_cup"], "DistinctOpponentPairs": len(self.pairs),
            "PlayersDebuted": len(self.known),
        }


#: Hall of Fame: career record -> column (highest value; equal value: lowest player number)
CAREER_RECORDS = {
    "MostGamesPlayed": "NumberGames", "MostWins": "NumberWins", "MostGoalsScored": "GoalsFor", "MostDoubleDigits": "DoubleDigits",
    "MostCleanSheets": "CleanSheets", "MostDifferentOpponents": "DifferentOpponents", "MostDifferentVictims": "DifferentVictims",
    "MostDoubleDigitsVictims": "DoubleDigitsVictims", "MostCleanSheetsVictims": "CleanSheetsVictims",
    "BiggestRatingAscent": "BiggestRatingAscent", "MostGamesInOneYear": "peak_year_games",
    "MostTournamentsInOneYear": "peak_year_tournaments", "MostTournamentsPlayed": "tournaments_played",
    "MostTournamentWins": "event_gold", "MostPerfectEvents": "perfect_events", "MostCountriesPlayedIn": "countries_played_in",
    "MostOpponentCountriesFaced": "opponent_countries_faced", "MostOpponentCountriesBeaten": "opponent_countries_beaten",
}
#: record -> (career column, highest wins)
RATIO_RECORDS = {
    "BiggestWinRatio": ("WinRatio", True), "BiggestGoalsForAverage": ("AverageGoalsFor", True),
    "SmallestGoalsAgainstAverage": ("AverageGoalsAgainst", False), "BiggestGoalRatio": ("GoalRatio", True),
    "BiggestDoubleDigitsRatio": ("DoubleDigitsRatio", True), "BiggestCleanSheetsRatio": ("CleanSheetsRatio", True),
}


class Records:
    """Single-game records of the whole community, in the order the games were played."""

    def __init__(self) -> None:
        self.held: dict[str, tuple[int, Side, Any]] = {}

    def add_game(self, sides: tuple[Side, Side], date: Any) -> None:
        for s in sides:
            self._offer("MostGoalsScoredInOneGame", s.gf, s, date)
            if s.score == 1:
                self._offer("BiggestWinDifference", s.gf - s.ga, s, date)
        if sides[0].score == 0.5 and sides[0].gf:
            self._offer("BiggestDrawSum", sides[0].gf * 2, sides[0], date)
        self._offer("BiggestSumOfGoals", sides[0].gf + sides[0].ga, sides[0], date)

    def _offer(self, name: str, value: int, s: Side, date: Any) -> None:
        held = self.held.get(name)
        if held is None or value > held[0]:  # on a tie the first game keeps the record
            self.held[name] = (value, s, date)

    def columns(self, careers: dict[int, Career], names: dict[int, str]) -> dict[str, Any]:
        out: dict[str, Any] = {}
        for record, (value, s, date) in self.held.items():
            out[record], out[f"{record}Date"], out[f"{record}GameID"] = value, date, s.id
            if record in ("BiggestDrawSum", "BiggestSumOfGoals"):
                out[f"{record}IDA"], out[f"{record}IDB"] = s.me, s.opp
                out[f"{record}NameA"], out[f"{record}NameB"] = names[s.me], names[s.opp]
            else:
                out[f"{record}ID"], out[f"{record}Name"] = s.me, names[s.me]
        for record, column in CAREER_RECORDS.items():
            best = max(((c.values.get(column) or 0, -pid) for pid, c in careers.items()), default=(0, 0))
            value, pid = best[0], -best[1]
            out[record] = value or None
            out[f"{record}ID"] = out[f"{record}Name"] = out[f"{record}Date"] = None
            if value:
                out[f"{record}ID"], out[f"{record}Name"] = pid, names[pid]
                if column.startswith("peak_year"):
                    out[f"{record}Date"] = dt.date(careers[pid].values[f"{column}_year"], 12, 31)
                else:
                    prefix = next(p for p, c in RISES.items() if c == column)
                    out[f"{record}Date"] = careers[pid].rise[prefix][1]
        for record, (column, highest) in RATIO_RECORDS.items():
            pool = [
                ((c.values[column] if highest else -c.values[column]), -pid)
                for pid, c in careers.items()
                if c.n["games"] >= RATIO_RECORD_MIN_GAMES and not (column == "GoalRatio" and c.values[column] == NO_GOAL_RATIO)
            ]
            # Compare at the four decimals that are stored, so that equal stored values are a tie.
            best = max(((round(v, 4), p) for v, p in pool), default=None)
            out[record] = abs(best[0]) if best else None
            out[f"{record}ID"] = -best[1] if best else None
            out[f"{record}Name"] = names[-best[1]] if best else None
        return out


class Country:
    """World Cup numbers of one nation: the games of all its players together."""

    def __init__(self) -> None:
        self.all = Tally()
        self.players: set[int] = set()
        self.entries = 0
        self.world_cups: set[int] = set()
        self.medals: Counter[str] = Counter()
        self.domestic = self.international = 0
        self.results: list[tuple[float, float]] = []

    def columns(self, token: str, realm: Counter[str], nationals: dict[int, Tally]) -> dict[str, Any]:
        n, g = self.all.n, self.all.n["games"]
        top = lambda name: max((nationals[p].top(name) or 0 for p in self.players), default=0)  # noqa: E731
        points = WIN_POINTS * n["wins"] + DRAW_POINTS * n["draws"]
        return {
            "players": len(self.players), "wc_participations": self.entries, "wc_participations_per_player": ratio(self.entries, len(self.players)),
            "games_per_player": ratio(g, len(self.players)), "domestic_games": self.domestic, "domestic_game_share": ratio(self.domestic, g),
            "international_games": self.international, "international_game_share": ratio(self.international, g),
            "games_share": ratio(g, realm["player_games"]), "goals_share": ratio(n["gf"], realm["goals"]),
            "realm_wc_tournament_count": realm["tournaments"], "realm_wc_player_games": realm["player_games"], "realm_wc_goals_for": realm["goals"],
            "tournaments_with_nation": len(self.world_cups), "gold": self.medals["gold"], "silver": self.medals["silver"],
            "bronze": self.medals["bronze"], "podiums": self.medals["gold"] + self.medals["silver"] + self.medals["bronze"],
            "games": g, "wins": n["wins"], "draws": n["draws"], "losses": n["losses"], "points": points,
            "points_per_realm_wc": ratio(points, realm["tournaments"]), "win_rate": ratio(n["wins"] + 0.5 * n["draws"], g),
            "average_opponent_rating": ratio(self.all.rating_sum, g), "goals_for": n["gf"], "goals_against": n["ga"],
            "goal_ratio": goal_ratio(n["gf"], n["ga"]), "most_goals_scored": top("most_scored"), "most_goals_conceded": top("most_conceded"),
            "biggest_win_difference": top("win"), "biggest_loss_difference": top("loss"), "biggest_sum_of_goals": top("most_goals"),
            "biggest_draw_sum": top("draw"), "double_digits": n["dd"], "clean_sheets": n["cs"], "double_digits_ratio": ratio(n["dd"], g),
            "clean_sheets_ratio": ratio(n["cs"], g), "double_digits_conceded": n["dd_against"], "clean_sheets_conceded": n["cs_against"],
            "double_digits_conceded_ratio": ratio(n["dd_against"], g), "clean_sheets_conceded_ratio": ratio(n["cs_against"], g),
            "opponent_countries_faced": self.all.count("nations_faced"), "opponent_countries_beaten": self.all.count("nations_beaten"),
            "opponent_countries_beaten_by": self.all.count("nations_beaten_by"), "different_opponents": self.all.count("opponents"),
            "different_victims": self.all.count("victims"), "double_digits_victims": self.all.count("dd_victims"),
            "clean_sheets_victims": self.all.count("cs_victims"),
        }


def world_cup_page_columns(
    t: dict, games: list[tuple[Side, Side]], places: dict[int, int], newcomers: int, games_in_year: int
) -> dict[str, Any]:
    n: Counter[str] = Counter()
    opponents: dict[int, set[int]] = defaultdict(set)
    played: Counter[int] = Counter()
    nation: dict[int, Any] = {}
    nation_pairs: set[tuple[str, str]] = set()
    records: dict[str, tuple[int, int]] = {}

    def record(name: str, value: int, game_id: int, highest: bool = True) -> None:
        held = records.get(name)
        if held is None or (value > held[0] if highest else value < held[0]):
            records[name] = (value, game_id)

    for sides in games:
        a = sides[0]
        goals, margin = a.gf + a.ga, abs(a.gf - a.ga)
        n.update(
            games=1, draws=int(margin == 0), goals=goals, dd=sum(s.gf >= DOUBLE_DIGITS for s in sides), cs=sum(s.ga == 0 for s in sides),
            high=int(goals >= HIGH_SCORING), low=int(goals <= LOW_SCORING), blowout=int(margin >= BLOWOUT),
            international=int(bool(a.nation and a.opp_nation and a.nation != a.opp_nation)),
        )
        for s in sides:
            played[s.me] += 1
            opponents[s.me].add(s.opp)
            nation[s.me] = s.nation
            record("player", s.gf, s.id)
        if a.nation and a.opp_nation:
            nation_pairs.add((min(a.nation, a.opp_nation), max(a.nation, a.opp_nation)))
        record("most", goals, a.id)
        record("least", goals, a.id, highest=False)
        record("margin", margin, a.id)
        if margin == 0:
            record("draw", goals, a.id)
    g, players, host = n["games"], len(played), country(t["country"])
    guests = sum(1 for c in nation.values() if c and c != host)
    winner = next((p for p, place in places.items() if place == 1), None)
    return {
        "tournament_name": t["name"], "calendar_year": t["event_date"].year, "event_date": t["event_date"], "event_chrono": t["chrono"],
        "host_country": host, "rated_games": g, "decided_games": g - n["draws"], "draws": n["draws"], "goals": n["goals"],
        "double_digit_slots": n["dd"], "clean_sheet_slots": n["cs"], "high_scoring_games": n["high"], "low_scoring_games": n["low"],
        "blowout_games": n["blowout"], "goals_per_game": ratio(n["goals"], g), "draw_rate": ratio(n["draws"], g),
        "decided_rate": ratio(g - n["draws"], g), "double_digit_rate": ratio(n["dd"], g), "clean_sheet_rate": ratio(n["cs"], g),
        "high_scoring_rate": ratio(n["high"], g), "low_scoring_rate": ratio(n["low"], g), "blowout_rate": ratio(n["blowout"], g),
        "distinct_players": players, "distinct_player_nationalities": len({c for c in nation.values() if c}),
        "max_games_one_player": max(played.values()), "first_time_wc_players": newcomers,
        "distinct_opponent_pairs": sum(len(o) for o in opponents.values()) // 2, "avg_games_per_player": ratio(2 * g, players),
        "avg_opponents_per_player": ratio(sum(len(o) for o in opponents.values()), players),
        "distinct_host_country_players": sum(1 for c in nation.values() if c and c == host), "distinct_guest_players": guests,
        "guest_player_share": ratio(guests, players), "distinct_opponent_countries_pairs": len(nation_pairs),
        "international_games": n["international"], "international_game_share": ratio(n["international"], g),
        "highest_goal_sum": records["most"][0], "highest_goal_sum_game_id": records["most"][1],
        "lowest_goal_sum": records["least"][0], "lowest_goal_sum_game_id": records["least"][1],
        "biggest_margin": records["margin"][0], "biggest_margin_game_id": records["margin"][1],
        "highest_scoring_draw_sum": records.get("draw", (None, None))[0], "highest_scoring_draw_game_id": records.get("draw", (None, None))[1],
        "most_goals_one_player_game": records["player"][0], "most_goals_one_player_game_id": records["player"][1],
        **{f"{medal}_player_id": next((p for p, pl in places.items() if pl == i), None) for i, medal in enumerate(("gold", "silver", "bronze"), 1)},
        "champion_game_count": played[winner] if winner else None, "share_of_year_games": ratio(g, games_in_year),
    }


# --- The recount --------------------------------------------------------------------------------------
def recount(conn: Any, database: str) -> dict[str, Any]:
    """Recount everything and compare; ``conn`` must return rows as dictionaries."""
    started = time.monotonic()
    cur = conn.cursor()

    def read(sql: str, *args: Any) -> list[dict[str, Any]]:
        cur.execute(sql, args)
        return list(cur.fetchall())

    check = Compare(cur, database)
    tournaments = {r["id"]: r for r in read("SELECT * FROM tournaments")}
    players = {r["id"]: r for r in read("SELECT id, name, country FROM amiga_players")}
    names = {pid: p["name"] for pid, p in players.items()}
    nation = {pid: country(p["country"]) for pid, p in players.items()}
    games: dict[int, list[dict]] = defaultdict(list)
    for g in read(
        "SELECT id, game_date, player_a_id, player_b_id, tournament_id, goals_a, goals_b FROM amiga_games ORDER BY game_date, id"
    ):
        games[g["tournament_id"]].append(g)
    unfinished = [t for t in games if t is None or not tournaments[t]["rating_finalized"]]
    order = sorted(
        (t for t in games if t not in unfinished),
        key=lambda t: (tournaments[t]["event_date"], tournaments[t]["chrono"], t),
    )
    ratings = {r["game_id"]: r for r in read("SELECT * FROM amiga_game_ratings")}
    snapshots: dict[int, dict[int, dict]] = defaultdict(dict)
    for r in read("SELECT * FROM amiga_player_event_snapshots"):
        snapshots[r["tournament_id"]][r["player_id"]] = r

    careers: dict[int, Career] = {}
    pairs: dict[int, dict[int, Pair]] = defaultdict(lambda: defaultdict(Pair))
    realm, records = Realm(), Records()
    inverse_before: dict[tuple[int, str], int] = {}
    wc_players: dict[int, Tally] = {}
    wc_medals: dict[int, Counter[str]] = defaultdict(Counter)
    wc_rise: dict[int, tuple[int, Any]] = {}
    wc_nations: dict[str, Country] = defaultdict(Country)
    wc_realm: Counter[str] = Counter()
    wc_rows: dict[int, dict[str, Any]] = {}
    year_games: Counter[int] = Counter()
    last_headline: dict[str, Any] = {}
    last_records: dict[str, Any] = {}

    for tid in order:
        t = tournaments[tid]
        date, year, host = t["event_date"], t["event_date"].year, country(t["country"])
        world_cup = t["is_world_cup"] == 1
        identity = {"event_date": date, "event_chrono": t["chrono"]}
        stored_here = snapshots[tid]
        places = {pid: r["event_finish_position"] for pid, r in stored_here.items()}

        # 1. The games. Ratings stand still during a tournament and move at its end.
        before: dict[int, float] = {}
        change: Counter[int] = Counter()
        event: dict[int, Tally] = defaultdict(Tally)
        results: dict[int, list[tuple[float, float]]] = defaultdict(list)
        played: list[tuple[Side, Side]] = []
        for g in games[tid]:
            a, b, ga, gb = g["player_a_id"], g["player_b_id"], g["goals_a"], g["goals_b"]
            for pid in (a, b):
                before.setdefault(pid, careers.setdefault(pid, Career()).rating)
            sides = (
                Side(g, a, b, ga, gb, before[b], nation[a], nation[b]),
                Side(g, b, a, gb, ga, before[a], nation[b], nation[a]),
            )
            played.append(sides)
            chance = expected(before[a], before[b])
            move = ELO_FACTOR * (sides[0].score - chance)
            change[a] += move
            change[b] -= move
            check.row("amiga_game_ratings", g["id"], {
                "game_id": g["id"], "rating_a": before[a], "rating_b": before[b], "rating_difference": before[a] - before[b],
                "expected_score_a": chance, "expected_score_b": 1 - chance, "actual_score": sides[0].score,
                "adjustment_a": move, "adjustment_b": -move, "new_rating_a": None, "new_rating_b": None,
                "sum_of_goals": ga + gb, "goal_difference": abs(ga - gb), "winner_id": a if ga > gb else b if gb > ga else -1,
                "home_win": int(ga > gb), "draw": int(ga == gb), "away_win": int(gb > ga),
                "dd_player_a": int(ga >= DOUBLE_DIGITS), "dd_player_b": int(gb >= DOUBLE_DIGITS),
                "cs_player_a": int(gb == 0), "cs_player_b": int(ga == 0),
            }, ratings.pop(g["id"], None))
            realm.add_game(str(year), host, sides, world_cup)
            records.add_game(sides, date)
            for s in sides:
                careers[s.me].add(s)
                event[s.me].add(s)
                results[s.me].append((s.opp_rating, s.score))
                pairs[s.me][s.opp].sides.append(s)
        realm.add_tournament(str(year), host)
        year_games[year] += len(played)

        # 2. The players of this tournament.
        perfect_here = False
        for pid in before:
            c, e = careers[pid], event[pid].n
            c.rating = before[pid] + change[pid]
            place, perfect = places.get(pid), e["games"] >= PERFECT_MIN_GAMES and e["wins"] == e["games"]
            perfect_here = perfect_here or perfect
            made = performance(results[pid])
            if c.peak is None or c.rating > c.peak[0] + 1e-6:
                c.peak = (c.rating, tid)
            if c.low is None or c.rating < c.low[0] - 1e-6:
                c.low = (c.rating, tid)
            if made is not None and (c.best_performance is None or made > c.best_performance[0]):
                c.best_performance = (made, tid)
            c.events.update(played=1, gold=int(place == 1), silver=int(place == 2), bronze=int(place == 3), perfect=int(perfect))
            c.last_event = (tid, date)
            c.year_games[year] += e["games"]
            c.year_events[year] += 1
            if host:
                c.hosts.add(host)
            old, c.values = c.values, c.columns()
            for prefix, column in RISES.items():
                if (c.values[column] or 0) > (old.get(column) or 0):
                    c.rise[prefix] = (tid, date)
            if world_cup:
                wc_medals[pid].update(played=1, gold=int(place == 1), silver=int(place == 2), bronze=int(place == 3))
                wc_rise[pid] = (tid, date)

        # 3. Numbers that also change for players who were not there: rank, and records others hold against them.
        ladder = sorted(careers, key=lambda p: (-round(careers[p].rating, 6), p))
        rank = {pid: i for i, pid in enumerate(ladder, 1)}
        for pid, c in careers.items():
            if c.best_rank is None or rank[pid] < c.best_rank[0]:
                c.best_rank = (rank[pid], tid)
        check.rows("amiga_player_elo_rank_at_event", {
            pid: {"player_id": pid, "tournament_id": tid, **identity, "elo_rank": rank[pid], "peak_elo_rank": c.best_rank[0], "peak_elo_rank_tournament_id": c.best_rank[1]}
            for pid, c in careers.items()
        }, {r["player_id"]: r for r in read("SELECT * FROM amiga_player_elo_rank_at_event WHERE tournament_id = %s", tid)})
        inverse: dict[str, Counter[int]] = {
            column: Counter(c.against(record) for c in careers.values() if record in c.best) for column, (_, record) in INVERSE.items()
        }
        changed = {}
        for column, (metric, _) in INVERSE.items():
            for pid in careers:
                now = inverse[column][pid]
                if now != inverse_before.get((pid, metric), 0):
                    inverse_before[(pid, metric)] = now
                    changed[(pid, metric)] = {"player_id": pid, "tournament_id": tid, "metric": metric, "value_after": now, **identity}
        check.rows("amiga_player_inverse_count_at_event", changed, {
            (r["player_id"], r["metric"]): r for r in read("SELECT * FROM amiga_player_inverse_count_at_event WHERE tournament_id = %s", tid)
        })

        # 4. Compare the tournament rows.
        want = {}
        for pid, at_start in before.items():
            c, e, place = careers[pid], event[pid].n, places.get(pid)
            want[pid] = {
                "player_id": pid, "tournament_id": tid, **identity, "tournament_name": t["name"], "is_cup": t["is_cup"], "country": t["country"],
                "has_league": t["has_league"], "has_cup": t["has_cup"], "is_world_cup": t["is_world_cup"], "is_winner": int(place == 1),
                "event_points": WIN_POINTS * e["wins"] + DRAW_POINTS * e["draws"], "games": e["games"], "games_in_event": e["games"],
                "wins": e["wins"], "draws": e["draws"], "losses": e["losses"], "goals_for": e["gf"], "goals_against": e["ga"],
                "avg_goals_for": ratio(e["gf"], e["games"]), "avg_goals_against": ratio(e["ga"], e["games"]), "rating_before": at_start,
                "rating_delta": change[pid], "rating_after": c.rating, "performance_rating": performance(results[pid]),
                "is_perfect_event": int(e["games"] >= PERFECT_MIN_GAMES and e["wins"] == e["games"]), "elo_rank": rank[pid],
                "honours_last_event_date": date, "honours_last_tournament_id": tid,
                **c.values, **c.rise_columns(), **{column: inverse[column][pid] for column in INVERSE},
            }
        check.rows("amiga_player_event_snapshots", want, stored_here)
        stored_pairs: dict[tuple[int, int], dict] = {
            (r["player_id"], r["opponent_id"]): r for r in read("SELECT * FROM amiga_player_matchup_at_event WHERE as_of_tournament_id = %s", tid)
        }
        check.rows("amiga_player_matchup_at_event", {
            (pid, opp): {"player_id": pid, "opponent_id": opp, "as_of_tournament_id": tid, **identity, **pair.columns()}
            for pid in before for opp, pair in pairs[pid].items()
        }, stored_pairs)
        for (pid, opp), r in stored_pairs.items():
            check.equation("amiga_player_matchup_at_event", (pid, opp, tid), pairs[pid][opp].results(), r["performance_rating"])
        check.row("amiga_tournament_catalog_stats", tid, {
            "tournament_id": tid, "game_count": len(played), "has_perfect_participant": int(perfect_here),
        }, next(iter(read("SELECT * FROM amiga_tournament_catalog_stats WHERE tournament_id = %s", tid)), None))

        # 5. The community and the Hall of Fame after this tournament.
        about = {"tournament_id": tid, **identity, "tournament_name": t["name"]}
        last_headline = realm.headline(careers)
        check.row("amiga_community_stats_snapshots", tid, {**about, **last_headline},
                  next(iter(read("SELECT * FROM amiga_community_stats_snapshots WHERE tournament_id = %s", tid)), None))
        last_records = records.columns(careers, names)
        check.row("amiga_realm_snapshots", tid, {**about, **last_records},
                  next(iter(read("SELECT * FROM amiga_realm_snapshots WHERE tournament_id = %s", tid)), None))
        fact_columns = ("period_type", "period_key", "slice_type", "slice_key", "metric_key", "count_basis")
        check.rows("amiga_community_stat_facts", {
            k: {"tournament_id": tid, **dict(zip(fact_columns, k)), "value": v} for k, v in realm.all_facts().items()
        }, {tuple(r[c] for c in fact_columns): r for r in read("SELECT * FROM amiga_community_stat_facts WHERE tournament_id = %s", tid)})

        # 6. World Cups.
        if world_cup:
            wc_realm.update(tournaments=1, player_games=2 * len(played), goals=sum(s[0].gf + s[0].ga for s in played))
            newcomers = sum(1 for pid in before if pid not in wc_players)
            for sides in played:
                for s in sides:
                    wc_players.setdefault(s.me, Tally()).add(s)
                    n = wc_nations[s.nation or "Unknown"]
                    n.all.add(s)
                    n.results.append((s.opp_rating, s.score))
                    if (s.nation or "Unknown") == (s.opp_nation or "Unknown"):
                        n.domestic += 1
                    else:
                        n.international += 1
            for pid in before:
                n = wc_nations[nation[pid] or "Unknown"]
                n.players.add(pid)
                n.entries += 1
                n.world_cups.add(tid)
                n.medals.update({m: int(places.get(pid) == i) for i, m in enumerate(("gold", "silver", "bronze"), 1)})
            stored_nations = {r["country_token"]: r for r in read("SELECT * FROM amiga_country_slice_at_event WHERE as_of_tournament_id = %s", tid)}
            check.rows("amiga_country_slice_at_event", {
                token: {"country_token": token, "slice_key": "world_cup", "as_of_tournament_id": tid, **identity, **n.columns(token, wc_realm, wc_players)} for token, n in wc_nations.items()
            }, stored_nations)
            for token, r in stored_nations.items():
                check.equation("amiga_country_slice_at_event", (token, tid), wc_nations[token].results, r["performance_rating"])
            wc_rows[tid] = {"tournament_id": tid, **world_cup_page_columns(t, played, places, newcomers, year_games[year])}
        # Every tournament repeats the World Cup numbers of the players who were there.
        check.rows("amiga_player_slice_at_event", {
            pid: {"player_id": pid, "slice_key": "world_cup", "as_of_tournament_id": tid, **identity,
                  **world_cup_player_columns(wc_players[pid], wc_medals[pid]),
                  "tournaments_played_last_rise_tournament_id": wc_rise[pid][0], "tournaments_played_last_rise_event_date": wc_rise[pid][1]}
            for pid in before if pid in wc_players
        }, {r["player_id"]: r for r in read("SELECT * FROM amiga_player_slice_at_event WHERE as_of_tournament_id = %s", tid)})

    # The tables that show the present.
    for gid in ratings:
        if not unfinished:
            check.miss("amiga_game_ratings", "(row)", gid, "no row", "a row")
    check.rows("amiga_player_current", {
        pid: {
            "player_id": pid, "last_tournament_id": c.last_event[0], "last_event_date": c.last_event[1], "elo_rank": rank[pid], "peak_elo_rank": c.best_rank[0], "peak_elo_rank_tournament_id": c.best_rank[1],
            **c.values, **c.rise_columns(), **{column: inverse_before.get((pid, metric), 0) for column, (metric, _) in INVERSE.items()},
        }
        for pid, c in careers.items()
    }, {r["player_id"]: r for r in read("SELECT * FROM amiga_player_current")})
    stored_pairs = {(r["player_id"], r["opponent_id"]): r for r in read("SELECT * FROM amiga_player_matchup_summary")}
    check.rows("amiga_player_matchup_summary", {
        (pid, opp): {"player_id": pid, "opponent_id": opp, **pair.columns()} for pid, against in pairs.items() for opp, pair in against.items()
    }, stored_pairs)
    for (pid, opp), r in stored_pairs.items():
        check.equation("amiga_player_matchup_summary", (pid, opp), pairs[pid][opp].results(), r["performance_rating"])
    check.rows("amiga_community_stats", {1: last_headline}, {r["id"]: r for r in read("SELECT * FROM amiga_community_stats")})
    check.rows("amiga_generalstats", {1: last_records}, {r["id"]: r for r in read("SELECT * FROM amiga_generalstats")})
    check.rows("amiga_world_cup_stats", wc_rows, {r["tournament_id"]: r for r in read("SELECT * FROM amiga_world_cup_stats")})
    check.rows("amiga_player_slice_totals", {
        pid: {"player_id": pid, "slice_key": "world_cup", **world_cup_player_columns(tally, wc_medals[pid]),
              "tournaments_played_last_rise_tournament_id": wc_rise[pid][0], "tournaments_played_last_rise_event_date": wc_rise[pid][1]}
        for pid, tally in wc_players.items()
    }, {r["player_id"]: r for r in read("SELECT * FROM amiga_player_slice_totals")})
    stored_nations = {r["country_token"]: r for r in read("SELECT * FROM amiga_country_slice_totals")}
    check.rows("amiga_country_slice_totals", {
        token: {"country_token": token, "slice_key": "world_cup", **n.columns(token, wc_realm, wc_players)} for token, n in wc_nations.items()
    }, stored_nations)
    for token, r in stored_nations.items():
        check.equation("amiga_country_slice_totals", token, wc_nations[token].results, r["performance_rating"])

    return {
        "database": database,
        "tournaments": len(order),
        "not_finished": [tournaments[t]["name"] if t else "(games without a tournament)" for t in unfinished],
        "values": check.values,
        "different": dict(check.different.most_common()),
        "examples": check.example,
        "without_recount": check.uncovered(),
        "seconds": round(time.monotonic() - started, 1),
    }


REPORT_PATH = Path(__file__).resolve().parents[3] / "data" / "amiga" / "modern" / "recount-last.json"
MAX_UNFINISHED = 3


def run_recount(database: str | None = None) -> int:
    """Exit code 0 = same, 1 = different or a column without a recount, 2 = nothing to compare."""
    import pymysql
    from pymysql.cursors import DictCursor

    from scripts.amiga.config import load_amiga_db_config
    from scripts.amiga.modern.constants import WORK_DB

    cfg = load_amiga_db_config()
    database = database or WORK_DB
    if not database.startswith("ko2amiga"):
        print(f"Refusing: {database!r} is not an Amiga database")
        return 2
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password, database=database,
        charset="utf8mb4", cursorclass=DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
            cur.execute("SET TRANSACTION READ ONLY")
            cur.execute("START TRANSACTION WITH CONSISTENT SNAPSHOT")
            cur.execute(
                "SELECT COUNT(DISTINCT t.id) AS n FROM tournaments t "
                "INNER JOIN amiga_games g ON g.tournament_id = t.id WHERE t.rating_finalized = 0"
            )
            unfinished = int(cur.fetchone()["n"])
        if unfinished > MAX_UNFINISHED:
            print(f"Nothing to compare: {unfinished} tournaments in {database} are not finished (is a rebuild running?)")
            return 2
        result = recount(conn, database)
    finally:
        conn.close()

    REPORT_PATH.parent.mkdir(parents=True, exist_ok=True)
    REPORT_PATH.write_text(json.dumps(result, indent=2, default=str) + "\n", encoding="utf-8")
    print(f"recount of {database}: {result['tournaments']} tournaments, {result['values']:,} values, {result['seconds']:.0f} s")
    if result["not_finished"]:
        print("  left out, not finished: " + ", ".join(result["not_finished"]))
    for name, count in result["different"].items():
        e = result["examples"][name]
        print(f"  DIFFERENT {name}: {count}, e.g. {e['key']}: recount {e['recount']}, stored {e['stored']}")
    for name in result["without_recount"]:
        print(f"  NO RECOUNT for {name}: add it, or list it with a reason in NOT_RECOUNTED (docs/amiga-recount-policy.md)")
    same = not result["different"] and not result["without_recount"]
    print("RESULT: " + ("SAME" if same else "DIFFERENT" if result["different"] else "COLUMN WITHOUT A RECOUNT"))
    return 0 if same else 1
