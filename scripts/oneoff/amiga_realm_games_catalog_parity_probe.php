<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_realm_games_all.php';
require __DIR__ . '/../../site/public_html/includes/amiga_realm_games_filter_facets.php';
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$state = amiga_realm_games_all_request_state();
foreach (['year:2024', 'month:2014-07', 'month:2002-06'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);
    $types = ''; $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql($ctx, $types, $params, 't.event_date', 't.chrono', 't.id');
    $catCount = (int)(amiga_realm_games_hub_query_all($con, 'SELECT COALESCE(SUM(c.game_count),0) AS n FROM tournaments t LEFT JOIN amiga_tournament_catalog_stats c ON c.tournament_id=t.id WHERE '.amiga_tournament_public_visibility_where('t').$cutoffSql, $types, $params)[0]['n'] ?? 0);
    $gameCount = amiga_realm_games_all_count($con, $state, $ctx);
    echo "$as count cat=$catCount game=$gameCount " . ($catCount === $gameCount ? 'OK' : 'DIFF') . "\n";
}
$con->close();