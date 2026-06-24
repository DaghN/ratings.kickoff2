<?php
/**
 * World Cup country slice roll-ups + finalize rebuild (mirrors country_slice_compute.py).
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_slice_totals_lib.php';
require_once __DIR__ . '/amiga_country_slice_game_stats_lib.php';
require_once __DIR__ . '/amiga_country_slice_persist_lib.php';

const AMIGA_COUNTRY_SLICE_WC_NAME_RE = '^World Cup[[:space:]]+[^[:space:]]';

/** @var list<string> */
const AMIGA_COUNTRY_SLICE_SUM_PLAYER_COLS = [
    'gold',
    'silver',
    'bronze',
    'games',
    'wins',
    'draws',
    'losses',
    'goals_for',
    'goals_against',
    'points',
    'double_digits',
    'clean_sheets',
    'double_digits_conceded',
    'clean_sheets_conceded',
];

/** @var list<string> */
const AMIGA_COUNTRY_SLICE_MAX_PLAYER_COLS = [
    'most_goals_scored',
    'most_goals_conceded',
    'biggest_win_difference',
    'biggest_loss_difference',
    'biggest_sum_of_goals',
    'biggest_draw_sum',
];

function amiga_country_slice_token_sql(string $playerAlias = 'p'): string
{
    return "CASE WHEN TRIM({$playerAlias}.country) IS NULL OR TRIM({$playerAlias}.country) = '' "
        . "THEN '" . AMIGA_COUNTRY_UNKNOWN_TOKEN . "' ELSE TRIM({$playerAlias}.country) END";
}

/**
 * @return array<string, array<string, mixed>>
 */
function amiga_country_slice_load_player_rollups(mysqli $con): array
{
    $tokenSql = amiga_country_slice_token_sql('p');
    $sumCols = [];
    foreach (AMIGA_COUNTRY_SLICE_SUM_PLAYER_COLS as $col) {
        $sumCols[] = "COALESCE(SUM(s.{$col}), 0) AS {$col}";
    }
    $maxCols = [];
    foreach (AMIGA_COUNTRY_SLICE_MAX_PLAYER_COLS as $col) {
        $maxCols[] = "COALESCE(MAX(s.{$col}), 0) AS {$col}";
    }
    $sql = 'SELECT ' . $tokenSql . ' AS country_token, '
        . 'COUNT(DISTINCT s.player_id) AS players, '
        . implode(', ', $sumCols) . ', '
        . implode(', ', $maxCols) . ' '
        . 'FROM amiga_player_slice_totals s '
        . 'INNER JOIN amiga_players p ON p.id = s.player_id '
        . 'WHERE s.slice_key = ? AND s.tournaments_played >= 1 '
        . 'GROUP BY country_token';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare country slice rollups: ' . $con->error);
    }
    $sliceKey = amiga_slice_key_world_cup();
    $stmt->bind_param('s', $sliceKey);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute country slice rollups: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(string) $row['country_token']] = $row;
    }
    $stmt->close();

    return $out;
}

/**
 * @return array<string, array{int, int}>
 */
function amiga_country_slice_load_participation_counts(
    mysqli $con,
    int $tournamentId,
): array {
    $tokenSql = amiga_country_slice_token_sql('p');
    $sql = 'SELECT ' . $tokenSql . ' AS country_token, '
        . 'COUNT(DISTINCT s.tournament_id) AS tournaments_with_nation, '
        . 'COUNT(*) AS wc_participations '
        . 'FROM amiga_player_event_snapshots s '
        . 'INNER JOIN amiga_players p ON p.id = s.player_id '
        . 'INNER JOIN tournaments t ON t.id = s.tournament_id '
        . 'INNER JOIN tournaments tc ON tc.id = ? '
        . 'WHERE t.name REGEXP ? '
        . 'AND ('
        . '  t.event_date < tc.event_date '
        . '  OR (t.event_date = tc.event_date AND t.chrono < tc.chrono) '
        . '  OR (t.event_date = tc.event_date AND t.chrono = tc.chrono AND t.id <= tc.id)'
        . ') '
        . 'GROUP BY country_token';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare country participation: ' . $con->error);
    }
    $wcRe = AMIGA_COUNTRY_SLICE_WC_NAME_RE;
    $stmt->bind_param('is', $tournamentId, $wcRe);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute country participation: ' . $err);
    }
    $res = $stmt->get_result();
    $out = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $out[(string) $row['country_token']] = [
            'tournaments_with_nation' => (int) ($row['tournaments_with_nation'] ?? 0),
            'wc_participations' => (int) ($row['wc_participations'] ?? 0),
        ];
    }
    $stmt->close();

    return $out;
}

/**
 * @return array{realm_wc_tournament_count: int, realm_wc_player_games: int, realm_wc_goals_for: int}
 */
