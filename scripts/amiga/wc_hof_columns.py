"""Column manifest for the Amiga World Cup Hall of Fame store (SCH-046).

Single source of truth for:
  - DDL ``scripts/amiga/sql/derived/046_wc_hof.sql``
    (``amiga_wc_hof_snapshots`` + ``amiga_wc_hof_present``)
  - WC HoF compute / persist (WCH-3)
  - ``verify-wc-hof`` oracles (WCH-4)
  - PHP read lib column list (WCH-6)

Policy:  docs/amiga-wc-hof-policy.md  (§4 record register, 28 rows)
Plan:    docs/amiga-wc-hof-implementation-plan.md  (SCH-046)

Storage decisions (locked):
  - ID1: HoF ``{Prefix}Date`` is DERIVED at compute time from the holder's
    ``amiga_player_slice_at_event`` history; no per-metric rise columns are added.
  - ID2: snapshot persist is idempotent UPSERT keyed by ``tournament_id``; a full
    replay rebuilds every WC snapshot. No incremental forward-chain.
"""

from __future__ import annotations

from dataclasses import dataclass

# Re-export the 6 slice extension columns so the manifest test can cross-check them.
from scripts.amiga.slice_columns import SLICE_STAT_COLUMNS_WC_HOF  # noqa: F401

# Ratio / average / rate rows require this many WC slice games at cutoff (WCH6).
WC_ESTABLISHED_MIN_GAMES = 20

# --- spec enums -----------------------------------------------------------

SORT_HIGHER = "higher"
SORT_LOWER = "lower"

HOLDER_SINGLE = "single"   # one player: {Prefix}ID / {Prefix}Name
HOLDER_PAIR = "pair"       # draw-style pair: {Prefix}IDA/IDB + NameA/NameB

ANCHOR_NONE = "none"
ANCHOR_GAME = "game"             # adds {Prefix}GameID
ANCHOR_TOURNAMENT = "tournament" # adds {Prefix}TournamentID

DATE_RISE = "rise"             # last strict increase on the slice timeline (ID1)
DATE_GAME = "game"             # event date of the anchored game
DATE_TOURNAMENT = "tournament" # event date of the anchored World Cup


@dataclass(frozen=True)
class WcHofRecordSpec:
    """One HoF record group (policy §4)."""

    prefix: str
    value_sql_type: str
    sort: str
    min_games: int
    holder: str
    anchor: str
    date_kind: str
    section: str
    source_hint: str


