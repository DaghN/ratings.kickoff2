<?php
/**
 * Amiga Opponents wing — stored matchup rows (present or snapshot-at-cutoff).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_matchup_snapshot_lib.php';

function amiga_player_opponents_matchup_ratio(int $part, int $games): float
{
    return $games > 0 ? $part / $games : 0.0;
}

function amiga_player_opponents_goal_ratio(int $goalsFor, int $goalsAgainst): float
{
    return $goalsAgainst !== 0 ? $goalsFor / $goalsAgainst : -1.0;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_player_opponents_normalize_matchup_row(array $row): array
{
    return [
        'opponent_id' => (int) $row['opponent_id'],
        'opponent_name' => (string) $row['opponent_name'],
        'opponent_country' => (string) ($row['opponent_country'] ?? ''),
        'opponent_rating' => (int) ($row['opponent_rating'] ?? 0),
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
        'max_goals_for' => (int) ($row['max_goals_for'] ?? 0),
        'max_goals_against' => (int) ($row['max_goals_against'] ?? 0),
        'min_goals_for' => (int) ($row['min_goals_for'] ?? 0),
        'min_goals_against' => (int) ($row['min_goals_against'] ?? 0),
        'max_win_margin' => array_key_exists('max_win_margin', $row) && $row['max_win_margin'] !== null
            ? (int) $row['max_win_margin'] : null,
        'max_loss_margin' => array_key_exists('max_loss_margin', $row) && $row['max_loss_margin'] !== null
            ? (int) $row['max_loss_margin'] : null,
        'max_draw_goals' => array_key_exists('max_draw_goals', $row) && $row['max_draw_goals'] !== null
            ? (int) $row['max_draw_goals'] : null,
        'max_goal_sum' => (int) ($row['max_goal_sum'] ?? 0),
        'min_goal_sum' => (int) ($row['min_goal_sum'] ?? 0),
        'double_digits' => (int) ($row['dd_wins'] ?? 0),
        'double_digits_conceded' => (int) ($row['dd_losses'] ?? 0),
        'clean_sheets' => (int) ($row['cs_wins'] ?? 0),
        'clean_sheets_conceded' => (int) ($row['cs_losses'] ?? 0),
        'performance_rating' => array_key_exists('performance_rating', $row) && $row['performance_rating'] !== null
            ? (float) $row['performance_rating'] : null,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_opponents_matchup_rows(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    $raw = amiga_player_matchup_opponent_rows($con, $playerId, $ctx);
    $rows = [];
    foreach ($raw as $row) {
        $rows[] = amiga_player_opponents_normalize_matchup_row($row);
    }

    return $rows;
}
