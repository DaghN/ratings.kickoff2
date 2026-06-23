<?php
/**
 * Build and persist per–World Cup event stats rows
 * (mirrors scripts/amiga/world_cup_stats.py + world_cup_stats_columns.py).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_game_metrics.php';
require_once __DIR__ . '/amiga_honours_totals_lib.php';
require_once __DIR__ . '/../../../includes/amiga_participation_placement.php';

function amiga_world_cup_country_token(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim($value);

    return $text !== '' ? $text : null;
}

/**
 * @return list<string>
 */
function amiga_world_cup_stats_column_names(): array
{
    return [
        'tournament_id',
        'tournament_name',
        'calendar_year',
        'event_date',
        'event_chrono',
        'host_country',
        'host_city',
        'rated_games',
        'decided_games',
        'draws',
        'goals',
        'double_digit_slots',
        'clean_sheet_slots',
        'high_scoring_games',
        'low_scoring_games',
        'blowout_games',
        'knockout_games',
        'group_games',
        'goals_per_game',
        'draw_rate',
        'decided_rate',
        'double_digit_rate',
        'clean_sheet_rate',
        'high_scoring_rate',
        'low_scoring_rate',
        'blowout_rate',
        'distinct_players',
        'distinct_player_nationalities',
        'max_games_one_player',
        'first_time_wc_players',
        'distinct_opponent_pairs',
        'avg_games_per_player',
        'avg_opponents_per_player',
        'distinct_host_country_players',
        'distinct_guest_players',
        'guest_player_share',
        'distinct_opponent_countries_pairs',
        'international_games',
        'international_game_share',
        'highest_goal_sum',
        'highest_goal_sum_game_id',
        'lowest_goal_sum',
        'lowest_goal_sum_game_id',
        'biggest_margin',
        'biggest_margin_game_id',
        'highest_scoring_draw_sum',
        'highest_scoring_draw_game_id',
        'most_goals_one_player_game',
        'most_goals_one_player_game_id',
        'gold_player_id',
        'silver_player_id',
        'bronze_player_id',
        'champion_game_count',
        'share_of_year_games',
        'finalized_at',
    ];
}

function amiga_world_cup_host_city_from_name(string $name): ?string
{
    $text = trim($name);
    if ($text === '' || !str_contains($text, '(') || !str_ends_with($text, ')')) {
        return null;
    }
    $inner = trim(substr($text, (int) strrpos($text, '(') + 1, -1));

    return $inner !== '' ? $inner : null;
}

/**
 * @return array{0: ?int, 1: ?int, 2: ?int}
 */
function amiga_world_cup_load_podium_player_ids(mysqli $con, int $tournamentId): array
{
    $sql = "
        SELECT scope_type, scope_key, player_id, position
        FROM amiga_tournament_standings
        WHERE tournament_id = ?
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare world cup podium: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute world cup podium: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    $finish = amiga_participation_compute_wc_podium_finish_from_standings($rows);
    $gold = $silver = $bronze = null;
    $bronzeCandidates = [];
    foreach ($finish as $pid => $place) {
        $place = (int) $place;
        $pid = (int) $pid;
        if ($place === 1) {
            $gold = $pid;
        } elseif ($place === 2) {
            $silver = $pid;
        } elseif ($place === 3) {
            $bronzeCandidates[] = $pid;
        }
    }
    if ($bronzeCandidates !== []) {
        $bronze = min($bronzeCandidates);
    }

    return [$gold, $silver, $bronze];
}

/**
 * @param array<int, true> $playerIds
 */
function amiga_world_cup_count_first_time_wc_players(
    mysqli $con,
    int $tournamentId,
    array $playerIds,
    ?string $eventDate,
    float $chrono,
): int {
    if ($playerIds === []) {
        return 0;
    }

    $ids = array_keys($playerIds);
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $idTypes = str_repeat('i', count($ids));
    $sql = "
        SELECT DISTINCT g.player_a_id AS pid
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE g.player_a_id IN ({$placeholders})
          AND t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
          AND (
            t.event_date < ?
            OR (t.event_date = ? AND (t.chrono < ? OR (t.chrono = ? AND t.id < ?)))
          )
        UNION
        SELECT DISTINCT g.player_b_id AS pid
        FROM amiga_games g
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE g.player_b_id IN ({$placeholders})
          AND t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
          AND (
            t.event_date < ?
            OR (t.event_date = ? AND (t.chrono < ? OR (t.chrono = ? AND t.id < ?)))
          )
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare first-time wc players: ' . $con->error);
    }

    $bindTypes = $idTypes . 'ssddi' . $idTypes . 'ssddi';
    $bindParams = array_merge(
        $ids,
        [$eventDate, $eventDate, $chrono, $chrono, $tournamentId],
        $ids,
        [$eventDate, $eventDate, $chrono, $chrono, $tournamentId],
    );
    $stmt->bind_param($bindTypes, ...$bindParams);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute first-time wc players: ' . $stmt->error);
    }

    /** @var array<int, true> $prior */
    $prior = [];
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $prior[(int) $row['pid']] = true;
    }
    $stmt->close();

    $count = 0;
    foreach ($ids as $pid) {
        if (!isset($prior[$pid])) {
            $count++;
        }
    }

    return $count;
}

