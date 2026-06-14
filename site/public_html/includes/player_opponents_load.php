<?php
/**
 * Player Opponents wing — read path for player_matchup_summary (stored truth).
 */
declare(strict_types=1);

require_once __DIR__ . '/status_queries.php';

function player_opponents_matchup_summary_has_extension(mysqli $con): bool
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        $cache = false;

        return false;
    }

    $res = $con->query("SHOW COLUMNS FROM `player_matchup_summary` LIKE 'max_goals_for'");
    $cache = $res !== false && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }

    return $cache;
}

/**
 * @return list<array<string, mixed>>|null Null when table missing (caller may fall back to live ratedresults scan).
 */
function player_opponents_matchup_summary_rows(mysqli $con, int $playerId): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return null;
    }

    $playerId = max(0, $playerId);
    $extended = player_opponents_matchup_summary_has_extension($con);

    $sql = 'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, '
        . 'm.games, m.wins, m.draws, m.losses, m.goals_for, m.goals_against';

    if ($extended) {
        $sql .= ', m.max_goals_for, m.max_goals_against, m.min_goals_for, m.min_goals_against, '
            . 'm.max_win_margin, m.max_loss_margin, m.max_draw_goals, m.max_goal_sum, m.min_goal_sum, '
            . 'm.double_digits, m.double_digits_conceded, m.clean_sheets, m.clean_sheets_conceded';
    }

    $sql .= ' FROM player_matchup_summary m '
        . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
        . 'WHERE m.player_id = ? '
        . 'ORDER BY m.games DESC, opponent_name ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }

    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $mapped = [
            'opponent_id' => (int) $row['opponent_id'],
            'opponent_name' => (string) $row['opponent_name'],
            'games' => (int) $row['games'],
            'wins' => (int) $row['wins'],
            'draws' => (int) $row['draws'],
            'losses' => (int) $row['losses'],
            'goals_for' => (int) $row['goals_for'],
            'goals_against' => (int) $row['goals_against'],
        ];

        if ($extended) {
            $mapped['max_goals_for'] = (int) $row['max_goals_for'];
            $mapped['max_goals_against'] = (int) $row['max_goals_against'];
            $mapped['min_goals_for'] = (int) $row['min_goals_for'];
            $mapped['min_goals_against'] = (int) $row['min_goals_against'];
            $mapped['max_win_margin'] = $row['max_win_margin'] !== null ? (int) $row['max_win_margin'] : null;
            $mapped['max_loss_margin'] = $row['max_loss_margin'] !== null ? (int) $row['max_loss_margin'] : null;
            $mapped['max_draw_goals'] = $row['max_draw_goals'] !== null ? (int) $row['max_draw_goals'] : null;
            $mapped['max_goal_sum'] = (int) $row['max_goal_sum'];
            $mapped['min_goal_sum'] = (int) $row['min_goal_sum'];
            $mapped['double_digits'] = (int) $row['double_digits'];
            $mapped['double_digits_conceded'] = (int) $row['double_digits_conceded'];
            $mapped['clean_sheets'] = (int) $row['clean_sheets'];
            $mapped['clean_sheets_conceded'] = (int) $row['clean_sheets_conceded'];
        }

        $rows[] = $mapped;
    }

    $stmt->close();

    return $rows;
}

function player_opponents_matchup_ratio(int $part, int $games): float
{
    return $games > 0 ? $part / $games : 0.0;
}

function player_opponents_goal_ratio(int $goalsFor, int $goalsAgainst): float
{
    return $goalsAgainst !== 0 ? $goalsFor / $goalsAgainst : -1.0;
}
