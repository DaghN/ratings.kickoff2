"""Column manifests for amiga_generalstats and amiga_realm_snapshots (realm-snapshot policy)."""

from __future__ import annotations

# Realm-wide aggregates (not per-player record holders).
GENERALSTATS_AGGREGATE_COLUMNS: tuple[str, ...] = (
    "NumberOfPlayers",
    "DifferentOpponentsAverage",
    "GamesPlayed",
    "GamesPlayedAverage",
    "NumberOfDecidedGames",
    "NumberOfDraws",
    "DecidedGamesRatio",
    "DrawsRatio",
    "GoalsScored",
    "GoalsPerGameAverage",
    "DoubleDigits",
    "CleanSheets",
    "DoubleDigitsRatio",
    "CleanSheetsRatio",
)

# Career / single-game record holder value columns.
RECORD_HOLDER_VALUE_COLUMNS: tuple[str, ...] = (
    "MostGamesPlayed",
    "MostWins",
    "MostGoalsScored",
    "MostGoalsScoredInOneGame",
    "BiggestWinDifference",
    "BiggestDrawSum",
    "BiggestSumOfGoals",
    "MostDoubleDigits",
    "MostCleanSheets",
    "MostDifferentOpponents",
    "MostDifferentVictims",
    "MostDoubleDigitsVictims",
    "MostCleanSheetsVictims",
    "BiggestRatingAscent",
    "MostGamesInOneYear",
    "MostTournamentsInOneYear",
    "MostTournamentsPlayed",
    "MostTournamentWins",
    "MostPerfectEvents",
    "MostCountriesPlayedIn",
    "MostOpponentCountriesFaced",
    "MostOpponentCountriesBeaten",
)

RECORD_HOLDER_ID_COLUMNS: tuple[str, ...] = (
    "MostGamesPlayedID",
    "MostWinsID",
    "MostGoalsScoredID",
    "MostGoalsScoredInOneGameID",
    "BiggestWinDifferenceID",
    "BiggestDrawSumIDA",
    "BiggestDrawSumIDB",
    "BiggestSumOfGoalsIDA",
    "BiggestSumOfGoalsIDB",
    "MostDoubleDigitsID",
    "MostCleanSheetsID",
    "MostDifferentOpponentsID",
    "MostDifferentVictimsID",
    "MostDoubleDigitsVictimsID",
    "MostCleanSheetsVictimsID",
    "BiggestRatingAscentID",
    "MostGamesInOneYearID",
    "MostTournamentsInOneYearID",
    "MostTournamentsPlayedID",
    "MostTournamentWinsID",
    "MostPerfectEventsID",
    "MostCountriesPlayedInID",
    "MostOpponentCountriesFacedID",
    "MostOpponentCountriesBeatenID",
)

RECORD_HOLDER_NAME_COLUMNS: tuple[str, ...] = (
    "MostGamesPlayedName",
    "MostWinsName",
    "MostGoalsScoredName",
    "MostGoalsScoredInOneGameName",
    "BiggestWinDifferenceName",
    "BiggestDrawSumNameA",
    "BiggestDrawSumNameB",
    "BiggestSumOfGoalsNameA",
    "BiggestSumOfGoalsNameB",
    "MostDoubleDigitsName",
    "MostCleanSheetsName",
    "MostDifferentOpponentsName",
    "MostDifferentVictimsName",
    "MostDoubleDigitsVictimsName",
    "MostCleanSheetsVictimsName",
    "BiggestRatingAscentName",
    "MostGamesInOneYearName",
    "MostTournamentsInOneYearName",
    "MostTournamentsPlayedName",
    "MostTournamentWinsName",
    "MostPerfectEventsName",
    "MostCountriesPlayedInName",
    "MostOpponentCountriesFacedName",
    "MostOpponentCountriesBeatenName",
)

RECORD_HOLDER_DATE_COLUMNS: tuple[str, ...] = (
    "MostGamesPlayedDate",
    "MostWinsDate",
    "MostGoalsScoredDate",
    "MostGoalsScoredInOneGameDate",
    "BiggestWinDifferenceDate",
    "BiggestDrawSumDate",
    "BiggestSumOfGoalsDate",
    "MostDoubleDigitsDate",
    "MostCleanSheetsDate",
    "MostDifferentOpponentsDate",
    "MostDifferentVictimsDate",
    "MostDoubleDigitsVictimsDate",
    "MostCleanSheetsVictimsDate",
    "BiggestRatingAscentDate",
    "MostGamesInOneYearDate",
    "MostTournamentsInOneYearDate",
    "MostTournamentsPlayedDate",
    "MostTournamentWinsDate",
    "MostPerfectEventsDate",
    "MostCountriesPlayedInDate",
    "MostOpponentCountriesFacedDate",
    "MostOpponentCountriesBeatenDate",
)

