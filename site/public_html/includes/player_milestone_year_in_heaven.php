<?php
/**
 * Milestone year_in_heaven — played every UTC week of a calendar year (52-slot grid).
 * Establishing game = MIN(ratedresults.id) on the week that completes the set.
 */
declare(strict_types=1);

/**
 * @return list<string> UTC Monday Y-m-d for weeks 1–52 of calendar year Y (week 1 contains 1 Jan).
 */
function k2_calendar_year_week_mondays(int $year): array
{
    if ($year < 2000 || $year > 2100) {
        return [];
    }
    $jan1 = new DateTimeImmutable(sprintf('%04d-01-01', $year), new DateTimeZone('UTC'));
    $dow = (int) $jan1->format('N');
    $week1 = $jan1->modify('-' . ($dow - 1) . ' days');
    $mondays = [];
    for ($i = 0; $i < 52; $i++) {
        $mondays[] = $week1->modify('+' . ($i * 7) . ' days')->format('Y-m-d');
    }

    return $mondays;
}

/**
 * Calendar year Y whose 52-week grid contains this UTC week Monday, or null.
 */
function k2_calendar_year_for_week_monday(string $weekMondayYmd): ?int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekMondayYmd)) {
        return null;
    }
    $year = (int) substr($weekMondayYmd, 0, 4);
    for ($y = $year - 1; $y <= $year + 1; $y++) {
        if (in_array($weekMondayYmd, k2_calendar_year_week_mondays($y), true)) {
            return $y;
        }
    }

    return null;
}

function k2_milestone_player_has_unlock(mysqli $con, int $playerId, string $milestoneKey): bool
{
    $stmt = $con->prepare(
        'SELECT 1 FROM `player_milestones` WHERE `player_id` = ? AND `milestone_key` = ? LIMIT 1'
    );
    if ($stmt === false) {
        return true;
    }
    $stmt->bind_param('is', $playerId, $milestoneKey);
    $stmt->execute();
    $res = $stmt->get_result();
    $has = $res && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $has;
}

/**
 * Post-game: after player_period_games week row includes this game.
 * Call when this rated game is the first in its UTC week slot (week games = 1 after upsert).
 *
 * @param int|null $qualifyingGameId rated game that completed 52/52 (live post-game = current game)
 * @param string|null $qualifyingGameDate that game's `Date` (Recent feed / achieved_at)
 */
function k2_milestone_maybe_unlock_year_in_heaven(
    mysqli $con,
    int $playerId,
    string $weekMondayYmd,
    ?int $qualifyingGameId = null,
    ?string $qualifyingGameDate = null
): void {
    if ($playerId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekMondayYmd)) {
        return;
    }
    if (!function_exists('k2_milestone_tables_ready')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
    }
    if (!function_exists('k2_play_streak_establishing_game')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
    }
    if (!k2_milestone_tables_ready($con)) {
        return;
    }
    if (k2_milestone_player_has_unlock($con, $playerId, 'year_in_heaven')) {
        return;
    }

    $calendarYear = k2_calendar_year_for_week_monday($weekMondayYmd);
    if ($calendarYear === null) {
        return;
    }

    $slots = k2_calendar_year_week_mondays($calendarYear);
    if ($slots === []) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($slots), '?'));
    $types = 'i' . str_repeat('s', count($slots));
    $sql = 'SELECT COUNT(*) AS c FROM `player_period_games` '
        . 'WHERE `player_id` = ? AND `period_type` = \'week\' AND `period_start` IN (' . $placeholders . ')';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return;
    }
    $params = array_merge([$playerId], $slots);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if (!$row || (int) $row['c'] !== 52) {
        return;
    }

    if ($qualifyingGameId !== null && $qualifyingGameId > 0 && $qualifyingGameDate !== null && $qualifyingGameDate !== '') {
        $unlockGameId = $qualifyingGameId;
        $unlockAt = $qualifyingGameDate;
    } else {
        $est = k2_play_streak_establishing_game($con, $playerId, $weekMondayYmd, 'week');
        if ($est === null) {
            return;
        }
        $unlockGameId = $est['id'];
        $unlockAt = $est['Date'];
    }

    k2_milestone_insert_game_unlock(
        $con,
        $playerId,
        'year_in_heaven',
        $unlockGameId,
        $unlockAt,
        $calendarYear
    );
}
