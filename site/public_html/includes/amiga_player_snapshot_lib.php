<?php
/**
 * Amiga player reads at time-travel cutoff — event snapshot row + persisted elo_rank.
 *
 * @see docs/amiga-time-travel-policy.md T15
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_rating_history_lib.php';
require_once __DIR__ . '/k2_table_helpers.php';

function amiga_player_wing_request_path(?string $path = null): bool
{
    $path ??= amiga_snapshot_request_path();

    return str_contains(k2_table_path_only($path), '/amiga/player/');
}

function amiga_player_wing_id_from_request(): int
{
    return isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
}

/** First rated snapshot for one player — `as=event:ID` (T18 player Event stepper; not mode toggle — T19). */
function amiga_player_first_snapshot_as_param(mysqli $con, int $playerId): ?string
{
    if ($playerId < 1) {
        return null;
    }

    $sql = 'SELECT tournament_id FROM amiga_player_event_snapshots
        WHERE player_id = ? AND NumberGames > 0
        ORDER BY event_date ASC, event_chrono ASC, tournament_id ASC
        LIMIT 1';
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
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if ($row === false) {
        return null;
    }

    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    if ($tournamentId < 1) {
        return null;
    }

    return amiga_snapshot_format_as_param('event', (string) $tournamentId);
}

/**
 * Latest event-snapshot career row for one player on or before cutoff.
 *
 * @return array<string, mixed>|null
 */
function amiga_player_snapshot_row_at_cutoff(mysqli $con, int $playerId, AmigaSnapshotContext $ctx): ?array
{
    if ($playerId < 1 || !$ctx->isActive()) {
        return null;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return null;
    }

    $sql = 'SELECT s.* FROM (
        SELECT snap.*,
            ROW_NUMBER() OVER (
                PARTITION BY snap.player_id
                ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
            ) AS rn
        FROM amiga_player_event_snapshots snap
        WHERE snap.player_id = ?
          AND (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
    ) s WHERE s.rn = 1 LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('isdi', $playerId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? $row : null;
}

/** Career rating rank at cutoff (1 = highest among players with games > 0). */
function amiga_player_snapshot_rating_rank(mysqli $con, float $rating, AmigaSnapshotContext $ctx): int
{
    if (!$ctx->isActive()) {
        return 0;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) + 1 AS r FROM (
        SELECT x.Rating FROM (
            SELECT snap.Rating, snap.NumberGames,
                ROW_NUMBER() OVER (
                    PARTITION BY snap.player_id
                    ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC
                ) AS rn
            FROM amiga_player_event_snapshots snap
            WHERE (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)
        ) x
        WHERE x.rn = 1 AND x.NumberGames > 0
    ) ranked WHERE ranked.Rating > ?';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('sdid', $eventDate, $chrono, $tournamentId, $rating);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? (int) ($row['r'] ?? 0) : 0;
}

/**
 * @param array<string, mixed> $playerRow
 * @return array<string, mixed>
 */
function amiga_player_pre_debut_row(array $playerRow): array
{
    return [
        'id' => (int) $playerRow['ID'],
        'name' => (string) $playerRow['Name'],
        'country' => (string) ($playerRow['Country'] ?? ''),
        'display' => true,
        'at_cutoff' => false,
        'rating' => null,
        'peak_rating' => null,
        'games' => null,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'win_pct' => null,
        'goals_for' => 0,
        'goals_against' => 0,
        'goal_ratio' => null,
        'opp_avg' => null,
        'rank' => null,
    ];
}

/**
 * @param array<string, mixed> $playerRow
 * @param array<string, mixed> $snap
 * @return array<string, mixed>
 */
function amiga_player_row_from_snapshot(array $playerRow, array $snap, int $rank): array
{
    $display = (int) ($snap['Display'] ?? 0) === 1;
    $games = (int) ($snap['NumberGames'] ?? 0);

    return [
        'id' => (int) $playerRow['ID'],
        'name' => (string) $playerRow['Name'],
        'country' => (string) ($playerRow['Country'] ?? ''),
        'display' => $display,
        'at_cutoff' => true,
        'rating' => $display && !k2_db_is_null($snap['Rating']) ? (int) round((float) $snap['Rating']) : null,
        'peak_rating' => !k2_db_is_null($snap['PeakRating'] ?? null) && (float) ($snap['PeakRating'] ?? 0) > 0
            ? (int) round((float) $snap['PeakRating']) : null,
        'games' => $games,
        'wins' => (int) ($snap['NumberWins'] ?? 0),
        'draws' => (int) ($snap['NumberDraws'] ?? 0),
        'losses' => (int) ($snap['NumberLosses'] ?? 0),
        'win_pct' => !k2_db_is_null($snap['WinRatio'] ?? null) ? round(100 * (float) $snap['WinRatio'], 1) : null,
        'goals_for' => (int) ($snap['GoalsFor'] ?? 0),
        'goals_against' => (int) ($snap['GoalsAgainst'] ?? 0),
        'goal_ratio' => !k2_db_is_null($snap['GoalRatio'] ?? null) ? (float) $snap['GoalRatio'] : null,
        'opp_avg' => !k2_db_is_null($snap['AverageOpponentRating'] ?? null) ? (float) $snap['AverageOpponentRating'] : null,
        'rank' => $rank,
    ];
}