# Per-player geo/year columns on snapshots + current.
GEO_YEAR_PLAYER_COLUMNS: tuple[str, ...] = (
    "peak_year_games",
    "peak_year_games_year",
    "peak_year_tournaments",
    "peak_year_tournaments_year",
    "countries_played_in",
    "opponent_countries_faced",
    "opponent_countries_beaten",
)

HONOURS_RISE_PLAYER_COLUMNS: tuple[str, ...] = (
    "tournaments_played_last_rise_tournament_id",
    "tournaments_played_last_rise_event_date",
    "event_gold_last_rise_tournament_id",
    "event_gold_last_rise_event_date",
    "perfect_events_last_rise_tournament_id",
    "perfect_events_last_rise_event_date",
)

from scripts.amiga.career_rise import CAREER_RISE_PLAYER_COLUMNS  # noqa: E402

GEO_RISE_PLAYER_COLUMNS: tuple[str, ...] = (
    "countries_played_in_last_rise_tournament_id",
    "countries_played_in_last_rise_event_date",
    "opponent_countries_faced_last_rise_tournament_id",
    "opponent_countries_faced_last_rise_event_date",
    "opponent_countries_beaten_last_rise_tournament_id",
    "opponent_countries_beaten_last_rise_event_date",
)

RECORD_RISE_PLAYER_COLUMNS: tuple[str, ...] = (
    HONOURS_RISE_PLAYER_COLUMNS + GEO_RISE_PLAYER_COLUMNS + CAREER_RISE_PLAYER_COLUMNS
)

RECORD_HOLDER_GAME_ID_COLUMNS: tuple[str, ...] = (
    "MostGoalsScoredInOneGameGameID",
    "BiggestWinDifferenceGameID",
    "BiggestDrawSumGameID",
    "BiggestSumOfGoalsGameID",
)

# Ratio / average leaders (value + holder id + name each).
RATIO_LEADER_VALUE_COLUMNS: tuple[str, ...] = (
    "BiggestWinRatio",
    "BiggestGoalsForAverage",
    "SmallestGoalsAgainstAverage",
    "BiggestGoalRatio",
    "BiggestDoubleDigitsRatio",
    "BiggestCleanSheetsRatio",
)

RATIO_LEADER_ID_COLUMNS: tuple[str, ...] = (
    "BiggestWinRatioID",
    "BiggestGoalsForAverageID",
    "SmallestGoalsAgainstAverageID",
    "BiggestGoalRatioID",
    "BiggestDoubleDigitsRatioID",
    "BiggestCleanSheetsRatioID",
)

RATIO_LEADER_NAME_COLUMNS: tuple[str, ...] = (
    "BiggestWinRatioName",
    "BiggestGoalsForAverageName",
    "SmallestGoalsAgainstAverageName",
    "BiggestGoalRatioName",
    "BiggestDoubleDigitsRatioName",
    "BiggestCleanSheetsRatioName",
)

RATIO_LEADER_COLUMNS: tuple[str, ...] = (
    RATIO_LEADER_VALUE_COLUMNS
    + RATIO_LEADER_ID_COLUMNS
    + RATIO_LEADER_NAME_COLUMNS
)

RECORD_HOLDER_COLUMNS: tuple[str, ...] = (
    RECORD_HOLDER_VALUE_COLUMNS
    + RECORD_HOLDER_ID_COLUMNS
    + RECORD_HOLDER_NAME_COLUMNS
    + RECORD_HOLDER_DATE_COLUMNS
    + RECORD_HOLDER_GAME_ID_COLUMNS
)

# HoF record book only — community headline aggregates live on amiga_community_*.
GENERALSTATS_PAYLOAD_COLUMNS: tuple[str, ...] = (
    RECORD_HOLDER_COLUMNS
    + RATIO_LEADER_COLUMNS
)

REALM_SNAPSHOT_KEY_COLUMNS: tuple[str, ...] = (
    "tournament_id",
    "event_date",
    "event_chrono",
    "tournament_name",
    "finalized_at",
)

# Payload mirrored on amiga_realm_snapshots (= generalstats without id).
REALM_SNAPSHOT_PAYLOAD_COLUMNS: tuple[str, ...] = GENERALSTATS_PAYLOAD_COLUMNS

REALM_SNAPSHOT_COLUMNS: tuple[str, ...] = (
    REALM_SNAPSHOT_KEY_COLUMNS + REALM_SNAPSHOT_PAYLOAD_COLUMNS
)
