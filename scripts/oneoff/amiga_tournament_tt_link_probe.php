<?php
declare(strict_types=1);

$_GET = ['id' => '5', 'as' => 'event:5'];
$_SERVER['REQUEST_URI'] = '/amiga/tournament.php?id=5&as=event%3A5';

require __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_snapshot_chrome.php';

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

echo "tournament_tt_href_ok\n";
echo $href . PHP_EOL;
