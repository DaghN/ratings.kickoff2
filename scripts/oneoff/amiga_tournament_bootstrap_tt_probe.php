<?php
declare(strict_types=1);
/** Simulates amiga_tournament_page.php shared bootstrap per view (lib timing only). */
require_once __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_bracket.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_href.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $t0): float { return round((microtime(true) - $t0) * 1000, 1); }

function bootstrap_tournament(mysqli $con, int $id, string $pageView): float
{
    $tAll = microtime(true);
    $tournament = amiga_tournament_load($con, $id);
    if ($tournament === null) {
        return ms($tAll);
    }
    $scopeType = 'league';
    $scopeKey = '';
    $leagueLabeledScopes = amiga_tournament_list_league_labeled_scopes($con, $id);
    $knockoutScopes = amiga_tournament_list_scopes($con, $id, 'knockout');
    $implicitLeagueRows = amiga_tournament_standings_rows($con, $id, 'league', '');
    $rows = amiga_tournament_standings_rows($con, $id, $scopeType, $scopeKey);
    $isWorldCupEvent = amiga_tournament_is_world_cup($tournament);
    $hasBracket = $knockoutScopes !== [];
    $isKnockoutView = $scopeType === 'knockout';
    if ($hasBracket && $isKnockoutView) {
        amiga_tournament_knockout_bracket_data($con, $id, $knockoutScopes);
    }
    amiga_tournament_participation_rows($con, $id);
    amiga_tournament_game_count($con, $id);
    amiga_tournament_winner($con, $id);
    if ($pageView === 'games') {
        amiga_tournament_games_rows($con, $id, 0);
    }
    $intent = amiga_tournament_step_nav_intent_from_request($scopeType, $scopeKey, $pageView, null);
    amiga_tournament_step_catalog($con);
    amiga_tournament_step_player_choices($con);
    amiga_tournament_step_country_choices($con);
    amiga_tournament_step_keys($con, amiga_tournament_step_catalog($con), $id, amiga_tournament_step_filter_bag_from_request($con));

    return ms($tAll);
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$fixtures = [
    ['id' => 589, 'view' => 'event-stats'],
    ['id' => 589, 'view' => 'games'],
    ['id' => 589, 'view' => 'stages'],
    ['id' => 603, 'view' => 'stages'],
];

foreach (['present', 'year:2024'] as $as) {
    echo "=== cutoff {$as} ===\n";
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        amiga_snapshot_context_from_request($con);
    }
    foreach ($fixtures as $fx) {
        amiga_snapshot_context_reset();
        if ($as !== 'present') {
            $_GET['as'] = $as;
            amiga_snapshot_context_from_request($con);
        }
        $ms = bootstrap_tournament($con, $fx['id'], $fx['view']);
        echo "  id={$fx['id']} {$fx['view']}: {$ms} ms\n";
    }
    amiga_snapshot_context_reset();
    if ($as !== 'present') {
        $_GET['as'] = $as;
        amiga_snapshot_context_from_request($con);
    }
    $t0 = microtime(true);
    amiga_tournament_index_rows($con, 0, 0);
    echo '  catalog index_rows: ' . ms($t0) . " ms\n";
}

$con->close();