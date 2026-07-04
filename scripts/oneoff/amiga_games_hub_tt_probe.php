<?php
declare(strict_types=1);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_lib.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_highlights_helpers.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';
function ms(float $t0): float { return round((microtime(true)-$t0)*1000,1); }
$con = new mysqli($dbhost,$username,$password,$database,$dbportnum);
foreach (['present','year:2024'] as $as) {
  if ($as==='present') { amiga_snapshot_context_reset(); $ctx=AmigaSnapshotContext::present(); }
  else { $_GET['as']=$as; amiga_snapshot_context_reset(); $ctx=amiga_snapshot_context_from_request($con); }
  echo "=== $as ===\n";
  $t0=microtime(true); $tournaments=amiga_games_hub_recent_tournaments($con,$ctx); echo '  recent_tournaments: '.ms($t0)." ms\n";
  $t0=microtime(true); amiga_games_hub_recent_games_by_tournament($con,$tournaments,$ctx); echo '  recent_games_batch: '.ms($t0)." ms\n";
  $t0=microtime(true); amiga_games_hub_status_counts($con,$ctx); echo '  hub_status_counts: '.ms($t0)." ms\n";
  foreach (['most_goals','biggest_upsets'] as $board) {
    $t0=microtime(true); amiga_games_highlights_fetch($con,$board,$ctx); echo "  highlights_$board: ".ms($t0)." ms\n";
  }
}
$con->close();