"""Career cumulative scalar last-rise tracking (SCH-030)."""

from __future__ import annotations

from typing import Any

# (career column on snapshot/current, rise field prefix, HoF generalstats prefix)
CAREER_RISE_SPECS: tuple[tuple[str, str, str], ...] = (
    ("NumberGames", "number_games", "MostGamesPlayed"),
    ("NumberWins", "number_wins", "MostWins"),
    ("GoalsFor", "goals_for", "MostGoalsScored"),
    ("DoubleDigits", "double_digits", "MostDoubleDigits"),
    ("CleanSheets", "clean_sheets", "MostCleanSheets"),
    ("DifferentOpponents", "different_opponents", "MostDifferentOpponents"),
    ("DifferentVictims", "different_victims", "MostDifferentVictims"),
    ("DoubleDigitsVictims", "double_digits_victims", "MostDoubleDigitsVictims"),
    ("CleanSheetsVictims", "clean_sheets_victims", "MostCleanSheetsVictims"),
    ("BiggestRatingAscent", "biggest_rating_ascent", "BiggestRatingAscent"),
)

CAREER_RISE_VALUE_COLUMNS: tuple[str, ...] = tuple(spec[0] for spec in CAREER_RISE_SPECS)

CAREER_RISE_PLAYER_COLUMNS: tuple[str, ...] = tuple(
    col
    for _career, prefix, _hof in CAREER_RISE_SPECS
    for col in (f"{prefix}_last_rise_tournament_id", f"{prefix}_last_rise_event_date")
)

HOF_PREFIX_TO_CAREER_RISE_DATE: dict[str, str] = {
    hof: f"{prefix}_last_rise_event_date" for _career, prefix, hof in CAREER_RISE_SPECS
}


def _empty_rise_fields() -> dict[str, Any]:
    out: dict[str, Any] = {}
    for col in CAREER_RISE_PLAYER_COLUMNS:
        out[col] = None
    return out


def empty_career_rise_state() -> dict[str, Any]:
    return _empty_rise_fields()


def career_rise_from_row(row: dict[str, Any]) -> dict[str, Any]:
    out = _empty_rise_fields()
    for col in CAREER_RISE_PLAYER_COLUMNS:
        if col in row:
            out[col] = row.get(col)
    return out


def prior_career_values_from_row(row: dict[str, Any]) -> dict[str, int | float]:
    out: dict[str, int | float] = {}
    for col in CAREER_RISE_VALUE_COLUMNS:
        if col == "BiggestRatingAscent":
            val = row.get(col)
            out[col] = float(val) if val is not None else 0.0
        else:
            out[col] = int(row.get(col) or 0)
    return out


def _scalar_increased(career_col: str, prior: int | float, new: int | float) -> bool:
    if career_col == "BiggestRatingAscent":
        return float(new) > float(prior)
    return int(new) > int(prior)


def apply_career_rise_fields(
    rise_state: dict[str, Any],
    prior_career: dict[str, int | float],
    new_career: dict[str, Any],
    *,
    tournament_id: int,
    event_date: Any,
) -> dict[str, Any]:
    """Return updated rise fields after one event finalize."""
    out = dict(rise_state)
    for career_col, prefix, _hof in CAREER_RISE_SPECS:
        if career_col == "BiggestRatingAscent":
            old = float(prior_career.get(career_col) or 0.0)
            new = float(new_career.get(career_col) or 0.0)
        else:
            old = int(prior_career.get(career_col) or 0)
            new = int(new_career.get(career_col) or 0)
        if _scalar_increased(career_col, old, new):
            out[f"{prefix}_last_rise_tournament_id"] = int(tournament_id)
            out[f"{prefix}_last_rise_event_date"] = event_date
    return out
