<?php
/**
 * Amiga opponent matchup reads — present summary vs at-event snapshot (time travel).
 *
 * @see docs/amiga-opponents-wing-policy.md
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * Latest at-event matchup row per opponent on or before cutoff (alias ``m``).
 */
function amiga_matchup_at_event_latest_from_sql(string $alias = 'm'): string
{
    return "FROM (\n"
        . "    SELECT x.* FROM (\n"
        . "        SELECT m.*,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY m.player_id, m.opponent_id\n"
        . "                ORDER BY m.event_date DESC, m.event_chrono DESC, m.as_of_tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_matchup_at_event m\n"
        . "        WHERE m.player_id = ?\n"
        . "          AND (m.event_date, m.event_chrono, m.as_of_tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias}";
}

/**
 * @return list<string>
 */
function amiga_matchup_opponents_rating_at_cutoff_join_sql(): string
{
    return "LEFT JOIN (\n"
        . "    SELECT x.player_id, x.Rating FROM (\n"
        . "        SELECT s.player_id, s.Rating,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY s.player_id\n"
        . "                ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_event_snapshots s\n"
        . "        WHERE (s.event_date, s.event_chrono, s.tournament_id) <= (?, ?, ?)\n"
        . "    ) x WHERE x.rn = 1\n"
        . ") opp_snap ON opp_snap.player_id = m.opponent_id";
}

/**
 * @return list<string>
 */
function amiga_matchup_opponents_select_columns(bool $atCutoff = false): array
{
    $ratingExpr = $atCutoff
        ? 'COALESCE(opp_snap.Rating, 0) AS opponent_rating'
        : 'COALESCE(c.Rating, 0) AS opponent_rating';

    return [
        'm.opponent_id',
        'COALESCE(p.name, CONCAT(\'#\', m.opponent_id)) AS opponent_name',
        'p.country AS opponent_country',
        $ratingExpr,
        'm.games',
        'm.wins',
        'm.draws',
        'm.losses',
        'm.goals_for',
        'm.goals_against',
        'm.max_goals_for',
        'm.max_goals_against',
        'm.min_goals_for',
        'm.min_goals_against',
        'm.max_win_margin',
        'm.max_loss_margin',
        'm.max_draw_goals',
        'm.max_goal_sum',
        'm.min_goal_sum',
        'm.dd_wins',
        'm.dd_losses',
        'm.cs_wins',
        'm.cs_losses',
    ];
}

/**
 * Directed matchup rows for one player (present or at cutoff).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_matchup_opponent_rows(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    if ($playerId < 1) {
        return [];
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $select = 'SELECT ' . implode(', ', amiga_matchup_opponents_select_columns($ctx->isActive()));

    if (!$ctx->isActive()) {
        $sql = $select
            . ' FROM amiga_player_matchup_summary m'
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
            . ' LEFT JOIN amiga_player_current c ON c.player_id = m.opponent_id'
            . ' WHERE m.player_id = ? AND m.games > 0'
            . ' ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $playerId);
    } else {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return [];
        }
        $sql = $select
            . ' ' . amiga_matchup_at_event_latest_from_sql('m')
            . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
            . ' ' . amiga_matchup_opponents_rating_at_cutoff_join_sql()
            . ' WHERE m.games > 0'
            . ' ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $eventDate = $cutoff['event_date'];
        $chrono = $cutoff['chrono'];
        $tournamentId = $cutoff['tournament_id'];
        $stmt->bind_param('isdisdi', $playerId, $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    }

    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }

    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rows;
}
