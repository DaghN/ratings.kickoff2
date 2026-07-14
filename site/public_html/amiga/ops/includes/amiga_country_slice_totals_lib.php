<?php
/**
 * World Cup country slice row helpers (mirrors scripts/amiga/country_slice_totals.py).
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_player_slice_lib.php';
require_once __DIR__ . '/../includes/amiga_player_geo_year_lib.php';

const AMIGA_COUNTRY_UNKNOWN_TOKEN = 'Unknown';

/** @var list<string> */
const AMIGA_COUNTRY_SLICE_STAT_COLUMNS = [
    'players',
    'wc_participations',
    'wc_participations_per_player',
    'games_per_player',
    'domestic_games',
    'domestic_game_share',
    'international_games',
    'international_game_share',
    'games_share',
    'goals_share',
    'realm_wc_tournament_count',
    'realm_wc_player_games',
    'realm_wc_goals_for',
    'tournaments_with_nation',
    'gold',
    'silver',
    'bronze',
    'podiums',
    'games',
    'wins',
    'draws',
    'losses',
    'points',
    'points_per_realm_wc',
    'win_rate',
    'average_opponent_rating',
    'performance_rating',
    'goals_for',
    'goals_against',
    'goal_ratio',
    'most_goals_scored',
    'most_goals_conceded',
    'biggest_win_difference',
    'biggest_loss_difference',
    'biggest_sum_of_goals',
    'biggest_draw_sum',
    'double_digits',
    'clean_sheets',
    'double_digits_ratio',
    'clean_sheets_ratio',
    'double_digits_conceded',
    'clean_sheets_conceded',
    'double_digits_conceded_ratio',
    'clean_sheets_conceded_ratio',
    'opponent_countries_faced',
    'opponent_countries_beaten',
    'opponent_countries_beaten_by',
    'different_opponents',
    'different_victims',
    'double_digits_victims',
    'clean_sheets_victims',
];

/** @var list<string> */
const AMIGA_COUNTRY_SLICE_NULLABLE_COLUMNS = [
    'wc_participations_per_player',
    'games_per_player',
    'domestic_game_share',
    'international_game_share',
    'games_share',
    'goals_share',
    'points_per_realm_wc',
    'win_rate',
    'average_opponent_rating',
    'performance_rating',
    'goal_ratio',
    'double_digits_ratio',
    'clean_sheets_ratio',
    'double_digits_conceded_ratio',
    'clean_sheets_conceded_ratio',
];

/**
 * @param array<int, string|null> $playerCountries
 */
function amiga_country_token_for_player(array $playerCountries, int $playerId): string
{
    $own = AmigaPlayerGeoYearTracker::normalizeCountry($playerCountries[$playerId] ?? null);

    return $own ?? AMIGA_COUNTRY_UNKNOWN_TOKEN;
}

/**
 * @return array<string, mixed>
 */
function amiga_country_slice_empty_world_cup(): array
{
    $row = ['slice_key' => amiga_slice_key_world_cup()];
    foreach (AMIGA_COUNTRY_SLICE_STAT_COLUMNS as $col) {
        $row[$col] = in_array($col, AMIGA_COUNTRY_SLICE_NULLABLE_COLUMNS, true) ? null : 0;
    }

    return $row;
}

function amiga_country_slice_ratio_db(int $num, int $den, int $precision = 4): ?float
{
    if ($den <= 0) {
        return null;
    }

    return round($num / $den, $precision);
}

function amiga_country_slice_goal_ratio(int $goalsFor, int $goalsAgainst): ?float
{
    if ($goalsAgainst <= 0) {
        return null;
    }

    return round($goalsFor / $goalsAgainst, 8);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_country_slice_finalize_row(array &$row): void
{
    $players = (int) ($row['players'] ?? 0);
    $games = (int) ($row['games'] ?? 0);
    $participations = (int) ($row['wc_participations'] ?? 0);
    $gf = (int) ($row['goals_for'] ?? 0);
    $ga = (int) ($row['goals_against'] ?? 0);
    $wins = (int) ($row['wins'] ?? 0);
    $draws = (int) ($row['draws'] ?? 0);
    $points = (int) ($row['points'] ?? 0);
    $domestic = (int) ($row['domestic_games'] ?? 0);
    $international = (int) ($row['international_games'] ?? 0);
    $realmGames = (int) ($row['realm_wc_player_games'] ?? 0);
    $realmGf = (int) ($row['realm_wc_goals_for'] ?? 0);
    $realmWcs = (int) ($row['realm_wc_tournament_count'] ?? 0);

    $row['podiums'] = (int) ($row['gold'] ?? 0) + (int) ($row['silver'] ?? 0) + (int) ($row['bronze'] ?? 0);
    $row['wc_participations_per_player'] = amiga_country_slice_ratio_db($participations, $players);
    $row['games_per_player'] = amiga_country_slice_ratio_db($games, $players);
    $row['domestic_game_share'] = amiga_country_slice_ratio_db($domestic, $games, 6);
    $row['international_game_share'] = amiga_country_slice_ratio_db($international, $games, 6);
    $row['games_share'] = $realmGames > 0 ? amiga_country_slice_ratio_db($games, $realmGames, 6) : null;
    $row['goals_share'] = $realmGf > 0 ? amiga_country_slice_ratio_db($gf, $realmGf, 6) : null;
    $row['goal_ratio'] = amiga_country_slice_goal_ratio($gf, $ga);
    $row['points_per_realm_wc'] = $realmWcs > 0 ? amiga_country_slice_ratio_db($points, $realmWcs) : null;
    $row['win_rate'] = $games > 0
        ? amiga_country_slice_ratio_db($wins * 2 + $draws, $games * 2, 6)
        : null;

    $dd = (int) ($row['double_digits'] ?? 0);
    $cs = (int) ($row['clean_sheets'] ?? 0);
    $ddc = (int) ($row['double_digits_conceded'] ?? 0);
    $csc = (int) ($row['clean_sheets_conceded'] ?? 0);
    $row['double_digits_ratio'] = amiga_country_slice_ratio_db($dd, $games);
    $row['clean_sheets_ratio'] = amiga_country_slice_ratio_db($cs, $games);
    $row['double_digits_conceded_ratio'] = amiga_country_slice_ratio_db($ddc, $games);
    $row['clean_sheets_conceded_ratio'] = amiga_country_slice_ratio_db($csc, $games);
}
