"""Column lists and formula defaults for replay v1 (see docs/replay-v1-scope-and-reset.md)."""

from __future__ import annotations

K_FACTOR = 32
START_RATING = 1600.0
ESTABLISHED_MIN_GAMES = 20
ALLOWED_DATABASES = frozenset(
    {"ko2unity_work", "ko2unity_baseline", "ko2unity_db", "kooldb"}
)

# ratedresults — cleared on reset, rewritten on replay
RATEDRESULTS_CLEAR = (
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
    "WinnerID",
    "SumOfGoals",
    "GoalDifference",
    "HomeWin",
    "Draw",
    "AwayWin",
    "DDPlayerA",
    "DDPlayerB",
    "CSPlayerA",
    "CSPlayerB",
)

# playertable — never touched by reset/replay v1
PLAYERTABLE_PRESERVE_PREFIXES = ("Pref_", "Profile_", "Feedback_")
PLAYERTABLE_PRESERVE_EXACT = frozenset(
    {
        "ID",
        "Name",
        "Email",
        "CryptPassword",
        "GUID",
        "LegalAccepted",
        "JoinDate",
        "LastLogin",
        "LastActive",
        "Country",
        "Language",
        "AvoidRank",
        "Challenge1",
        "Challenge2",
        "NewForumPosts",
        "Pref_UseCustomKits",
        "Pref_KitStyleA",
        "Pref_KitColour1",
        "Pref_KitColour2",
        "Pref_KitColour3",
        "Pref_KitStyleB",
        "Pref_KitColour4",
        "Pref_KitColour5",
        "Display",
        "PlayerRank",
        "IsOnline",
        "IPPort",
        "LobbyTime",
    }
)

# playertable — NULL on reset (rebuilt during replay where noted in manifest)
PLAYERTABLE_NULL_ON_RESET = (
    "NumberGames",
    "NumberWins",
    "NumberDraws",
    "NumberLosses",
    "WinRatio",
    "DrawRatio",
    "LossRatio",
    "GoalsFor",
    "GoalsAgainst",
    "AverageGoalsFor",
    "AverageGoalsAgainst",
    "GoalRatio",
    "MostGoalsScored",
    "MostGoalsConceded",
    "BiggestWinDifference",
    "BiggestDrawSum",
    "BiggestLossDifference",
    "BiggestSumOfGoals",
    "DoubleDigits",
    "CleanSheets",
    "DoubleDigitsConceded",
    "CleanSheetsConceded",
    "DoubleDigitsRatio",
    "CleanSheetsRatio",
    "DoubleDigitsConcededRatio",
    "CleanSheetsConcededRatio",
    "DifferentOpponents",
    "DifferentVictims",
    "DoubleDigitsVictims",
    "CleanSheetsVictims",
    "MostGoalsConcededVictims",
    "LeastGoalsScoredVictims",
    "BiggestLossVictims",
    "DifferentCulprits",
    "DoubleDigitsCulprits",
    "CleanSheetsCulprits",
    "MostGoalsScoredCulprits",
    "LeastGoalsConcededCulprits",
    "BiggestWinCulprits",
    "SumOfOpponentsRating",
    "AverageOpponentRating",
    "HighestRatedVictim",
    "CurrentRatingAscent",
    "BiggestRatingAscent",
    "CurrentRatingDescent",
    "BiggestRatingDescent",
    "PeakRating",
    "WinningStreak",
    "DrawingStreak",
    "LosingStreak",
    "NonWinStreak",
    "NonDrawStreak",
    "NonLossStreak",
    "LongestWinningStreak",
    "LongestDrawingStreak",
    "LongestLosingStreak",
    "LongestNonWinStreak",
    "LongestNonDrawStreak",
    "LongestNonLossStreak",
    "LastGameGameID",
    "LastWinGameID",
    "LastDrawGameID",
    "LastLossGameID",
    "LowestRatingGameID",
    "PeakRatingGameID",
    "MostGoalsScoredGameID",
    "LeastGoalsScoredGameID",
    "MostGoalsConcededGameID",
    "LeastGoalsConcededGameID",
    "BiggestWinGameID",
    "BiggestDrawGameID",
    "BiggestLossGameID",
    "SmallestSumOfGoalsGameID",
    "BiggestSumOfGoalsGameID",
    "MostGoalsScoredVictimID",
    "LeastGoalsConcededVictimID",
    "BiggestWinVictimID",
    "MostGoalsConcededCulpritID",
    "LeastGoalsScoredCulpritID",
    "BiggestLossCulpritID",
    "HighestRatedVictimGameID",
    "LowestRatedCulpritGameID",
)

# NOT NULL milestone facilitators (SCH-018) — zero on reset, not NULL.
PLAYERTABLE_ZERO_ON_RESET = (
    "ScoreStreak",
    "MerchantStreak",
    "ExactTenGoalStreak",
    "WinMarginOneStreak",
    "LossMarginOneStreak",
)

# NOT NULL sentinels on reset (docs/replay-v1-scope-and-reset.md §5.2)
PLAYERTABLE_SENTINELS_ON_RESET = {
    "LeastGoalsScored": 50,
    "LeastGoalsConceded": 50,
    "SmallestSumOfGoals": 50,
    "LowestRating": 5000.00,
    "LowestRatedCulprit": 5000.00,
}

# LastGame is NOT NULL in DB — baseline before first replayed game
PLAYERTABLE_LASTGAME_RESET = "1970-01-01 00:00:00"
