<?php
/**
 * Amiga profile v0 — player row + ladder rank. No derived-table queries beyond stats join.
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/k2_amiga_routes.php';

/**
 * @return array<string, mixed>
 */
function amiga_player_load(mysqli $con, int $id): array
{
    if ($id < 1) {
        throw new InvalidArgumentException('Invalid player id.');
    }

    $stmt = $con->prepare(
        'SELECT p.id AS ID, p.name AS Name, p.country AS Country, p.display AS Display, '
        . 's.Rating, s.PeakRating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
        . 's.WinRatio, s.GoalsFor, s.GoalsAgainst, s.GoalRatio, s.PeakRatingGameID, s.AverageOpponentRating '
        . amiga_player_base_from_sql($con) . ' WHERE p.id = ? LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Player query failed.');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if ($row === null) {
        throw new RuntimeException('Player not found.');
    }

    $games = (int) ($row['NumberGames'] ?? 0);
    if ($games < 1) {
        throw new RuntimeException('Player has no rated games.');
    }

    $rankStmt = $con->prepare(amiga_player_career_rating_rank_sql($con));
    if (!$rankStmt) {
        throw new RuntimeException('Rank query failed.');
    }
    $rankStmt->bind_param('i', $id);
    $rankStmt->execute();
    $rankRes = $rankStmt->get_result();
    $rankRow = $rankRes->fetch_assoc();
    $rankStmt->close();

    $display = (int) ($row['Display'] ?? 0) === 1;

    return [
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'country' => (string) ($row['Country'] ?? ''),
        'display' => $display,
        'rating' => $display && !k2_db_is_null($row['Rating']) ? (int) round((float) $row['Rating']) : null,
        'peak_rating' => !k2_db_is_null($row['PeakRating']) && (float) $row['PeakRating'] > 0
            ? (int) round((float) $row['PeakRating']) : null,
        'games' => $games,
        'wins' => (int) ($row['NumberWins'] ?? 0),
        'draws' => (int) ($row['NumberDraws'] ?? 0),
        'losses' => (int) ($row['NumberLosses'] ?? 0),
        'win_pct' => !k2_db_is_null($row['WinRatio']) ? round(100 * (float) $row['WinRatio'], 1) : null,
        'goals_for' => (int) ($row['GoalsFor'] ?? 0),
        'goals_against' => (int) ($row['GoalsAgainst'] ?? 0),
        'goal_ratio' => !k2_db_is_null($row['GoalRatio']) ? (float) $row['GoalRatio'] : null,
        'opp_avg' => !k2_db_is_null($row['AverageOpponentRating']) ? (float) $row['AverageOpponentRating'] : null,
        'rank' => (int) ($rankRow['r'] ?? 0),
    ];
}

function k2_amiga_player_link(int $id, string $name): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';
    $href = amiga_url_with_context('/amiga/player/profile.php', ['id' => $id]);

    return '<a class="k2-link-star" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . k2_h($name) . '</a>';
}
