<?php
/**
 * Player Opponents H2H — load helpers and pair resolution.
 */
declare(strict_types=1);

require_once __DIR__ . '/status_queries.php';
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/player_opponents_lib.php';
require_once __DIR__ . '/player_opponents_load.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';
require_once __DIR__ . '/player_opponents_h2h_charts.php';
require_once __DIR__ . '/player_h2h_performance_rating.php';

function player_opponents_h2h_parse_opponent_id(mixed $raw, int $playerId): int
{
    $opponentId = is_numeric($raw) ? (int) $raw : 0;
    if ($opponentId <= 0 || $opponentId === $playerId) {
        return 0;
    }

    return $opponentId;
}

function player_opponents_h2h_pair_games_live(mysqli $con, int $playerId, int $opponentId): int
{
    $sql = 'SELECT COUNT(*) AS n FROM ratedresults WHERE '
        . '(idA = ? AND idB = ?) OR (idA = ? AND idB = ?)';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

function player_opponents_h2h_pair_games(mysqli $con, int $playerId, int $opponentId): int
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }

    $stmt = $con->prepare(
        'SELECT games FROM player_matchup_summary WHERE player_id = ? AND opponent_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }

    return (int) $row['games'];
}

/**
 * @return array{opponent_id: int, opponent_name: string, games: int}|null
 */
function player_opponents_h2h_resolve_opponent(mysqli $con, int $playerId, int $opponentId): ?array
{
    if ($opponentId <= 0 || $opponentId === $playerId) {
        return null;
    }

    $stmt = $con->prepare('SELECT ID, Name FROM playertable WHERE ID = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    return [
        'opponent_id' => (int) $row['ID'],
        'opponent_name' => (string) $row['Name'],
        'games' => player_opponents_h2h_pair_games($con, $playerId, $opponentId),
    ];
}

/**
 * Played opponents for dropdowns (summary when present, else live aggregation).
 *
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function player_opponents_h2h_played_opponents(mysqli $con, int $playerId): array
{
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return [];
    }

    if (k2_status_table_exists($con, 'player_matchup_summary')) {
        $sql = 'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, m.games '
            . 'FROM player_matchup_summary m '
            . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
            . 'WHERE m.player_id = ? AND m.games > 0 '
            . 'ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $playerId);
        if (!$stmt->execute()) {
            $stmt->close();

            return [];
        }
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'opponent_id' => (int) $row['opponent_id'],
                'opponent_name' => (string) $row['opponent_name'],
                'games' => (int) $row['games'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    return player_opponents_h2h_played_opponents_live($con, $playerId);
}

/**
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function player_opponents_h2h_played_opponents_live(mysqli $con, int $playerId): array
{
    $playerId = max(0, $playerId);
    $sql = 'SELECT opponentID, opponentname, COUNT(*) AS games FROM ('
        . 'SELECT idB AS opponentID, nameB AS opponentname FROM ratedresults WHERE idA = ? '
        . 'UNION ALL '
        . 'SELECT idA AS opponentID, nameA AS opponentname FROM ratedresults WHERE idB = ?'
        . ') AS sides GROUP BY opponentID, opponentname ORDER BY games DESC, opponentname ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $playerId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'opponent_id' => (int) $row['opponentID'],
            'opponent_name' => (string) $row['opponentname'],
            'games' => (int) $row['games'],
        ];
    }
    $stmt->close();

    return $rows;
}

function k2_h2h_games_meta_label(int $games): string
{
    return $games . ' game' . ($games === 1 ? '' : 's');
}

/**
 * @return array{player_id: int, name: string, display: bool, rank: ?int, rating: mixed}|null
 */