# Register order mirrors policy §4 (honours -> results -> goals -> DD/CS ->
# network -> single-game -> awards -> single-WC peaks).
WC_HOF_RECORD_SPECS: tuple[WcHofRecordSpec, ...] = (
    # 4.1 Honours and volume (cumulative WC slice)
    WcHofRecordSpec("MostWcPlayed", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.1", "tournaments_played"),
    WcHofRecordSpec("MostWcGold", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.1", "gold"),
    WcHofRecordSpec("MostWcGames", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.1", "games"),
    WcHofRecordSpec("MostWcWins", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.1", "wins"),
    WcHofRecordSpec("MostWcPoints", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.1", "points"),
    # 4.2 Results quality (ratio, 20-game gate)
    WcHofRecordSpec("BestWcPtsPerGame", "decimal(6,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.2", "points/games"),
    WcHofRecordSpec("BestWcWinRate", "decimal(5,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.2", "win_rate"),
    # 4.3 Goals (career WC numerators on slice)
    WcHofRecordSpec("MostWcGoalsFor", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.3", "goals_for"),
    WcHofRecordSpec("BestWcGoalsForPerGame", "decimal(6,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.3", "goals_for/games"),
    WcHofRecordSpec("BestWcGoalsAgainstPerGame", "decimal(6,4)", SORT_LOWER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.3", "goals_against/games"),
    WcHofRecordSpec("BestWcGoalDiffPerGame", "decimal(7,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.3", "(goals_for-goals_against)/games"),
    WcHofRecordSpec("BestWcGoalRatio", "decimal(7,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.3", "goal_ratio"),
    # 4.4 Double digits and clean sheets
    WcHofRecordSpec("MostWcDoubleDigits", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.4", "double_digits"),
    WcHofRecordSpec("BestWcDoubleDigitsRatio", "decimal(5,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.4", "double_digits_ratio"),
    WcHofRecordSpec("MostWcCleanSheets", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.4", "clean_sheets"),
    WcHofRecordSpec("BestWcCleanSheetsRatio", "decimal(5,4)", SORT_HIGHER, WC_ESTABLISHED_MIN_GAMES, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.4", "clean_sheets_ratio"),
    # 4.5 Opponent network (WC slice V2)
    WcHofRecordSpec("MostWcOpponents", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.5", "different_opponents"),
    WcHofRecordSpec("MostWcVictims", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.5", "different_victims"),
    WcHofRecordSpec("MostWcDoubleDigitsVictims", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.5", "double_digits_victims"),
    WcHofRecordSpec("MostWcCleanSheetsVictims", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.5", "clean_sheets_victims"),
    # 4.6 Single-game extremes (WC rated games only)
    WcHofRecordSpec("MostWcGoalsInOneGame", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_GAME, DATE_GAME, "4.6", "most_goals_scored"),
    WcHofRecordSpec("BiggestWcWinDifference", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_GAME, DATE_GAME, "4.6", "biggest_win_difference"),
    WcHofRecordSpec("BiggestWcDrawSum", "int(11)", SORT_HIGHER, 0, HOLDER_PAIR, ANCHOR_GAME, DATE_GAME, "4.6", "biggest_draw_sum"),
    WcHofRecordSpec("BiggestWcSumOfGoals", "int(11)", SORT_HIGHER, 0, HOLDER_PAIR, ANCHOR_GAME, DATE_GAME, "4.6", "biggest_sum_of_goals"),
    # 4.7 Per-WC category awards (new cumulative counters)
    WcHofRecordSpec("MostWcBestAttackAwards", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.7", "best_attack_awards"),
    WcHofRecordSpec("MostWcBestDefenseAwards", "int(11)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_NONE, DATE_RISE, "4.7", "best_defense_awards"),
    # 4.8 Single-World-Cup peaks (best one tournament)
    WcHofRecordSpec("BestSingleWcGoalsForPerGame", "decimal(6,4)", SORT_HIGHER, 0, HOLDER_SINGLE, ANCHOR_TOURNAMENT, DATE_TOURNAMENT, "4.8", "best_single_wc_gf_per_game"),
    WcHofRecordSpec("BestSingleWcGoalsAgainstPerGame", "decimal(6,4)", SORT_LOWER, 0, HOLDER_SINGLE, ANCHOR_TOURNAMENT, DATE_TOURNAMENT, "4.8", "best_single_wc_ga_per_game"),
)


def _holder_id_columns(spec: WcHofRecordSpec) -> tuple[str, ...]:
    if spec.holder == HOLDER_PAIR:
        return (f"{spec.prefix}IDA", f"{spec.prefix}IDB")
    return (f"{spec.prefix}ID",)


def _holder_name_columns(spec: WcHofRecordSpec) -> tuple[str, ...]:
    if spec.holder == HOLDER_PAIR:
        return (f"{spec.prefix}NameA", f"{spec.prefix}NameB")
    return (f"{spec.prefix}Name",)


WC_HOF_VALUE_COLUMNS: tuple[str, ...] = tuple(s.prefix for s in WC_HOF_RECORD_SPECS)

WC_HOF_HOLDER_ID_COLUMNS: tuple[str, ...] = tuple(
    col for s in WC_HOF_RECORD_SPECS for col in _holder_id_columns(s)
)

WC_HOF_HOLDER_NAME_COLUMNS: tuple[str, ...] = tuple(
    col for s in WC_HOF_RECORD_SPECS for col in _holder_name_columns(s)
)

WC_HOF_DATE_COLUMNS: tuple[str, ...] = tuple(f"{s.prefix}Date" for s in WC_HOF_RECORD_SPECS)

WC_HOF_GAME_ID_COLUMNS: tuple[str, ...] = tuple(
    f"{s.prefix}GameID" for s in WC_HOF_RECORD_SPECS if s.anchor == ANCHOR_GAME
)

WC_HOF_TOURNAMENT_ID_COLUMNS: tuple[str, ...] = tuple(
    f"{s.prefix}TournamentID" for s in WC_HOF_RECORD_SPECS if s.anchor == ANCHOR_TOURNAMENT
)

# Full holder payload (shared by snapshots + present), grouped by kind.
WC_HOF_PAYLOAD_COLUMNS: tuple[str, ...] = (
    WC_HOF_VALUE_COLUMNS
    + WC_HOF_HOLDER_ID_COLUMNS
    + WC_HOF_HOLDER_NAME_COLUMNS
    + WC_HOF_DATE_COLUMNS
    + WC_HOF_GAME_ID_COLUMNS
    + WC_HOF_TOURNAMENT_ID_COLUMNS
)

WC_HOF_SNAPSHOT_KEY_COLUMNS: tuple[str, ...] = (
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "finalized_at",
)

WC_HOF_SNAPSHOT_COLUMNS: tuple[str, ...] = (
    WC_HOF_SNAPSHOT_KEY_COLUMNS + WC_HOF_PAYLOAD_COLUMNS
)

# Present projection: id=1 mirror of the latest snapshot payload (policy option A).
WC_HOF_PRESENT_COLUMNS: tuple[str, ...] = ("id",) + WC_HOF_PAYLOAD_COLUMNS

WC_HOF_SPEC_BY_PREFIX: dict[str, WcHofRecordSpec] = {s.prefix: s for s in WC_HOF_RECORD_SPECS}


def wc_hof_payload_column_sql_types() -> dict[str, str]:
    """Map each payload column to its DDL type (DDL <-> manifest cross-check)."""
    types: dict[str, str] = {}
    for spec in WC_HOF_RECORD_SPECS:
        types[spec.prefix] = spec.value_sql_type
        for col in _holder_id_columns(spec):
            types[col] = "int(11)"
        for col in _holder_name_columns(spec):
            types[col] = "varchar(50)"
        types[f"{spec.prefix}Date"] = "date"
        if spec.anchor == ANCHOR_GAME:
            types[f"{spec.prefix}GameID"] = "int(11)"
        if spec.anchor == ANCHOR_TOURNAMENT:
            types[f"{spec.prefix}TournamentID"] = "int(11)"
    return types