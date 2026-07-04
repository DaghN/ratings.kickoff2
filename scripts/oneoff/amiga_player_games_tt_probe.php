<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_player_games_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_games_filter_facets.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_load.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$playerId = 382;
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');

foreach (['present', 'year:2024', 'month:2014-07'] as $as) {
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    echo "=== {$as} ===\n";
    $filters = amiga_player_games_filters_from_request($con, $playerId, [], $ctx);
    $filterContext = amiga_player_games_filter_context($filters);

    $t0 = microtime(true);
    amiga_player_games_validate_filters_career_wide($con, $playerId, $filters, $ctx);
    echo '  validate_filters_career_wide: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_player_games_load_filter_facets($con, $playerId, $filterContext, $ctx);
    echo '  load_filter_facets: ' . ms($t0) . " ms\n";

    $whereTypes = '';
    $whereParams = [];
    $whereSql = amiga_games_where_clause(
        $playerId, $filters['result'], $filters['opponent'], $filters['tournament'],
        $filters['event'], $filters['country'], $filters['opp_country'], $filters['day'],
        $filters['since'], $filters['until'], $filters['year'],
        $filters['gf'], $filters['ga'], $filters['gs'], $filters['gd'],
        $whereTypes, $whereParams, $ctx
    );
    $fromSql = amiga_rated_games_from_sql($playerId);

    $t0 = microtime(true);
    amiga_games_query_all($con, 'SELECT COUNT(*) AS n ' . $fromSql . ' WHERE ' . $whereSql, $whereTypes, $whereParams);
    echo '  games count: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_games_query_all($con, 'SELECT r.id FROM amiga_games r WHERE 1=0', '', []);
    $rows = amiga_games_query_all($con, 'SELECT r.id ' . $fromSql . ' WHERE ' . $whereSql . ' ORDER BY r.id DESC', $whereTypes, $whereParams);
    echo '  games fetch all: ' . ms($t0) . ' ms (' . count($rows) . " rows)\n";
}
$con->close();