function amiga_world_cup_realm_games_in_year(
    mysqli $con,
    ?int $calendarYear,
    ?string $eventDate,
    float $chrono,
    int $tournamentId,
): ?int {
    if ($calendarYear === null) {
        return null;
    }

    $sql = "
        SELECT COUNT(*) AS n
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN tournaments t ON t.id = g.tournament_id
        WHERE YEAR(t.event_date) = ?
          AND (
            t.event_date < ?
            OR (t.event_date = ? AND (t.chrono < ? OR (t.chrono = ? AND t.id <= ?)))
          )
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare realm games in year: ' . $con->error);
    }
    $stmt->bind_param('issddi', $calendarYear, $eventDate, $eventDate, $chrono, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute realm games in year: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row !== null ? (int) ($row['n'] ?? 0) : 0;
}

/**
 * @return array<string, mixed>|null
 */
function amiga_world_cup_build_stats_row(
    mysqli $con,
    int $tournamentId,
    ?string $finalizedAt = null,
): ?array {
    $stmt = $con->prepare(
        'SELECT id, name, event_date, chrono, country, rating_finalized_at
         FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare world cup tournament: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute world cup tournament: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $tour = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($tour === null || !amiga_honours_is_world_cup_tournament((string) ($tour['name'] ?? ''))) {
        return null;
    }

    $eventDate = $tour['event_date'] ?? null;
    $chrono = (float) ($tour['chrono'] ?? 0);
    $calendarYear = null;
    if ($eventDate !== null && $eventDate !== '') {
        $calendarYear = (int) substr((string) $eventDate, 0, 4);
    }
    $hostCountry = amiga_world_cup_country_token(isset($tour['country']) ? (string) $tour['country'] : null);
    $hostCity = amiga_world_cup_host_city_from_name((string) ($tour['name'] ?? ''));

    $gamesSql = "
        SELECT g.id AS game_id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b, g.phase,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals, r.actual_score,
               r.dd_player_a, r.dd_player_b, r.cs_player_a, r.cs_player_b
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE g.tournament_id = ?
        ORDER BY g.game_date ASC, g.id ASC
    ";
    $gamesStmt = $con->prepare($gamesSql);
    if ($gamesStmt === false) {
        throw new RuntimeException('prepare world cup games: ' . $con->error);
    }
    $gamesStmt->bind_param('i', $tournamentId);
    if (!$gamesStmt->execute()) {
        throw new RuntimeException('execute world cup games: ' . $gamesStmt->error);
    }
    $gamesRes = $gamesStmt->get_result();

    $ratedGames = 0;
    $draws = 0;
    $goals = 0;
    $ddSlots = 0;
    $csSlots = 0;
    $highScoring = 0;
    $lowScoring = 0;
    $blowouts = 0;
    $internationalGames = 0;
    $knockoutGames = 0;
    $groupGames = 0;
    /** @var array<int, true> $players */
    $players = [];
    /** @var array<string, true> $nationalities */
    $nationalities = [];
    /** @var array<int, true> $hostPlayers */
    $hostPlayers = [];
    /** @var array<int, true> $guestPlayers */
    $guestPlayers = [];
    /** @var array<string, true> $pairs */
    $pairs = [];
    /** @var array<string, true> $nationalityPairs */
    $nationalityPairs = [];
    /** @var array<int, int> $gamesPerPlayer */
    $gamesPerPlayer = [];
    /** @var array<int, array<int, true>> $opponentsPerPlayer */
    $opponentsPerPlayer = [];

    $highestSum = -1;
    $highestSumGameId = null;
    $lowestSum = null;
    $lowestSumGameId = null;
    $biggestMargin = -1;
    $biggestMarginGameId = null;
    $highestDrawSum = -1;
    $highestDrawGameId = null;
    $mostGoalsOne = -1;
    $mostGoalsOneGameId = null;

    while ($gamesRes && ($row = $gamesRes->fetch_assoc())) {
        $metrics = amiga_community_rated_game_metrics($row);
        $ratedGames++;
        $goals += (int) $metrics['sum_of_goals'];
        $ddSlots += (int) $metrics['dd_slots'];
        $csSlots += (int) $metrics['cs_slots'];

        if ($metrics['is_draw']) {
            $draws++;
            if ((int) $metrics['sum_of_goals'] > $highestDrawSum) {
                $highestDrawSum = (int) $metrics['sum_of_goals'];
                $highestDrawGameId = $metrics['game_id'];
            }
        }
        if ($metrics['is_high_scoring']) {
            $highScoring++;
        }
        if ($metrics['is_low_scoring']) {
            $lowScoring++;
        }
        if ($metrics['is_blowout']) {
            $blowouts++;
        }
        if (amiga_community_is_knockout_phase($metrics['phase'])) {
            $knockoutGames++;
        } else {
            $groupGames++;
        }

        $sumGoals = (int) $metrics['sum_of_goals'];
        if ($sumGoals > $highestSum) {
            $highestSum = $sumGoals;
            $highestSumGameId = $metrics['game_id'];
        }
        if ($lowestSum === null || $sumGoals < $lowestSum) {
            $lowestSum = $sumGoals;
            $lowestSumGameId = $metrics['game_id'];
        }
        $margin = (int) $metrics['margin'];
        if ($margin > $biggestMargin) {
            $biggestMargin = $margin;
            $biggestMarginGameId = $metrics['game_id'];
        }
        $onePlayerMax = max((int) $metrics['goals_a'], (int) $metrics['goals_b']);
        if ($onePlayerMax > $mostGoalsOne) {
            $mostGoalsOne = $onePlayerMax;
            $mostGoalsOneGameId = $metrics['game_id'];
        }

        foreach (
            [
                [(int) $metrics['player_a_id'], (int) $metrics['player_b_id'], amiga_world_cup_country_token((string) ($row['country_a'] ?? ''))],
                [(int) $metrics['player_b_id'], (int) $metrics['player_a_id'], amiga_world_cup_country_token((string) ($row['country_b'] ?? ''))],
            ] as [$pid, $opp, $country]
        ) {
            $players[$pid] = true;
            $gamesPerPlayer[$pid] = ($gamesPerPlayer[$pid] ?? 0) + 1;
            $opponentsPerPlayer[$pid][$opp] = true;
            if ($country !== null) {
                $nationalities[$country] = true;
                if ($hostCountry !== null && $country === $hostCountry) {
                    $hostPlayers[$pid] = true;
                } elseif ($hostCountry !== null) {
                    $guestPlayers[$pid] = true;
                }
            }
        }

        $ca = amiga_world_cup_country_token((string) ($row['country_a'] ?? ''));
        $cb = amiga_world_cup_country_token((string) ($row['country_b'] ?? ''));
        if ($ca !== null && $cb !== null) {
            $sorted = [$ca, $cb];
            sort($sorted, SORT_STRING);
            $nationalityPairs[implode("\0", $sorted)] = true;
            if ($ca !== $cb) {
                $internationalGames++;
            }
        }

        [$pairA, $pairB] = amiga_community_canonical_pair(
            (int) $metrics['player_a_id'],
            (int) $metrics['player_b_id'],
        );
        $pairs["{$pairA}:{$pairB}"] = true;
    }
    $gamesStmt->close();

    $decided = $ratedGames - $draws;
    $distinctPlayers = count($players);
    $maxGames = $gamesPerPlayer !== [] ? max($gamesPerPlayer) : 0;
    $avgGames = $distinctPlayers > 0
        ? round(array_sum($gamesPerPlayer) / $distinctPlayers, 3)
        : null;
    $avgOpponents = null;
    if ($opponentsPerPlayer !== []) {
        $oppCounts = array_map(static fn (array $set): int => count($set), $opponentsPerPlayer);
        $avgOpponents = round(array_sum($oppCounts) / count($oppCounts), 3);
    }

    $firstTimeWc = amiga_world_cup_count_first_time_wc_players(
        $con,
        $tournamentId,
        $players,
        $eventDate !== null ? (string) $eventDate : null,
        $chrono,
    );
    [$goldId, $silverId, $bronzeId] = amiga_world_cup_load_podium_player_ids($con, $tournamentId);
    $championGames = $goldId !== null ? ($gamesPerPlayer[$goldId] ?? 0) : null;

    $realmYearGames = amiga_world_cup_realm_games_in_year(
        $con,
        $calendarYear,
        $eventDate !== null ? (string) $eventDate : null,
        $chrono,
        $tournamentId,
    );
    $shareYear = ($realmYearGames !== null && $realmYearGames !== 0)
        ? amiga_community_rate($ratedGames, $realmYearGames)
        : null;

    if ($finalizedAt === null) {
        $finalizedAt = $tour['rating_finalized_at'] ?? null;
        if ($finalizedAt !== null) {
            $finalizedAt = (string) $finalizedAt;
        }
    }
    if ($finalizedAt === null) {
        $finalizedAt = gmdate('Y-m-d H:i:s');
    }

    $guestShare = amiga_community_rate(count($guestPlayers), $distinctPlayers);

    $row = [
        'tournament_id' => $tournamentId,
        'tournament_name' => (string) ($tour['name'] ?? ''),
        'calendar_year' => $calendarYear,
        'event_date' => $eventDate,
        'event_chrono' => $chrono,
        'host_country' => $hostCountry,
        'host_city' => $hostCity,
        'rated_games' => $ratedGames,
        'decided_games' => $decided,
        'draws' => $draws,
        'goals' => $goals,
        'double_digit_slots' => $ddSlots,
        'clean_sheet_slots' => $csSlots,
        'high_scoring_games' => $highScoring,
        'low_scoring_games' => $lowScoring,
        'blowout_games' => $blowouts,
        'knockout_games' => $knockoutGames,
        'group_games' => $groupGames,
        'goals_per_game' => amiga_community_rate($goals, $ratedGames),
        'draw_rate' => amiga_community_rate($draws, $ratedGames),
        'decided_rate' => amiga_community_rate($decided, $ratedGames),
        'double_digit_rate' => amiga_community_rate($ddSlots, $ratedGames),
        'clean_sheet_rate' => amiga_community_rate($csSlots, $ratedGames),
        'high_scoring_rate' => amiga_community_rate($highScoring, $ratedGames),
        'low_scoring_rate' => amiga_community_rate($lowScoring, $ratedGames),
        'blowout_rate' => amiga_community_rate($blowouts, $ratedGames),
        'distinct_players' => $distinctPlayers,
        'distinct_player_nationalities' => count($nationalities),
        'max_games_one_player' => $maxGames,
        'first_time_wc_players' => $firstTimeWc,
        'distinct_opponent_pairs' => count($pairs),
        'avg_games_per_player' => $avgGames,
        'avg_opponents_per_player' => $avgOpponents,
        'distinct_host_country_players' => count($hostPlayers),
        'distinct_guest_players' => count($guestPlayers),
        'guest_player_share' => $guestShare,
        'distinct_opponent_countries_pairs' => count($nationalityPairs),
        'international_games' => $internationalGames,
        'international_game_share' => amiga_community_rate($internationalGames, $ratedGames),
        'highest_goal_sum' => $ratedGames > 0 ? $highestSum : null,
        'highest_goal_sum_game_id' => $highestSumGameId,
        'lowest_goal_sum' => $lowestSum,
        'lowest_goal_sum_game_id' => $lowestSumGameId,
        'biggest_margin' => $ratedGames > 0 ? $biggestMargin : null,
        'biggest_margin_game_id' => $biggestMarginGameId,
        'highest_scoring_draw_sum' => $highestDrawSum >= 0 ? $highestDrawSum : null,
        'highest_scoring_draw_game_id' => $highestDrawGameId,
        'most_goals_one_player_game' => $mostGoalsOne >= 0 ? $mostGoalsOne : null,
        'most_goals_one_player_game_id' => $mostGoalsOneGameId,
        'gold_player_id' => $goldId,
        'silver_player_id' => $silverId,
        'bronze_player_id' => $bronzeId,
        'champion_game_count' => $championGames,
        'share_of_year_games' => $shareYear,
        'finalized_at' => $finalizedAt,
    ];

    $columns = amiga_world_cup_stats_column_names();
    $missing = [];
    foreach ($columns as $col) {
        if (!array_key_exists($col, $row)) {
            $missing[] = $col;
        }
    }
    if ($missing !== []) {
        throw new RuntimeException('world cup stats row missing columns: ' . implode(', ', $missing));
    }

    return $row;
}

function amiga_world_cup_persist_for_tournament(
    mysqli $con,
    int $tournamentId,
    string $finalizedAt,
): bool {
    $row = amiga_world_cup_build_stats_row($con, $tournamentId, $finalizedAt);
    if ($row === null) {
        return false;
    }

    $columns = amiga_world_cup_stats_column_names();
    $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $updates = [];
    foreach ($columns as $col) {
        if ($col !== 'tournament_id') {
            $updates[] = "`{$col}` = VALUES(`{$col}`)";
        }
    }
    $sql = "INSERT INTO amiga_world_cup_stats ({$colList}) VALUES ({$placeholders}) "
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare world cup stats upsert: ' . $con->error);
    }

    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $val = $row[$col];
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }

    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute world cup stats upsert: ' . $err);
    }
    $stmt->close();

    return true;
}
