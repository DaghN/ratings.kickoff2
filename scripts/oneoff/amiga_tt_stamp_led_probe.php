<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_time_travel_stamp.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');

function led_text(?array $stamp): string
{
    if ($stamp === null) {
        return '';
    }

    $parts = [];
    foreach ($stamp['led'] as $part) {
        $parts[] = (string) $part['text'];
    }

    return implode('', $parts);
}

function assert_stamp_for_as(mysqli $con, string $as, string $expectedLed): void
{
    $view = amiga_snapshot_resolve_as($con, $as);
    if ($view === null) {
        throw new RuntimeException("resolve failed: {$as}");
    }
    amiga_snapshot_context_reset();
    $GLOBALS['_amiga_snapshot_context'] = AmigaSnapshotContext::fromCatalogView($view);
    $stamp = amiga_time_travel_stamp_view();
    $actual = led_text($stamp);
    echo "{$as} led={$actual} a11y={$stamp['a11y']}\n";
    if ($actual !== $expectedLed) {
        throw new RuntimeException("expected led {$expectedLed}, got {$actual}");
    }
}

$months = amiga_rating_history_catalog_month($con);
foreach ($months as $m) {
    if (empty($m['has_finalize_in_period']) && !empty($m['cutoff_tournament_id'])) {
        $key = (string) $m['key'];
        $expected = substr($key, 5, 2) . '.' . substr($key, 0, 4);
        echo "quiet_month key={$key} cutoff_event_date={$m['cutoff_event_date']}\n";
        assert_stamp_for_as($con, 'month:' . $key, $expected);
        break;
    }
}

$years = amiga_rating_history_catalog_year($con);
foreach ($years as $y) {
    if (empty($y['has_finalize_in_period']) && !empty($y['cutoff_tournament_id'])) {
        $key = (string) $y['key'];
        echo "quiet_year key={$key} cutoff_event_date={$y['cutoff_event_date']}\n";
        assert_stamp_for_as($con, 'year:' . $key, $key);
        break;
    }
}

$con->close();
echo "OK\n";