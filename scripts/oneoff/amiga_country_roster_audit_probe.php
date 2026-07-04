<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_countries_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $start): float { return round((microtime(true) - $start) * 1000, 1); }
function bench(string $label, callable $fn): mixed {
    $t0 = microtime(true);
    $r = $fn();
    echo $label . ': ' . ms($t0) . " ms\n";
    return $r;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");
$country = 'Greece';

foreach (['present', 'month:2025-09', 'month:2002-01', 'event:589'] as $as) {
    echo "\n=== Roster audit: {$country} as=" . ($as === 'present' ? '(none)' : $as) . " ===\n";
    if ($as === 'present') { unset($_GET['as']); } else { $_GET['as'] = $as; }
    $GLOBALS['_amiga_snapshot_context'] = null;

    bench('header_snapshot_context', fn () => amiga_snapshot_context_from_request($con));
    $ctx = amiga_snapshot_context_from_request($con);

    bench('query_roster_rows_new', fn () => amiga_countries_query_roster_rows($con, $ctx, $country));
    $all = bench('player_rows_all_countries_legacy', fn () => amiga_countries_player_rows($con, $ctx));
    echo '  all_players=' . count($all) . "\n";
    bench('index_rows_php_rollup', fn () => amiga_countries_index_rows($all));
    $roster = bench('roster_rows_php_filter', fn () => amiga_countries_roster_rows($all, $country));
    echo '  greece_roster=' . count($roster) . "\n";

    $t0 = microtime(true);
    $cutoff = $ctx->cutoff();
    $tokenSql = amiga_countries_token_sql('p');
    $sliceKey = amiga_slice_key_world_cup();
    $sliceJoin = amiga_slice_at_cutoff_join_sql();
    if (!$ctx->isActive()) {
        $sql = 'SELECT COUNT(*) AS n ' . amiga_player_base_from_sql($con, 's')
            . ' LEFT JOIN amiga_player_slice_totals wcs ON wcs.player_id = p.id AND wcs.slice_key = ? '
            . ' WHERE s.NumberGames > 0 AND ' . $tokenSql . ' = ?';
        $stmt = $con->prepare($sql);
        $stmt->bind_param('ss', $sliceKey, $country);
    } else {
        $sql = 'SELECT COUNT(*) AS n ' . amiga_lb_snapshot_from_sql('s') . ' '
            . str_replace('t.player_id', 'p.id', $sliceJoin['sql']) . ' '
            . ' WHERE s.NumberGames > 0 AND ' . $tokenSql . ' = ?';
        $stmt = $con->prepare($sql);
        $eventDate = $cutoff['event_date']; $chrono = $cutoff['chrono']; $tid = $cutoff['tournament_id'];
        $stmt->bind_param('sdissdis', $eventDate, $chrono, $tid, $sliceKey, $eventDate, $chrono, $tid, $country);
    }
    $stmt->execute();
    $n = (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();
    echo 'greece_only_sql_count: ' . ms($t0) . " ms (n={$n})\n";
}

$con->close();
echo "OK\n";