function player_opponents_h2h_load_player_card(mysqli $con, int $playerId): ?array
{
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return null;
    }

    $stmt = $con->prepare('SELECT Name, Rating, Display FROM playertable WHERE ID = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    $display = (int) ($row['Display'] ?? 0) === 1;
    $rank = null;
    if ($display) {
        $rankStmt = $con->prepare(
            'SELECT COUNT(*) + 1 AS plrank FROM playertable '
            . 'WHERE Display = 1 AND Rating > (SELECT Rating FROM playertable WHERE ID = ? LIMIT 1)'
        );
        if ($rankStmt) {
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

    return [
        'player_id' => $playerId,
        'name' => (string) $row['Name'],
        'display' => $display,
        'rank' => $rank,
        'rating' => $row['Rating'],
    ];
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function player_opponents_h2h_pair_record_live(mysqli $con, int $playerId, int $opponentId): ?array
{
    $sql = 'SELECT COUNT(*) AS games, COALESCE(SUM(win), 0) AS wins, COALESCE(SUM(draw), 0) AS draws, '
        . 'COALESCE(SUM(defeat), 0) AS losses, COALESCE(SUM(goalsfor), 0) AS goals_for, '
        . 'COALESCE(SUM(goalsagainst), 0) AS goals_against FROM ('
        . 'SELECT homewin AS win, draw, awaywin AS defeat, goalsA AS goalsfor, goalsB AS goalsagainst '
        . 'FROM ratedresults WHERE idA = ? AND idB = ? '
        . 'UNION ALL '
        . 'SELECT awaywin AS win, draw, homewin AS defeat, goalsB AS goalsfor, goalsA AS goalsagainst '
        . 'FROM ratedresults WHERE idA = ? AND idB = ?'
        . ') AS sides';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function player_opponents_h2h_pair_record(mysqli $con, int $playerId, int $opponentId): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }

    $stmt = $con->prepare(
        'SELECT games, wins, draws, losses, goals_for, goals_against '
        . 'FROM player_matchup_summary WHERE player_id = ? AND opponent_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }

    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * One identity card: avatar ring + name + rank/rating (hero-style).
 * Whole card links to the player profile when an id is present.
 *
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $card
 */
function k2_h2h_poster_card_html(array $card, string $side): string
{
    $name = (string) ($card['name'] ?? '');
    $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
    $pid = (int) ($card['player_id'] ?? 0);
    $display = !empty($card['display']);
    $rank = ($display && ($card['rank'] ?? null) !== null) ? '#' . (int) $card['rank'] : '—';
    $rating = ($display && isset($card['rating']) && !k2_db_is_null($card['rating']))
        ? k2_fmt_int($card['rating'], '—')
        : '—';
    $href = $pid > 0 ? k2_route('player-profile', ['id' => $pid]) : '';

    $rankStat = '<div class="k2-h2h2-card__stat"><dt>Rank</dt><dd>' . k2_h($rank) . '</dd></div>';
    $ratingStat = '<div class="k2-h2h2-card__stat"><dt>Rating</dt><dd>' . k2_h($rating) . '</dd></div>';
    // Opponent card mirrors subject: rank on the outer (avatar) edge, rating toward the vs.
    $stats = $side === 'opponent' ? ($ratingStat . $rankStat) : ($rankStat . $ratingStat);

    $inner = '<div class="k2-h2h2-card__media">'
        . '<div class="k2-h2h2-card__avatar" aria-hidden="true">' . k2_h($initial) . '</div>'
        . '</div>'
        . '<div class="k2-h2h2-card__body">'
        . '<p class="k2-h2h2-card__name">' . k2_h($name) . '</p>'
        . '<dl class="k2-h2h2-card__stats">'
        . $stats
        . '</dl>'
        . '</div>';

    $class = 'k2-h2h2-card k2-h2h2-card--' . k2_h($side);
    if ($href !== '') {
        $label = $name !== '' ? 'View ' . $name . ' profile' : 'View player profile';

        return '<a class="' . $class . ' k2-h2h2-card--link" href="' . k2_h($href) . '"'
            . ' aria-label="' . k2_h($label) . '">' . $inner . '</a>';
    }

    return '<article class="' . $class . '">' . $inner . '</article>';
}

/**
 * @return array<string, mixed>|null
 */
function player_opponents_h2h_pair_detail_live(mysqli $con, int $playerId, int $opponentId): ?array
{
    $sql = 'SELECT COUNT(*) AS games, COALESCE(SUM(win), 0) AS wins, COALESCE(SUM(draw), 0) AS draws, '
        . 'COALESCE(SUM(defeat), 0) AS losses, COALESCE(SUM(goalsfor), 0) AS goals_for, '
        . 'COALESCE(SUM(goalsagainst), 0) AS goals_against, COALESCE(SUM(DD), 0) AS double_digits, '
        . 'COALESCE(SUM(DDC), 0) AS double_digits_conceded, COALESCE(SUM(CS), 0) AS clean_sheets, '
        . 'COALESCE(SUM(CSC), 0) AS clean_sheets_conceded, MAX(goalsfor) AS max_goals_for, '
        . 'MAX(goalsagainst) AS max_goals_against, MIN(goalsfor) AS min_goals_for, '
        . 'MIN(goalsagainst) AS min_goals_against, '
        . 'MAX(CASE WHEN goalsfor > goalsagainst THEN goalsfor - goalsagainst ELSE NULL END) AS max_win_margin, '
        . 'MAX(CASE WHEN goalsagainst > goalsfor THEN goalsagainst - goalsfor ELSE NULL END) AS max_loss_margin, '
        . 'MAX(CASE WHEN draw = 1 THEN goalsfor ELSE NULL END) AS max_draw_goals, '
        . 'MAX(goalsfor + goalsagainst) AS max_goal_sum, MIN(goalsfor + goalsagainst) AS min_goal_sum '
        . 'FROM ('
        . 'SELECT homewin AS win, draw, awaywin AS defeat, goalsA AS goalsfor, goalsB AS goalsagainst, '
        . 'DDPlayerA AS DD, DDPlayerB AS DDC, CSPlayerA AS CS, CSPlayerB AS CSC '
        . 'FROM ratedresults WHERE idA = ? AND idB = ? '
        . 'UNION ALL '
        . 'SELECT awaywin AS win, draw, homewin AS defeat, goalsB AS goalsfor, goalsA AS goalsagainst, '
        . 'DDPlayerB AS DD, DDPlayerA AS DDC, CSPlayerB AS CS, CSPlayerA AS CSC '
        . 'FROM ratedresults WHERE idA = ? AND idB = ?'
        . ') AS sides';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null || (int) $row['games'] <= 0) {
        return null;
    }

    return player_opponents_h2h_pair_detail_attach_perf(
        $con,
        $playerId,
        $opponentId,
        player_opponents_h2h_pair_detail_map_row($row, true)
    );
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function player_opponents_h2h_pair_detail_map_row(array $row, bool $extremesStored): array
{
    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
        'double_digits' => (int) ($row['double_digits'] ?? 0),
        'double_digits_conceded' => (int) ($row['double_digits_conceded'] ?? 0),
        'clean_sheets' => (int) ($row['clean_sheets'] ?? 0),
        'clean_sheets_conceded' => (int) ($row['clean_sheets_conceded'] ?? 0),
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
        'extremes_stored' => $extremesStored,
        'perf_rating_subject' => null,
        'perf_rating_opponent' => null,
    ];
}

/**
 * @param array<string, mixed> $detail
 * @return array<string, mixed>
 */
function player_opponents_h2h_pair_detail_attach_perf(
    mysqli $con,
    int $playerId,
    int $opponentId,
    array $detail
): array {
    if ((int) ($detail['games'] ?? 0) < PERFORMANCE_RATING_MIN_GAMES) {
        $detail['perf_rating_subject'] = null;
        $detail['perf_rating_opponent'] = null;

        return $detail;
    }

    $perf = player_h2h_pair_performance_ratings($con, $playerId, $opponentId);
    $detail['perf_rating_subject'] = $perf['subject'];
    $detail['perf_rating_opponent'] = $perf['opponent'];

    return $detail;
}

/**
 * @return array<string, mixed>|null
 */
function player_opponents_h2h_pair_detail_load(mysqli $con, int $playerId, int $opponentId): ?array
{
    if (
        !k2_status_table_exists($con, 'player_matchup_summary')
        || !player_opponents_matchup_summary_has_extension($con)
    ) {
        return player_opponents_h2h_pair_detail_live($con, $playerId, $opponentId);
    }

    $sql = 'SELECT games, wins, draws, losses, goals_for, goals_against, max_goals_for, max_goals_against, '
        . 'min_goals_for, min_goals_against, max_win_margin, max_loss_margin, max_draw_goals, max_goal_sum, '
        . 'min_goal_sum, double_digits, double_digits_conceded, clean_sheets, clean_sheets_conceded '
        . 'FROM player_matchup_summary WHERE player_id = ? AND opponent_id = ? LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return player_opponents_h2h_pair_detail_live($con, $playerId, $opponentId);
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return player_opponents_h2h_pair_detail_live($con, $playerId, $opponentId);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null || (int) $row['games'] <= 0) {
        return player_opponents_h2h_pair_detail_live($con, $playerId, $opponentId);
    }

    return player_opponents_h2h_pair_detail_attach_perf(
        $con,
        $playerId,
        $opponentId,
        player_opponents_h2h_pair_detail_map_row($row, true)
    );
}

/**
 * @return 'subject'|'opponent'|'tie'|''
 */
function k2_h2h_perf_rating_leader(?int $subject, ?int $opponent): string
{
    if ($subject === null && $opponent === null) {
        return '';
    }
    if ($subject === null) {
        return 'opponent';
    }
    if ($opponent === null) {
        return 'subject';
    }

    return k2_h2h_race_leader((float) $subject, (float) $opponent, 'higher');
}

function k2_h2h_perf_rating_display(?int $rating): string
{
    return $rating !== null ? k2_fmt_int($rating, '—') : '—';
}

/**
 * @return 'subject'|'opponent'|'tie'|''
 */
function k2_h2h_race_leader(float $subject, float $opponent, string $mode): string
{
    if ($mode === 'none') {
        return '';
    }

    $epsilon = 0.0001;
    if (abs($subject - $opponent) < $epsilon) {
        return 'tie';
    }

    if ($mode === 'lower') {
        return $subject < $opponent ? 'subject' : 'opponent';
    }

    return $subject > $opponent ? 'subject' : 'opponent';
}

/**
 * Leader when either side may be missing (no wins yet → null margin).
 *
 * @return 'subject'|'opponent'|'tie'|''
 */
function k2_h2h_race_margin_leader(?int $subjectMargin, ?int $opponentMargin): string
{
    if ($subjectMargin === null && $opponentMargin === null) {
        return 'tie';
    }
    if ($subjectMargin === null) {
        return 'opponent';
    }
    if ($opponentMargin === null) {
        return 'subject';
    }

    return k2_h2h_race_leader((float) $subjectMargin, (float) $opponentMargin, 'higher');
}

function k2_h2h_race_margin_display(?int $margin): string
{
    return $margin !== null ? k2_fmt_int($margin, '0') : '-';
}

function k2_h2h_race_val_class(string $side): string
{
    return 'k2-h2h2-race__val k2-h2h2-race__val--' . $side;
}

function k2_h2h_race_val_html(string $display, string $side, string $leader): string
{
    if ($leader !== $side && $leader !== 'tie') {
        return $display;
    }

    $tone = $side === 'subject' ? 'blue' : 'red';

    return '<span class="' . $tone . '">' . $display . '</span>';
}

/**
 * Tie rows colour both sides; 0–0 stays muted unless the row opts in (least conceded).
 *
 * @return 'subject'|'opponent'|'tie'|''
 */
function k2_h2h_race_effective_leader(string $leader, float $subject, float $opponent, bool $colorZeroTie = false): string
{
    if ($leader !== 'tie') {
        return $leader;
    }

    if ($colorZeroTie) {
        return 'tie';
    }

    if (abs($subject) < 0.0001 && abs($opponent) < 0.0001) {
        return '';
    }

    return 'tie';
}

/**
 * @return 'subject'|'opponent'|'tie'|''
 */
function k2_h2h_race_margin_effective_leader(string $leader, ?int $subjectMargin, ?int $opponentMargin): string
{
    if ($leader !== 'tie') {
        return $leader;
    }

    if ($subjectMargin === null || $opponentMargin === null) {
        return 'tie';
    }

    return k2_h2h_race_effective_leader('tie', (float) $subjectMargin, (float) $opponentMargin, false);
}

/**
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $subjectCard
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $opponentCard
 * @param array<string, mixed> $detail
 */
function player_opponents_render_h2h_pair_detail(array $subjectCard, array $opponentCard, array $detail): void
{
    $games = (int) $detail['games'];
    if ($games <= 0) {
        return;
    }

    $goalsFor = (int) $detail['goals_for'];
    $goalsAgainst = (int) $detail['goals_against'];
    $avgFor = $games > 0 ? $goalsFor / $games : 0.0;
    $avgAgainst = $games > 0 ? $goalsAgainst / $games : 0.0;
    $dd = (int) $detail['double_digits'];
    $oppDd = (int) $detail['double_digits_conceded'];
    $cs = (int) $detail['clean_sheets'];
    $oppCs = (int) $detail['clean_sheets_conceded'];
    $maxWin = $detail['max_win_margin'];
    $maxLoss = $detail['max_loss_margin'];
    $subjectWinMargin = $maxWin !== null ? (int) $maxWin : null;
    $opponentWinMargin = $maxLoss !== null ? (int) $maxLoss : null;

    $renderRace = static function (
        string $label,
        string $subjectDisplay,
        string $opponentDisplay,
        string $leader,
        string $labelHelp = ''
    ): void {
        $labAttrs = '';
        if ($labelHelp !== '') {
            $labAttrs = ' data-k2-help="' . k2_h($labelHelp) . '" tabindex="0"';
        }
        ?>
	<tr class="k2-h2h2-race__row">
		<td class="<?php echo k2_h(k2_h2h_race_val_class('subject')); ?>"><?php echo k2_h2h_race_val_html($subjectDisplay, 'subject', $leader); ?></td>
		<th class="k2-h2h2-race__lab" scope="row"<?php echo $labAttrs; ?>><?php echo k2_h($label); ?></th>
		<td class="<?php echo k2_h(k2_h2h_race_val_class('opponent')); ?>"><?php echo k2_h2h_race_val_html($opponentDisplay, 'opponent', $leader); ?></td>
	</tr>
        <?php
    };

    $subjectPerf = array_key_exists('perf_rating_subject', $detail)
        ? $detail['perf_rating_subject']
        : null;
    $opponentPerf = array_key_exists('perf_rating_opponent', $detail)
        ? $detail['perf_rating_opponent']
        : null;
    if (!is_int($subjectPerf) && $subjectPerf !== null) {
        $subjectPerf = null;
    }
    if (!is_int($opponentPerf) && $opponentPerf !== null) {
        $opponentPerf = null;
    }

    $leastConcededSubject = (int) $detail['min_goals_against'];
    $leastConcededOpponent = (int) $detail['min_goals_for'];

    $caption = sprintf(
        'Head-to-head comparison for %s versus %s.',
        (string) ($subjectCard['name'] ?? ''),
        (string) ($opponentCard['name'] ?? '')
    );
    ?>
<section class="k2-h2h2-detail" aria-label="Head-to-head comparison">
	<table class="k2-h2h2-race">
		<caption class="k2-h2h2-race__caption"><?php echo k2_h($caption); ?></caption>
		<tbody>
		<?php
    $renderRace(
        'Goals scored',
            k2_h(k2_fmt_int($goalsFor, '0')),
            k2_h(k2_fmt_int($goalsAgainst, '0')),
            k2_h2h_race_effective_leader(
                k2_h2h_race_leader((float) $goalsFor, (float) $goalsAgainst, 'higher'),
                (float) $goalsFor,
                (float) $goalsAgainst
            )
        );
    $renderRace(
        'Goals per game',
        k2_h(k2_fmt_decimal($avgFor, $games)),
        k2_h(k2_fmt_decimal($avgAgainst, $games)),
        k2_h2h_race_effective_leader(
            k2_h2h_race_leader($avgFor, $avgAgainst, 'higher'),
            $avgFor,
            $avgAgainst
        )
    );
    $renderRace(
        'Most scored',
        k2_h(k2_fmt_int($detail['max_goals_for'], '0')),
        k2_h(k2_fmt_int($detail['max_goals_against'], '0')),
        k2_h2h_race_effective_leader(
            k2_h2h_race_leader((float) $detail['max_goals_for'], (float) $detail['max_goals_against'], 'higher'),
            (float) $detail['max_goals_for'],
            (float) $detail['max_goals_against']
        )
    );
    $renderRace(
        'Biggest winning margin',
        k2_h(k2_h2h_race_margin_display($subjectWinMargin)),
        k2_h(k2_h2h_race_margin_display($opponentWinMargin)),
        k2_h2h_race_margin_effective_leader(
            k2_h2h_race_margin_leader($subjectWinMargin, $opponentWinMargin),
            $subjectWinMargin,
            $opponentWinMargin
        )
    );
    $renderRace(
        'Least conceded',
        k2_h(k2_fmt_int($leastConcededSubject, '0')),
        k2_h(k2_fmt_int($leastConcededOpponent, '0')),
        k2_h2h_race_effective_leader(
            k2_h2h_race_leader((float) $leastConcededSubject, (float) $leastConcededOpponent, 'lower'),
            (float) $leastConcededSubject,
            (float) $leastConcededOpponent,
            true
        )
    );
    $renderRace(
        'Double digits',
        k2_h(k2_fmt_int($dd, '0')),
        k2_h(k2_fmt_int($oppDd, '0')),
        k2_h2h_race_effective_leader(
            k2_h2h_race_leader((float) $dd, (float) $oppDd, 'higher'),
            (float) $dd,
            (float) $oppDd
        )
    );
    $renderRace(
        'Clean sheets',
        k2_h(k2_fmt_int($cs, '0')),
        k2_h(k2_fmt_int($oppCs, '0')),
        k2_h2h_race_effective_leader(
            k2_h2h_race_leader((float) $cs, (float) $oppCs, 'higher'),
            (float) $cs,
            (float) $oppCs
        )
    );
    $renderRace(
        'Performance rating',
        k2_h(k2_h2h_perf_rating_display($subjectPerf)),
        k2_h(k2_h2h_perf_rating_display($opponentPerf)),
        k2_h2h_perf_rating_leader($subjectPerf, $opponentPerf),
        performance_rating_h2h_pair_help()
    );
    ?>
		</tbody>
	</table>
</section>
    <?php
}

/**
 * Holo link to the subject's full rated games list vs this opponent.
 *
 * @param array{player_id:int,name:string} $subjectCard
 * @param array{player_id:int,name:string} $opponentCard
 */
function player_opponents_render_h2h_all_games_link(array $subjectCard, array $opponentCard, int $games): void
{
    if ($games <= 0) {
        return;
    }

    $subjectId = (int) ($subjectCard['player_id'] ?? 0);
    $opponentId = (int) ($opponentCard['player_id'] ?? 0);
    $opponentName = (string) ($opponentCard['name'] ?? '');
    if ($subjectId <= 0 || $opponentId <= 0 || $opponentName === '') {
        return;
    }

    $href = '/player/games.php?id=' . $subjectId . '&opponent=' . $opponentId;
    $label = sprintf(
        'All %s rated games vs %s →',
        k2_fmt_int($games, '0'),
        $opponentName
    );
    ?>
<p class="k2-h2h2-all-games">
	<a class="k2-h2h2-all-games__link" href="<?php echo k2_h($href); ?>"><?php echo k2_h($label); ?></a>
</p>
    <?php
}

/**
 * Versus poster: mirrored identity cards around a `vs`, W/D/L hero, lead meter.
 *
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $subjectCard
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $opponentCard
 * @param array{games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int}|null $record
 */
function player_opponents_render_h2h_poster(
    array $subjectCard,
    array $opponentCard,
    ?array $record,
    int $games
): void {
    $hasGames = $record !== null && $games > 0;
    $w = $hasGames ? (int) $record['wins'] : 0;
    $d = $hasGames ? (int) $record['draws'] : 0;
    $l = $hasGames ? (int) $record['losses'] : 0;
    $total = $w + $d + $l;

    $pct = static function (int $part) use ($total): string {
        if ($total <= 0) {
            return '0';
        }

        return rtrim(rtrim(number_format(($part / $total) * 100, 3, '.', ''), '0'), '.');
    };

    $subjectName = (string) ($subjectCard['name'] ?? '');
    $opponentName = (string) ($opponentCard['name'] ?? '');
    $meterLabel = sprintf(
        '%s: %d won, %d drawn, %d lost in %d games versus %s.',
        $subjectName,
        $w,
        $d,
        $l,
        $games,
        $opponentName
    );
    ?>
<section class="k2-h2h2-poster k2-h2h2-poster--mirrored"<?php echo $hasGames ? '' : ' data-empty="1"'; ?>>
	<div class="k2-h2h2-marquee">
		<?php echo k2_h2h_poster_card_html($subjectCard, 'subject'); ?>
		<div class="k2-h2h2-vs" aria-hidden="true">vs</div>
		<?php echo k2_h2h_poster_card_html($opponentCard, 'opponent'); ?>
	</div>

	<?php if ($hasGames) { ?>
	<div class="k2-h2h2-record" role="group" aria-label="<?php echo k2_h(sprintf('%s wins, draws, %s wins', $subjectName, $opponentName)); ?>">
		<div class="k2-h2h2-stat k2-h2h2-stat--win">
			<span class="k2-h2h2-num blue"><?php echo k2_h(k2_fmt_int($w, '0')); ?></span>
			<span class="k2-h2h2-lab">Wins</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--draw">
			<span class="k2-h2h2-num"><?php echo k2_h(k2_fmt_int($d, '0')); ?></span>
			<span class="k2-h2h2-lab">Draws</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--opp-win">
			<span class="k2-h2h2-num red"><?php echo k2_h(k2_fmt_int($l, '0')); ?></span>
			<span class="k2-h2h2-lab">Wins</span>
		</div>
	</div>

	<div class="k2-h2h2-meter" role="img" aria-label="<?php echo k2_h($meterLabel); ?>">
		<span class="k2-h2h2-seg k2-h2h2-seg--win<?php echo $w > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($w); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--draw<?php echo $d > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($d); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--loss<?php echo $l > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($l); ?>%"></span>
	</div>
	<?php } else { ?>
	<p class="k2-h2h2-none">No rated games yet</p>
	<?php } ?>
</section>
	<?php
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 */
function k2_h2h_opponent_listbox_render(
    string $inputId,
    string $selectedValue,
    array $rows,
    string $ariaLabel,
    string $placeholder = 'Choose opponent…',
    string $emptyLabel = 'No opponents yet'
): void {
    $selectedLabel = '';
    $selectedValue = (string) $selectedValue;
    foreach ($rows as $row) {
        if ((string) (int) $row['opponent_id'] === $selectedValue) {
            $selectedLabel = (string) $row['opponent_name'];
            break;
        }
    }

    $listboxId = $inputId . '-listbox';
    $triggerId = $inputId . '-trigger';
    $hasRows = $rows !== [];
    $labelClass = 'k2-archive-listbox__label';
    if ($selectedLabel === '') {
        $labelClass .= ' k2-archive-listbox__label--placeholder';
        $selectedLabel = $hasRows ? $placeholder : $emptyLabel;
    }
    ?>
<div class="k2-archive-listbox k2-player-opponents-h2h__listbox" data-k2-archive-listbox>
    <input type="hidden" id="<?php echo k2_archive_listbox_h($inputId); ?>" class="k2-archive-listbox__value" value="<?php echo k2_archive_listbox_h($selectedValue); ?>" />
    <button
        type="button"
        id="<?php echo k2_archive_listbox_h($triggerId); ?>"
        class="k2-archive-listbox__trigger server-period-activity-leaderboard__input"
        aria-label="<?php echo k2_archive_listbox_h($ariaLabel); ?>"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="<?php echo k2_archive_listbox_h($listboxId); ?>"
        <?php echo $hasRows ? '' : ' disabled="disabled"'; ?>
    >
        <span class="<?php echo k2_archive_listbox_h($labelClass); ?>"><?php echo k2_archive_listbox_h($selectedLabel); ?></span>
        <span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
    </button>
    <ul id="<?php echo k2_archive_listbox_h($listboxId); ?>" class="k2-archive-listbox__panel" role="listbox" tabindex="-1" hidden="hidden">
<?php foreach ($rows as $row) {
    $value = (string) (int) $row['opponent_id'];
    $name = (string) $row['opponent_name'];
    $games = (int) $row['games'];
    $sel = $value === $selectedValue;
    $optClass = 'k2-archive-listbox__option k2-h2h-listbox__option' . ($sel ? ' is-selected' : '');
    ?>
        <li
            class="<?php echo k2_archive_listbox_h($optClass); ?>"
            role="option"
            data-value="<?php echo k2_archive_listbox_h($value); ?>"
            data-trigger-label="<?php echo k2_archive_listbox_h($name); ?>"
            aria-selected="<?php echo $sel ? 'true' : 'false'; ?>"
        >
            <span class="player-search-name k2-h2h-listbox__name"><?php echo k2_archive_listbox_h($name); ?></span>
            <span class="player-search-meta k2-h2h-listbox__meta"><?php echo k2_archive_listbox_h(k2_h2h_games_meta_label($games)); ?></span>
        </li>
<?php } ?>
    </ul>
</div>
    <?php
}

function player_opponents_render_h2h_panel(
    mysqli $con,
    int $playerId,
    string $playerName,
    int $selectedOpponentId = 0,
    bool $defaultToTopOpponent = false
): void {
    $playerId = max(0, $playerId);
    $playerName = trim($playerName);
    if ($playerName === '') {
        $playerName = '#' . $playerId;
    }

    $played = player_opponents_h2h_played_opponents($con, $playerId);
    if ($defaultToTopOpponent && $selectedOpponentId <= 0 && $played !== []) {
        $selectedOpponentId = (int) $played[0]['opponent_id'];
    }
    $byAlpha = $played;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp($a['opponent_name'], $b['opponent_name']);
        }
    );

    $pair = $selectedOpponentId > 0
        ? player_opponents_h2h_resolve_opponent($con, $playerId, $selectedOpponentId)
        : null;

    $searchUid = 'k2-h2h-search-' . $playerId;
    ?>
<div
	class="k2-player-opponents-h2h"
	data-k2-carry-scroll
	data-player-id="<?php echo $playerId; ?>"
	data-h2h-base="<?php echo k2_h(player_opponents_href($playerId, 'h2h')); ?>"
	<?php if ($pair !== null) { ?>
	data-chart-opponent-id="<?php echo (int) $pair['opponent_id']; ?>"
	data-chart-opponent-name="<?php echo k2_h((string) $pair['opponent_name']); ?>"
	<?php } ?>
>
	<div class="k2-player-opponents-h2h__pickers">
		<div class="k2-player-opponents-h2h__search player-search" role="search">
			<label class="player-search-label" for="<?php echo k2_h($searchUid); ?>">Search</label>
			<input
				id="<?php echo k2_h($searchUid); ?>"
				class="player-search-input k2-header-search__input k2-player-opponents-h2h__search-input"
				type="search"
				maxlength="32"
				autocomplete="off"
				spellcheck="false"
				placeholder="Player name…"
				aria-expanded="false"
				aria-controls="<?php echo k2_h($searchUid); ?>-results"
			/>
			<ul
				id="<?php echo k2_h($searchUid); ?>-results"
				class="player-search-results k2-player-opponents-h2h__search-results"
				role="listbox"
				hidden
			></ul>
		</div>
		<div class="k2-player-opponents-h2h__listbox-wrap">
			<label class="k2-player-opponents-h2h__select-label" for="k2-h2h-games-<?php echo $playerId; ?>-trigger">By games played</label>
			<?php k2_h2h_opponent_listbox_render(
			    'k2-h2h-games-' . $playerId,
			    (string) $selectedOpponentId,
			    $played,
			    'Choose opponent by games played'
			); ?>
		</div>
		<div class="k2-player-opponents-h2h__listbox-wrap">
			<label class="k2-player-opponents-h2h__select-label" for="k2-h2h-alpha-<?php echo $playerId; ?>-trigger">A–Z</label>
			<?php k2_h2h_opponent_listbox_render(
			    'k2-h2h-alpha-' . $playerId,
			    (string) $selectedOpponentId,
			    $byAlpha,
			    'Choose opponent A to Z'
			); ?>
		</div>
	</div>

	<div class="k2-player-opponents-h2h__stage">
		<?php if ($pair === null) { ?>
		<p class="k2-player-opponents-h2h__prompt k2-hub-page-intro">Choose an opponent above to compare head-to-head.</p>
		<?php } else {
		    $subjectCard = player_opponents_h2h_load_player_card($con, $playerId);
		    $opponentCard = player_opponents_h2h_load_player_card($con, $pair['opponent_id']);
		    if ($subjectCard !== null && $opponentCard !== null) {
		        $record = $pair['games'] > 0
		            ? player_opponents_h2h_pair_record($con, $playerId, $pair['opponent_id'])
		            : null;
		        $games = (int) $pair['games'];
		        player_opponents_render_h2h_poster($subjectCard, $opponentCard, $record, $games);
		        if ($games > 0) {
		            $detail = player_opponents_h2h_pair_detail_load($con, $playerId, $pair['opponent_id']);
		            if ($detail !== null) {
		                player_opponents_render_h2h_pair_detail($subjectCard, $opponentCard, $detail);
		            }
		            player_opponents_render_h2h_all_games_link($subjectCard, $opponentCard, $games);
		            $momentGames = player_opponents_h2h_pair_games_rows($con, $playerId, $pair['opponent_id']);
		            $momentSlots = player_opponents_h2h_moments_slots(
		                $momentGames,
		                (string) ($subjectCard['name'] ?? ''),
		                (string) ($opponentCard['name'] ?? '')
		            );
		            player_opponents_render_h2h_moments_grid($momentSlots);
		        }
		    } else { ?>
		<p class="k2-player-opponents-h2h__empty">Could not load player data for this pairing.</p>
		<?php }
		    } ?>
	</div>
	<?php if ($played !== []) {
	    player_opponents_render_h2h_matchup_charts(
	        $playerId,
	        false,
	        $pair !== null ? (string) $pair['opponent_name'] : null,
	        $pair !== null ? (int) $pair['opponent_id'] : null,
	        $playerName
	    );
	} ?>
</div>
    <?php
}
