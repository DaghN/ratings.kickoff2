<?php
/**
 * Online player hover glance — lightweight JSON payload from playertable + stored milestones.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/player_milestones_helpers.php';

/**
 * @return array<string, mixed>
 */
function online_player_glance_payload(mysqli $con, int $playerId): array
{
    $playerId = max(0, $playerId);
    if ($playerId < 1) {
        throw new InvalidArgumentException('Invalid player id.');
    }

    $stmt = $con->prepare(
        'SELECT id, Name, Rating, NumberGames FROM playertable WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('Player lookup failed.');
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Player lookup failed.');
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException('Player not found.');
    }

    $games = k2_db_is_null($row['NumberGames'] ?? null) ? 0 : (int) $row['NumberGames'];
    $ladderVisible = $games >= 1;
    $rating = null;
    $rank = null;
    if ($ladderVisible && !k2_db_is_null($row['Rating'] ?? null)) {
        $rating = (int) round((float) $row['Rating']);
        $rankStmt = $con->prepare(
            'SELECT COUNT(*) + 1 AS plrank FROM playertable '
            . 'WHERE NumberGames >= 1 AND Rating > (SELECT Rating FROM playertable WHERE id = ? LIMIT 1)'
        );
        if ($rankStmt !== false) {
            $rankStmt->bind_param('i', $playerId);
            if ($rankStmt->execute()) {
                $rankRes = $rankStmt->get_result();
                $rankRow = $rankRes ? $rankRes->fetch_assoc() : null;
                if ($rankRes) {
                    $rankRes->free();
                }
                if ($rankRow !== null) {
                    $rank = (int) $rankRow['plrank'];
                }
            }
            $rankStmt->close();
        }
    }

    $ms = k2_milestone_player_counts($con, $playerId);
    $milestoneTiers = $ms !== null ? k2_milestone_hero_tier_payload($ms, $playerId) : null;

    return [
        'realm' => 'online',
        'id' => (int) $row['id'],
        'name' => (string) ($row['Name'] ?? ''),
        'display' => $ladderVisible,
        'pre_debut' => false,
        'rank' => $ladderVisible ? $rank : null,
        'rating' => $rating,
        'games' => $games,
        'milestone_tiers' => $milestoneTiers,
    ];
}