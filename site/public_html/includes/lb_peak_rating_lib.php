<?php
/**
 * Peak rating leaderboard — playertable career peak + establishing game date (PeakRatingGameID).
 */
declare(strict_types=1);

/** Rated games shown before the peak-setting game in peak-context tooltips. */
const K2_LB_PEAK_CONTEXT_GAMES_BEFORE = 9;

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/peak_month_leaderboard_query.php';
require_once __DIR__ . '/k2_safety.php';

/**
 * @return mysqli_result|false
 */
function k2_lb_peak_rating_query(mysqli $con, ?string $orderClause = null)
{
    $orderClause ??= k2_lb_peak_rating_default_order_sql();
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, p.`PeakRating`, p.`LowestRating`, '
        . 'p.`AverageOpponentRating`, p.`HighestRatedVictim`, p.`LowestRatedCulprit`, '
        . 'p.`PeakRatingGameID` AS `peak_rating_game_id`, rr.`Date` AS `peak_rating_date` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `ratedresults` rr ON rr.`id` = p.`PeakRatingGameID` '
        . 'WHERE ' . $where . ' ORDER BY ' . $orderClause;

    return $con->query($sql);
}

function k2_lb_peak_rating_format_event_date(?string $date): string
{
    if ($date === null || $date === '') {
        return k2_fmt_dash();
    }

    $day = substr($date, 0, 10);
    if ($day === '') {
        return k2_fmt_dash();
    }

    return k2_format_peak_period('day', $day);
}

function k2_lb_peak_rating_date_sort_value(?string $date): string
{
    if ($date === null || $date === '') {
        return '0';
    }

    $ts = strtotime($date);

    return (string) ($ts !== false ? $ts : 0);
}

function k2_lb_peak_rating_context_enabled(mixed $peakRating, mixed $peakGameId): bool
{
    if (k2_fmt_peak_rating($peakRating) === '-' || k2_db_is_null($peakGameId) || (int) $peakGameId < 1) {
        return false;
    }

    return true;
}

/** Extra body cell classes when peak-context hover is available. */
function k2_lb_peak_rating_context_cell_class(mixed $peakRating, mixed $peakGameId): string
{
    return k2_lb_peak_rating_context_enabled($peakRating, $peakGameId)
        ? 'k2-lb-peak-context-cell k2-table-helped'
        : '';
}

/** data-* attrs for peak-context tooltip fetch (Peak + Peak date cells). */
function k2_lb_peak_rating_context_cell_attrs(int $playerId, mixed $peakRating, mixed $peakGameId): string
{
    if (!k2_lb_peak_rating_context_enabled($peakRating, $peakGameId)) {
        return '';
    }

    return ' tabindex="0" data-k2-lb-peak-player="' . $playerId . '"';
}

