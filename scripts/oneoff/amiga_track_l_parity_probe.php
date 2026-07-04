<?php
declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_filter_facets.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function old_score_line(mysqli $con, array $state, AmigaSnapshotContext $ctx): array {
    if (!amiga_realm_games_all_lean_eligible($state)) {
        return ['skip' => true];
    }
    $types = '';
    $params = [];
    $fromSql = amiga_realm_games_all_lean_from_sql($state, $ctx, $types, $params);
    $sql = 'SELECT gr.goal_difference AS gd, gr.sum_of_goals AS gs, GREATEST(g.goals_a, g.goals_b) AS ts ' . $fromSql;
    $gdSparse = $gsSparse = $tsSparse = [];
    foreach (k2_games_facet_query_rows($con, $sql, $types, $params) as $row) {
        $gd = (int) ($row['gd'] ?? 0);
        $gs = (int) ($row['gs'] ?? 0);
        $ts = (int) ($row['ts'] ?? 0);
        $gdSparse[$gd] = ($gdSparse[$gd] ?? 0) + 1;
        $gsSparse[$gs] = ($gsSparse[$gs] ?? 0) + 1;
        $tsSparse[$ts] = ($tsSparse[$ts] ?? 0) + 1;
    }
    return [
        'gd' => k2_games_facet_expand_numeric_gaps($gdSparse),
        'gs' => k2_games_facet_expand_numeric_gaps($gsSparse),
        'ts' => k2_games_facet_expand_numeric_gaps($tsSparse),
    ];
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
foreach (['present', 'month:2002-06', 'month:2014-07', 'year:2024'] as $as) {
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    $state = amiga_realm_games_all_request_state();
    amiga_realm_games_all_sanitize_filters($con, $state, $ctx);
    $old = old_score_line($con, $state, $ctx);
    $new = amiga_realm_games_facet_score_line_counts_single_pass($con, $state, $ctx);
    $countOld = count(amiga_tournament_index_cached_all_rows($con, $ctx));
    $countNew = amiga_tournament_index_count($con, $ctx);
    $gamesOld = 0;
    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff !== null) {
            $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games g INNER JOIN tournaments t ON t.id = g.tournament_id WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?)');
            if ($stmt) {
                $ed = $cutoff['event_date'];
                $ch = $cutoff['chrono'];
                $tid = $cutoff['tournament_id'];
                $stmt->bind_param('sdi', $ed, $ch, $tid);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : false;
                if ($res) {
                    $res->free();
                }
                $stmt->close();
                $gamesOld = $row !== false ? (int) ($row['n'] ?? 0) : 0;
            }
        }
    } else {
        $res = $con->query('SELECT COUNT(*) AS n FROM amiga_games');
        $row = $res ? $res->fetch_assoc() : false;
        if ($res) {
            $res->free();
        }
        $gamesOld = $row !== false ? (int) ($row['n'] ?? 0) : 0;
    }
    $gamesNew = amiga_lb_games_count($con, $ctx);
    echo "=== {$as} === score_line " . (json_encode($old) === json_encode($new) ? 'OK' : 'DIFF')
        . " tournament_count {$countOld} vs {$countNew} " . ($countOld === $countNew ? 'OK' : 'DIFF')
        . " games_count {$gamesOld} vs {$gamesNew} " . ($gamesOld === $gamesNew ? 'OK' : 'DIFF') . "\n";
}
$con->close();