function amiga_country_slice_load_realm_scalars(mysqli $con, int $tournamentId): array
{
    $cutoff = 'INNER JOIN tournaments tc ON tc.id = ? '
        . 'WHERE t.name REGEXP ? '
        . 'AND ('
        . '  t.event_date < tc.event_date '
        . '  OR (t.event_date = tc.event_date AND t.chrono < tc.chrono) '
        . '  OR (t.event_date = tc.event_date AND t.chrono = tc.chrono AND t.id <= tc.id)'
        . ')';

    $wcSql = 'SELECT COUNT(DISTINCT t.id) AS realm_wc_tournament_count '
        . 'FROM tournaments t ' . $cutoff;
    $gamesSql = 'SELECT COUNT(*) AS realm_wc_player_games FROM ('
        . 'SELECT g.player_a_id AS player_id FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id ' . $cutoff
        . ' UNION ALL '
        . 'SELECT g.player_b_id AS player_id FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id ' . $cutoff
        . ') sides';
    $goalsSql = 'SELECT COALESCE(SUM(g.goals_a + g.goals_b), 0) AS realm_wc_goals_for '
        . 'FROM amiga_games g INNER JOIN tournaments t ON t.id = g.tournament_id ' . $cutoff;

    $wcRe = AMIGA_COUNTRY_SLICE_WC_NAME_RE;
    $realmWc = 0;
    $playerGames = 0;
    $goalsFor = 0;

    $stmt = $con->prepare($wcSql);
    if ($stmt !== false) {
        $stmt->bind_param('is', $tournamentId, $wcRe);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $realmWc = (int) ($row['realm_wc_tournament_count'] ?? 0);
            }
        }
        $stmt->close();
    }

    $stmt = $con->prepare($gamesSql);
    if ($stmt !== false) {
        $stmt->bind_param('isis', $tournamentId, $wcRe, $tournamentId, $wcRe);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $playerGames = (int) ($row['realm_wc_player_games'] ?? 0);
            }
        }
        $stmt->close();
    }

    $stmt = $con->prepare($goalsSql);
    if ($stmt !== false) {
        $stmt->bind_param('is', $tournamentId, $wcRe);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $goalsFor = (int) ($row['realm_wc_goals_for'] ?? 0);
            }
        }
        $stmt->close();
    }

    return [
        'realm_wc_tournament_count' => $realmWc,
        'realm_wc_player_games' => $playerGames,
        'realm_wc_goals_for' => $goalsFor,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_country_slice_load_wc_games_with_ratings(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'g.goals_a AS GoalsA, g.goals_b AS GoalsB, '
        . 'gr.rating_a, gr.rating_b '
        . 'FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
        . 'INNER JOIN tournaments tc ON tc.id = ? '
        . 'WHERE t.name REGEXP ? '
        . 'AND ('
        . '  t.event_date < tc.event_date '
        . '  OR (t.event_date = tc.event_date AND t.chrono < tc.chrono) '
        . '  OR (t.event_date = tc.event_date AND t.chrono = tc.chrono AND t.id <= tc.id)'
        . ') '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, g.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $wcRe = AMIGA_COUNTRY_SLICE_WC_NAME_RE;
    $stmt->bind_param('is', $tournamentId, $wcRe);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $games = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $games[] = $row;
    }
    $stmt->close();

    return $games;
}

/**
 * @param array<string, AmigaCountryWorldCupSliceTracker> $trackers
 * @return array<string, array<string, mixed>>
 */
function amiga_country_slice_assemble_rows(
    mysqli $con,
    int $tournamentId,
    array $trackers,
): array {
    $rollups = amiga_country_slice_load_player_rollups($con);
    $participation = amiga_country_slice_load_participation_counts($con, $tournamentId);
    $realm = amiga_country_slice_load_realm_scalars($con, $tournamentId);

    $tokens = array_unique(array_merge(array_keys($rollups), array_keys($participation)));
    sort($tokens);

    $out = [];
    foreach ($tokens as $token) {
        $row = amiga_country_slice_empty_world_cup();
        $rollup = $rollups[$token] ?? [];
        $part = $participation[$token] ?? [];

        $row['players'] = (int) ($rollup['players'] ?? 0);
        if ($row['players'] < 1) {
            continue;
        }

        foreach (AMIGA_COUNTRY_SLICE_SUM_PLAYER_COLS as $col) {
            $row[$col] = (int) ($rollup[$col] ?? 0);
        }
        foreach (AMIGA_COUNTRY_SLICE_MAX_PLAYER_COLS as $col) {
            $row[$col] = (int) ($rollup[$col] ?? 0);
        }

        $row['tournaments_with_nation'] = (int) ($part['tournaments_with_nation'] ?? 0);
        $row['wc_participations'] = (int) ($part['wc_participations'] ?? 0);
        $row['realm_wc_tournament_count'] = $realm['realm_wc_tournament_count'];
        $row['realm_wc_player_games'] = $realm['realm_wc_player_games'];
        $row['realm_wc_goals_for'] = $realm['realm_wc_goals_for'];

        if (isset($trackers[$token])) {
            $trackers[$token]->flushInto($row);
        } else {
            amiga_country_slice_finalize_row($row);
        }

        $out[$token] = $row;
    }

    return $out;
}

/**
 * @param array<int, string|null> $playerCountries
 */
function amiga_country_slice_rebuild_at_world_cup_finalize(
    mysqli $con,
    int $tournamentId,
    mixed $eventDate,
    float $eventChrono,
    array $playerCountries,
): int {
    $games = amiga_country_slice_load_wc_games_with_ratings($con, $tournamentId);
    /** @var array<string, AmigaCountryWorldCupSliceTracker> $trackers */
    $trackers = [];
    amiga_country_slice_apply_wc_games($games, $playerCountries, $trackers);
    $rows = amiga_country_slice_assemble_rows($con, $tournamentId, $trackers);

    return amiga_ops_persist_country_slices($con, $tournamentId, $eventDate, $eventChrono, $rows);
}
