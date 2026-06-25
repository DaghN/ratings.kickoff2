<?php
/**
 * Amiga profile v0 — player row + ladder rank (present or snapshot at cutoff).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_player_snapshot_lib.php';
require_once __DIR__ . '/amiga_elo_rank_lib.php';

/**
 * @return array<string, mixed>
 */
function amiga_player_load(mysqli $con, int $id, ?AmigaSnapshotContext $ctx = null): array
{
    if ($id < 1) {
        throw new InvalidArgumentException('Invalid player id.');
    }

    if ($ctx === null) {
        $ctx = amiga_snapshot_context_from_request($con);
    }

    if ($ctx->isActive()) {
        return amiga_player_load_at_cutoff($con, $id, $ctx);
    }

    return amiga_player_load_present($con, $id);
}

/**
 * @return array<string, mixed>
 */
function amiga_player_load_present(mysqli $con, int $id): array
{
    $stmt = $con->prepare(
        'SELECT p.id AS ID, p.name AS Name, p.country AS Country, p.display AS Display, '
        . 's.Rating, s.elo_rank, s.PeakRating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
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

    $display = (int) ($row['Display'] ?? 0) === 1;

    return [
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'country' => (string) ($row['Country'] ?? ''),
        'display' => $display,
        'at_cutoff' => true,
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
        'rank' => (int) ($row['elo_rank'] ?? 0),
    ];
}

/**
 * @return array<string, mixed>
 */
function amiga_player_load_at_cutoff(mysqli $con, int $id, AmigaSnapshotContext $ctx): array
{
    $stmt = $con->prepare(
        'SELECT p.id AS ID, p.name AS Name, p.country AS Country FROM amiga_players p WHERE p.id = ? LIMIT 1'
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

    $snap = amiga_player_snapshot_row_at_cutoff($con, $id, $ctx);
    if ($snap === null || (int) ($snap['NumberGames'] ?? 0) < 1) {
        return amiga_player_pre_debut_row($row);
    }

    $rank = amiga_player_elo_rank_at_cutoff($con, $id, $ctx);

    return amiga_player_row_from_snapshot($row, $snap, $rank);
}

/**
 * Publish loaded player row into hero template globals.
 *
 * @param array<string, mixed> $pm
 */
function amiga_player_publish_hero_context(array $pm): void
{
    global $id, $Name, $Rating, $NumberGames, $rank, $Country, $Display, $playerId, $k2AmigaPlayerPreDebut;

    $id = (int) $pm['id'];
    $playerId = $id;
    $Name = (string) $pm['name'];
    $Country = (string) ($pm['country'] ?? '');
    $Display = !empty($pm['display']) ? 1 : 0;
    $k2AmigaPlayerPreDebut = ($pm['at_cutoff'] ?? true) === false;

    if ($k2AmigaPlayerPreDebut) {
        $Rating = null;
        $NumberGames = null;
        $rank = null;
    } else {
        $Rating = $pm['rating'] ?? null;
        $NumberGames = $pm['games'] ?? null;
        $rank = $pm['rank'] ?? null;
    }
}

function k2_amiga_player_profile_href(int $id, string $fragment = K2_PLAYER_PAGE_FRAGMENT): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';
    $href = amiga_url_with_context('/amiga/player/profile.php', ['id' => $id]);
    if ($fragment !== '') {
        $href .= '#' . ltrim($fragment, '#');
    }

    return $href;
}

function k2_amiga_player_link(int $id, string $name, string $fragment = K2_PLAYER_PAGE_FRAGMENT): string
{
    $href = k2_amiga_player_profile_href($id, $fragment);

    return '<a class="k2-link-star" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . k2_h($name) . '</a>';
}
