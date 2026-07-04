<?php
declare(strict_types=1);
require_once __DIR__ . '/../../site/public_html/includes/amiga_db.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function rows_json(array $rows): string {
    return json_encode(array_map(static fn ($r) => array_map('strval', $r), $rows), JSON_THROW_ON_ERROR);
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$fail = 0;

// game load parity (scoped inner scan)
$oldSql = 'SELECT r.id, r.Date, r.idA, r.NameA, r.idB, r.NameB, r.RatingA, r.RatingB, r.RatingDifference, '
    . 'r.GoalsA, r.GoalsB, r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB, '
    . 'r.SumOfGoals, r.GoalDifference, r.phase, r.tournament_id, r.tournament_name, '
    . 'r.country_a, r.country_b, r.tournament_country '
    . amiga_rated_games_from_sql()
    . ' WHERE r.id = ? LIMIT 1';
$newRow = amiga_rated_game_load($con, 27418);
$stmt = $con->prepare($oldSql);
$gid = 27418;
$stmt->bind_param('i', $gid);
$stmt->execute();
$oldRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (json_encode($oldRow) !== json_encode($newRow)) {
    echo "FAIL game_load 27418\n";
    $fail++;
} else {
    echo "PASS game_load 27418\n";
}

// videos games_by_ids parity @ tournament 9 (local manifest with game_ids)
$tid = 9;
$rows9 = amiga_tournament_videos_for_id($tid);
[$match9] = amiga_tournament_videos_partition($rows9);
$gameIds = [];
foreach ($match9 as $v) {
    foreach (($v['game_ids'] ?? []) as $gid) { $gameIds[] = (int) $gid; }
}
$gameIds = array_values(array_unique(array_filter($gameIds)));
if ($gameIds === []) {
    echo "SKIP videos_games_by_ids (no game_ids in manifest)\n";
} else {
    $newGames = amiga_tournament_videos_games_by_ids($con, $tid, $gameIds);
    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $oldFrom = amiga_rated_games_from_sql();
    $types = 'i' . str_repeat('i', count($gameIds));
    $params = array_merge([$tid], $gameIds);
    $sql = 'SELECT r.id, r.`Date`, r.idA, r.NameA, r.idB, r.NameB, r.phase,
                   r.GoalsA, r.GoalsB, r.RatingA, r.RatingB, r.RatingDifference,
                   r.ExpectedScoreA, r.ExpectedScoreB, r.ActualScore, r.AdjustmentA, r.AdjustmentB,
                   r.NewRatingA, r.NewRatingB, r.SumOfGoals, r.GoalDifference,
                   r.country_a, r.country_b '
        . $oldFrom . ' WHERE r.tournament_id = ? AND r.id IN (' . $placeholders . ')';
    $stmt = $con->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $oldGames = [];
    while ($r = $res->fetch_assoc()) { $oldGames[(int)$r['id']] = $r; }
    $stmt->close();
    if (rows_json(array_values($oldGames)) !== rows_json(array_values($newGames))) {
        echo "FAIL videos_games_by_ids {$tid}\n";
        $fail++;
    } else {
        echo "PASS videos_games_by_ids {$tid}\n";
    }
}

// player tournaments knockout_ties parity
$cutoffs = ['present' => null, 'year:2024' => 'year:2024', 'month:2014-07' => 'month:2014-07'];
foreach ($cutoffs as $label => $as) {
    amiga_snapshot_context_reset();
    if ($as !== null) {
        $_GET['as'] = $as;
        amiga_snapshot_context_from_request($con);
    }
    $newRows = amiga_player_tournament_participation_all($con, 382);
    $types = 'i';
    $params = [382];
    $cutoffSql = '';
    $ctx = amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $cutoffSql = amiga_snapshot_event_tuple_cutoff_and_sql($cutoff, $types, $params, 'p.event_date', 'p.event_chrono', 'p.tournament_id');
        }
    }
    $oldSql = 'SELECT p.tournament_id AS id, p.tournament_name AS name, p.event_date, p.event_chrono, p.is_cup, p.has_league, p.has_cup, p.country,
               p.event_finish_position AS position, p.event_points, p.games, p.wins, p.draws, p.losses, p.goals_for, p.goals_against,
               p.avg_goals_for, p.avg_goals_against, p.rating_before, p.rating_delta, p.rating_after, p.performance_rating,
               p.is_winner, p.is_perfect_event,
               (SELECT COUNT(DISTINCT sk.scope_key) FROM amiga_tournament_standings sk WHERE sk.tournament_id = p.tournament_id AND sk.scope_type = \'knockout\') AS knockout_ties
               FROM amiga_player_event_snapshots p INNER JOIN tournaments t ON t.id = p.tournament_id
               WHERE p.player_id = ? AND ' . amiga_tournament_public_visibility_where('t') . $cutoffSql . '
               ORDER BY COALESCE(p.event_chrono, 999999) DESC, COALESCE(p.event_date, \'1970-01-01\') DESC, p.tournament_id DESC';
    $stmt = $con->prepare($oldSql);
    $refs = [];
    foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
    $stmt->bind_param($types, ...$refs);
    $stmt->execute();
    $res = $stmt->get_result();
    $oldRows = [];
    while ($r = $res->fetch_assoc()) { $oldRows[] = $r; }
    $stmt->close();
    if (rows_json($oldRows) !== rows_json($newRows)) {
        echo "FAIL player_tournaments 382 {$label}\n";
        $fail++;
    } else {
        echo "PASS player_tournaments 382 {$label} (" . count($newRows) . " rows)\n";
    }
}

$con->close();
exit($fail > 0 ? 1 : 0);