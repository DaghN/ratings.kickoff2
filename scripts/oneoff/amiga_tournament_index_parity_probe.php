<?php
declare(strict_types=1);
require_once __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function index_rows_legacy(mysqli $con, ?AmigaSnapshotContext $ctx): array
{
    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params);
    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, t.is_cup, t.has_league, t.has_cup, t.equal_teams, t.country, t.player_count,
                   t.lifecycle_status,
                   COALESCE(c.game_count, 0) AS game_count,
                   COALESCE(c.standing_players, 0) AS standing_players,
                   COALESCE(c.standing_rows, 0) AS standing_rows,
                   COALESCE(c.league_scopes, 0) AS league_scopes,
                   COALESCE(c.knockout_ties, 0) AS knockout_ties,
                   COALESCE(c.has_perfect_participant, 0) AS has_perfect_participant,
                   wp.id AS winner_player_id,
                   wp.name AS winner_name,
                   wp.country AS winner_country
            FROM tournaments t
            LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id = t.id
            LEFT JOIN (
                SELECT tournament_id, MIN(player_id) AS player_id
                FROM amiga_player_event_snapshots
                WHERE is_winner = 1
                GROUP BY tournament_id
            ) win ON win.tournament_id = t.id
            LEFT JOIN amiga_players wp ON wp.id = win.player_id
            WHERE ' . amiga_tournament_public_visibility_where('t') . $cutoffSql . '
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC, t.name ASC';
    if ($types === '') {
        $res = mysqli_query($con, $sql);
    } else {
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    if (isset($res) && $res) {
        mysqli_free_result($res);
    }
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }

    return $rows;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$fail = 0;
foreach (['present', 'year:2001', 'month:2002-06', 'month:2014-07', 'event:589', 'year:2024'] as $as) {
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    $old = index_rows_legacy($con, $ctx);
    $new = amiga_tournament_index_cached_all_rows($con, $ctx);
    $oldJson = json_encode(array_map(static fn ($r) => array_map('strval', $r), $old));
    $newJson = json_encode(array_map(static fn ($r) => array_map('strval', $r), $new));
    if ($oldJson !== $newJson) {
        echo "FAIL {$as} rows old=" . count($old) . ' new=' . count($new) . "\n";
        $fail++;
    }
}
echo $fail === 0 ? "index catalog parity: PASS\n" : "index catalog parity: {$fail} FAIL\n";
$con->close();