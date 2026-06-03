<?php
/**
 * Prepare / zero-derived constants — keep aligned with scripts/ladder/constants.py
 * and docs/work-db-prepare.md §4.
 */
declare(strict_types=1);

const K2_OPS_START_RATING = 1600.0;

const K2_OPS_PROTECTED_BASELINE_DATABASES = ['ko2unity_baseline', 'kooldb2'];

const K2_OPS_PROTECTED_DEV_DATABASE = 'ko2unity_db';

/** @var list<string> */
const K2_OPS_AGGREGATE_TABLES_TRUNCATE = [
    'player_period_games',
    'player_peak_period_games',
    'server_daily_activity',
    'player_period_league',
    'player_matchup_summary',
    'server_period_game_totals',
    'server_period_matchups',
    'player_milestones',
    'player_play_streaks',
    'player_league_award',
    'player_league_totals',
    'player_league_slice_totals',
    'league_period',
];

/** @var list<string> */
const K2_OPS_RATEDRESULTS_CLEAR = [
    'RatingA', 'RatingB', 'RatingDifference', 'ExpectedScoreA', 'ExpectedScoreB',
    'AdjustmentA', 'AdjustmentB', 'NewRatingA', 'NewRatingB', 'ActualScore', 'WinnerID',
    'SumOfGoals', 'GoalDifference', 'HomeWin', 'Draw', 'AwayWin',
    'DDPlayerA', 'DDPlayerB', 'CSPlayerA', 'CSPlayerB',
];

/** @var list<string> */
const K2_OPS_PLAYERTABLE_NULL_ON_RESET = [
    'NumberGames', 'NumberWins', 'NumberDraws', 'NumberLosses', 'WinRatio', 'DrawRatio', 'LossRatio',
    'GoalsFor', 'GoalsAgainst', 'AverageGoalsFor', 'AverageGoalsAgainst', 'GoalRatio',
    'MostGoalsScored', 'MostGoalsConceded', 'BiggestWinDifference', 'BiggestDrawSum', 'BiggestLossDifference',
    'BiggestSumOfGoals', 'DoubleDigits', 'CleanSheets', 'DoubleDigitsConceded', 'CleanSheetsConceded',
    'DoubleDigitsRatio', 'CleanSheetsRatio', 'DoubleDigitsConcededRatio', 'CleanSheetsConcededRatio',
    'DifferentOpponents', 'DifferentVictims', 'DoubleDigitsVictims', 'CleanSheetsVictims',
    'MostGoalsConcededVictims', 'LeastGoalsScoredVictims', 'BiggestLossVictims', 'DifferentCulprits',
    'DoubleDigitsCulprits', 'CleanSheetsCulprits', 'MostGoalsScoredCulprits', 'LeastGoalsConcededCulprits',
    'BiggestWinCulprits', 'SumOfOpponentsRating', 'AverageOpponentRating', 'HighestRatedVictim',
    'CurrentRatingAscent', 'BiggestRatingAscent', 'CurrentRatingDescent', 'BiggestRatingDescent',
    'PeakRating', 'WinningStreak', 'DrawingStreak', 'LosingStreak',
    'NonWinStreak', 'NonDrawStreak', 'NonLossStreak', 'LongestWinningStreak', 'LongestDrawingStreak',
    'LongestLosingStreak', 'LongestNonWinStreak', 'LongestNonDrawStreak', 'LongestNonLossStreak',
    'LastGameGameID', 'LastWinGameID', 'LastDrawGameID', 'LastLossGameID', 'LowestRatingGameID',
    'PeakRatingGameID', 'MostGoalsScoredGameID', 'LeastGoalsScoredGameID', 'MostGoalsConcededGameID',
    'LeastGoalsConcededGameID', 'BiggestWinGameID', 'BiggestDrawGameID', 'BiggestLossGameID',
    'SmallestSumOfGoalsGameID', 'BiggestSumOfGoalsGameID', 'MostGoalsScoredVictimID',
    'LeastGoalsConcededVictimID', 'BiggestWinVictimID', 'MostGoalsConcededCulpritID',
    'LeastGoalsScoredCulpritID', 'BiggestLossCulpritID', 'HighestRatedVictimGameID', 'LowestRatedCulpritGameID',
];

/** NOT NULL columns (SCH-018) — reset to 0, not NULL. */
const K2_OPS_PLAYERTABLE_ZERO_ON_RESET = [
    'ScoreStreak',
    'MerchantStreak',
    'ExactTenGoalStreak',
    'WinMarginOneStreak',
    'LossMarginOneStreak',
];

/** @var array<string, int|float> */
const K2_OPS_PLAYERTABLE_SENTINELS_ON_RESET = [
    'LeastGoalsScored' => 50,
    'LeastGoalsConceded' => 50,
    'SmallestSumOfGoals' => 50,
    'LowestRating' => 5000.00,
    'LowestRatedCulprit' => 5000.00,
];

const K2_OPS_PLAYERTABLE_LASTGAME_RESET = '1970-01-01 00:00:00';

/** entered_arena prepare seed — live-faithful (register = lobby), not NumberGames >= 1. */
const K2_OPS_JOIN_DATE_VALID_WHERE = '`JoinDate` IS NOT NULL AND UNIX_TIMESTAMP(`JoinDate`) > 0';

/** @var list<string> */
const K2_OPS_REQUIRED_RATEDRESULTS_INDEXES = [
    'idx_ratedresults_idA',
    'idx_ratedresults_idB',
    'idx_ratedresults_date',
];

/** @var array<string, array<string, string|int>> */
const K2_OPS_DEFAULT_PROFILES = [
    'local-work' => [
        'work_database' => 'ko2unity_work',
        'baseline_database' => 'ko2unity_baseline',
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
    ],
    'staging-work' => [
        'work_database' => 'kooldb1',
        'baseline_database' => 'kooldb2',
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
    ],
];

const K2_OPS_TIER_BAND_PRODUCT = [
    'aspirational' => 'aspirational',
    'dedicated' => 'veteran',
    'accomplished' => 'key',
    'legendary' => 'legendary',
];
