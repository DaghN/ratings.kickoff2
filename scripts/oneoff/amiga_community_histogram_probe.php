<?php
declare(strict_types=1);

/**
 * Slice-8 Shape histogram oracle probe — four cutoffs × nine kinds.
 *
 * Usage: php scripts/oneoff/amiga_community_histogram_probe.php
 */

require __DIR__ . '/../../site/public_html/includes/amiga_community_histogram_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$res = $con->query(
    'SELECT tournament_id FROM amiga_community_stats_snapshots '
    . 'ORDER BY event_chrono ASC, event_date ASC, tournament_id ASC LIMIT 1'
);
if ($res === false) {
    fwrite(STDERR, "first snapshot query failed: {$con->error}\n");
    exit(1);
}
$row = $res->fetch_assoc();
$res->free();
if ($row === null) {
    fwrite(STDERR, "no community stats snapshots\n");
    exit(1);
}
$firstTid = (int) ($row['tournament_id'] ?? 0);
if ($firstTid < 1) {
    fwrite(STDERR, "invalid first snapshot tournament_id\n");
    exit(1);
}

/** @var list<array{key: string, as: string|null}> */
$cutoffs = [
    ['key' => 'first_event', 'as' => 'event:' . $firstTid],
    ['key' => 'year_2007', 'as' => 'year:2007'],
    ['key' => 'year_2015', 'as' => 'year:2015'],
    ['key' => 'present', 'as' => null],
];

echo "| kind | cutoff | ms | population | oracle | max_value |\n";
echo "| --- | --- | ---: | ---: | --- | ---: |\n";

foreach ($cutoffs as $cutoffSpec) {
    amiga_snapshot_context_reset();
    $_GET = [];
    if ($cutoffSpec['as'] !== null) {
        $_GET['as'] = $cutoffSpec['as'];
    }
    $ctx = amiga_snapshot_context_from_request($con);
    $bind = amiga_community_histogram_cutoff_bind($ctx);
    $cutoffLabel = $cutoffSpec['key'] . ' (' . $bind['label'] . ')';

    foreach (AMIGA_COMMUNITY_HISTOGRAM_KINDS as $kind) {
        $probe = amiga_community_histogram_probe($con, $kind, $ctx);
        $probe['cutoff'] = $cutoffLabel;
        echo '| '
            . $probe['kind'] . ' | '
            . $probe['cutoff'] . ' | '
            . number_format($probe['ms'], 2, '.', '') . ' | '
            . $probe['population'] . ' | '
            . $probe['oracle'] . ' | '
            . $probe['max_value'] . " |\n";
    }
}

exit(0);
