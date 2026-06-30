<?php
declare(strict_types=1);

$_GET = ['id' => '354', 'as' => 'event:94'];
$_SERVER['REQUEST_URI'] = '/amiga/player/tournaments.php?id=354&as=event%3A94';

require __DIR__ . '/../../site/config/ko2amiga_config.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_context.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_snapshot_url.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';

$con = mysqli_connect($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if (!$con instanceof mysqli) {
    fwrite(STDERR, "db connect failed\n");
    exit(1);
}
amiga_snapshot_context_from_request($con);
$ctx = amiga_snapshot_context_peek();
if (!$ctx instanceof AmigaSnapshotContext || !$ctx->isActive() || $ctx->wing() !== 'event') {
    fwrite(STDERR, "expected active event wing context\n");
    exit(1);
}

$otherId = 5;
$listHref = amiga_tournament_href(amiga_tournament_event_stats_url($otherId));
if (!preg_match('/[?&]id=' . $otherId . '(?:&|$)/', $listHref)) {
    fwrite(STDERR, "tournament href missing id={$otherId}: {$listHref}\n");
    exit(1);
}
if (!str_contains($listHref, 'as=event%3A94') && !str_contains($listHref, 'as=event:94')) {
    fwrite(STDERR, "tournament href should preserve active as=event:94: {$listHref}\n");
    exit(1);
}
if (str_contains($listHref, 'as=event%3A' . $otherId) || str_contains($listHref, 'as=event:' . $otherId)) {
    fwrite(STDERR, "tournament href must not rewrite as= to linked tournament id: {$listHref}\n");
    exit(1);
}

$_GET = ['id' => '5', 'as' => 'event:5'];
$_SERVER['REQUEST_URI'] = '/amiga/tournament.php?id=5&as=event%3A5';
amiga_snapshot_context_reset();
amiga_snapshot_context_from_request($con);

$href = amiga_tournament_href(amiga_tournament_event_stats_url(5));
if (!str_contains($href, 'as=event')) {
    fwrite(STDERR, "tournament href missing as: {$href}\n");
    exit(1);
}

$stepperHref = amiga_url_with_as_param(amiga_tournament_url(5), 'event:5') . '#tournament';
if (!str_contains($stepperHref, 'as=event')) {
    fwrite(STDERR, "stepper href missing as: {$stepperHref}\n");
    exit(1);
}

$_GET = ['id' => '5', 'as' => 'event:6'];
$_SERVER['REQUEST_URI'] = '/amiga/tournament/event-stats.php?id=5&as=event%3A6';

$chevronHref = amiga_snapshot_chrome_nav_href('/amiga/tournament/event-stats.php', 'event:6', 'event');
if (!preg_match('/[?&]id=5(?:&|$)/', $chevronHref)) {
    fwrite(STDERR, "event chevron href should keep page id=5: {$chevronHref}\n");
    exit(1);
}
if (!str_contains($chevronHref, 'as=event%3A6') && !str_contains($chevronHref, 'as=event:6')) {
    fwrite(STDERR, "event chevron href missing as=event:6: {$chevronHref}\n");
    exit(1);
}

$yearHref = amiga_snapshot_chrome_nav_href('/amiga/tournament/event-stats.php', 'year:2003', 'year');
if (preg_match('/[?&]id=6(?:&|$)/', $yearHref)) {
    fwrite(STDERR, "year wing should not rewrite tournament id: {$yearHref}\n");
    exit(1);
}

echo "tournament_tt_href_ok\n";
echo $href . PHP_EOL;
echo $chevronHref . PHP_EOL;
