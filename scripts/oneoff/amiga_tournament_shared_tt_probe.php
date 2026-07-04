<?php
declare(strict_types=1);
require_once __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_bracket.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

foreach ([589, 603] as $tid) {
    echo "=== tournament {$tid} ===\n";
    $t0 = microtime(true);
    amiga_tournament_load($con, $tid);
    echo '  load: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    $ll = amiga_tournament_list_league_labeled_scopes($con, $tid);
    echo '  league scopes: ' . ms($t0) . ' ms (' . count($ll) . ")\n";

    $t0 = microtime(true);
    $ko = amiga_tournament_list_scopes($con, $tid, 'knockout');
    echo '  ko scopes: ' . ms($t0) . ' ms (' . count($ko) . ")\n";

    $t0 = microtime(true);
    amiga_tournament_standings_rows($con, $tid, 'league', '');
    echo '  implicit league: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    $part = amiga_tournament_participation_rows($con, $tid);
    echo '  participation: ' . ms($t0) . ' ms (' . count($part) . ")\n";

    $t0 = microtime(true);
    $gc = amiga_tournament_game_count($con, $tid);
    echo '  game_count: ' . ms($t0) . " ms ({$gc})\n";

    $t0 = microtime(true);
    amiga_tournament_winner($con, $tid);
    echo '  winner: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_tournament_knockout_bracket_data($con, $tid, $ko);
    echo '  bracket: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    $games = amiga_tournament_games_rows($con, $tid, 0);
    echo '  games_rows: ' . ms($t0) . ' ms (' . count($games) . ")\n";

    $t0 = microtime(true);
    amiga_tournament_step_catalog($con);
    echo '  step_catalog: ' . ms($t0) . " ms\n";

    $t0 = microtime(true);
    amiga_tournament_step_player_choices($con);
    echo '  step_player_choices: ' . ms($t0) . " ms\n";
}

foreach (['present', 'year:2024', 'month:2014-07'] as $as) {
    echo "=== catalog {$as} ===\n";
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    $t0 = microtime(true);
    $rows = amiga_tournament_index_rows($con, 0, 0, $ctx);
    echo '  index_rows: ' . ms($t0) . ' ms (' . count($rows) . ")\n";

    $t0 = microtime(true);
    $n = amiga_tournament_index_count($con, $ctx);
    echo '  index_count (2nd): ' . ms($t0) . " ms ({$n})\n";
}

$con->close();