/**
 * Game row for peak-context JSON (idA/idB order; rating_delta is for the subject player).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function k2_lb_peak_rating_context_game_row(array $row, int $heroId, bool $isPeak): array
{
    $processed = k2_rated_game_is_processed($row);
    $ratingA = null;
    $ratingB = null;
    $ratingDelta = null;

    if ($processed) {
        $ratingA = $row['RatingA'] !== null ? (int) round((float) $row['RatingA']) : null;
        $ratingB = $row['RatingB'] !== null ? (int) round((float) $row['RatingB']) : null;
        $isHeroA = (int) ($row['idA'] ?? 0) === $heroId;
        $adjRaw = $isHeroA ? $row['AdjustmentA'] : $row['AdjustmentB'];
        $ratingDelta = $adjRaw !== null ? round((float) $adjRaw, 1) : null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'at' => (string) ($row['Date'] ?? ''),
        'is_peak' => $isPeak,
        'name_a' => (string) ($row['NameA'] ?? ''),
        'name_b' => (string) ($row['NameB'] ?? ''),
        'goals_a' => (int) ($row['GoalsA'] ?? 0),
        'goals_b' => (int) ($row['GoalsB'] ?? 0),
        'rating_a' => $ratingA,
        'rating_b' => $ratingB,
        'rating_delta' => $ratingDelta,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function k2_lb_peak_rating_context_fetch_games(mysqli $con, int $playerId, int $peakGameId): array
{
    $cols = '`id`, `Date`, `idA`, `idB`, `NameA`, `NameB`, `GoalsA`, `GoalsB`, '
        . '`RatingA`, `RatingB`, `AdjustmentA`, `AdjustmentB`, `ActualScore`';

    $stmtPeak = $con->prepare('SELECT ' . $cols . ' FROM `ratedresults` WHERE `id` = ? LIMIT 1');
    if ($stmtPeak === false) {
        return [];
    }
    $stmtPeak->bind_param('i', $peakGameId);
    $stmtPeak->execute();
    $peakRes = $stmtPeak->get_result();
    $peakRow = $peakRes ? $peakRes->fetch_assoc() : false;
    if ($peakRes) {
        $peakRes->free();
    }
    $stmtPeak->close();
    if (!$peakRow) {
        return [];
    }
    if ((int) $peakRow['idA'] !== $playerId && (int) $peakRow['idB'] !== $playerId) {
        return [];
    }

    $peakDate = (string) $peakRow['Date'];
    $rawRows = [$peakRow];

    $stmtBefore = $con->prepare(
        'SELECT ' . $cols . ' FROM `ratedresults` '
        . 'WHERE (`idA` = ? OR `idB` = ?) AND (`Date` < ? OR (`Date` = ? AND `id` < ?)) '
        . 'ORDER BY `Date` DESC, `id` DESC LIMIT ' . K2_LB_PEAK_CONTEXT_GAMES_BEFORE
    );
    if ($stmtBefore !== false) {
        $stmtBefore->bind_param('iissi', $playerId, $playerId, $peakDate, $peakDate, $peakGameId);
        $stmtBefore->execute();
        $beforeRes = $stmtBefore->get_result();
        $beforeRows = [];
        if ($beforeRes) {
            while ($row = $beforeRes->fetch_assoc()) {
                $beforeRows[] = $row;
            }
            $beforeRes->free();
        }
        $stmtBefore->close();
        $rawRows = array_merge(array_reverse($beforeRows), $rawRows);
    }

    $stmtAfter = $con->prepare(
        'SELECT ' . $cols . ' FROM `ratedresults` '
        . 'WHERE (`idA` = ? OR `idB` = ?) AND (`Date` > ? OR (`Date` = ? AND `id` > ?)) '
        . 'ORDER BY `Date` ASC, `id` ASC LIMIT 1'
    );
    if ($stmtAfter !== false) {
        $stmtAfter->bind_param('iissi', $playerId, $playerId, $peakDate, $peakDate, $peakGameId);
        $stmtAfter->execute();
        $afterRes = $stmtAfter->get_result();
        if ($afterRes) {
            $afterRow = $afterRes->fetch_assoc();
            if ($afterRow) {
                $rawRows[] = $afterRow;
            }
            $afterRes->free();
        }
        $stmtAfter->close();
    }

    require_once __DIR__ . '/k2_player_display_names.php';
    require_once __DIR__ . '/k2_rated_game_row.php';

    $nameMap = k2_player_display_names_for_rated_rows($con, $rawRows);
    $games = [];
    foreach (k2_rated_games_apply_display_names($rawRows, $nameMap) as $row) {
        $games[] = k2_lb_peak_rating_context_game_row(
            $row,
            $playerId,
            (int) ($row['id'] ?? 0) === $peakGameId
        );
    }

    return array_reverse($games);
}

/**
 * @return array<string, mixed>|null
 */
function k2_lb_peak_rating_context_payload(mysqli $con, int $playerId): ?array
{
    $stmt = $con->prepare('SELECT `PeakRating`, `PeakRatingGameID` FROM `playertable` WHERE `id` = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $playerRow = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if (!$playerRow) {
        return null;
    }

    $peakRating = $playerRow['PeakRating'];
    $peakGameId = $playerRow['PeakRatingGameID'] !== null ? (int) $playerRow['PeakRatingGameID'] : 0;
    if (!k2_lb_peak_rating_context_enabled($peakRating, $peakGameId)) {
        return null;
    }

    $games = k2_lb_peak_rating_context_fetch_games($con, $playerId, $peakGameId);
    if ($games === []) {
        return null;
    }

    $peakAt = '';
    foreach ($games as $game) {
        if (!empty($game['is_peak'])) {
            $peakAt = (string) ($game['at'] ?? '');
            break;
        }
    }

    return [
        'player_id' => $playerId,
        'peak_rating' => (int) round((float) $peakRating),
        'peak_game_id' => $peakGameId,
        'peak_at' => $peakAt,
        'games' => $games,
    ];
}
