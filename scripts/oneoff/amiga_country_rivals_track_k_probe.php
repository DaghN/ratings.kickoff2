<?php
declare(strict_types=1);
require __DIR__ . "/../../site/public_html/includes/amiga_lb_lib.php";
require __DIR__ . "/../../site/public_html/includes/amiga_country_rivals_load.php";
require __DIR__ . "/../../site/public_html/includes/amiga_country_rivals_h2h.php";
require __DIR__ . "/../../site/public_html/includes/amiga_country_rivals_perf_lib.php";
include __DIR__ . "/../../site/config/ko2amiga_config.php";
function ms(float $s): float { return round((microtime(true)-$s)*1000,1); }
function bench(string $l, callable $fn): void { $t=microtime(true); $fn(); echo "$l: ".ms($t)." ms\n"; }
$con = new mysqli($dbhost,$username,$password,$database,$dbportnum);
$con->set_charset("utf8mb4"); $con->query("SET time_zone = '+00:00'");
$scenarios = [
  "h2h present" => ["as"=>"", "hero"=>"England", "rival"=>"Italy", "mode"=>"h2h"],
  "h2h year:2024" => ["as"=>"year:2024", "hero"=>"England", "rival"=>"Italy", "mode"=>"h2h"],
  "wdl present" => ["as"=>"", "hero"=>"Germany", "rival"=>"", "mode"=>"wdl"],
  "wdl year:2024" => ["as"=>"year:2024", "hero"=>"Germany", "rival"=>"", "mode"=>"wdl"],
];
foreach ($scenarios as $label => $s) {
  echo "\n=== $label ===\n";
  if ($s["as"] !== "") { $_GET["as"]=$s["as"]; } else { unset($_GET["as"]); }
  $GLOBALS["_amiga_snapshot_context"]=null;
  $ctx = amiga_lb_context($con);
  $hero = $s["hero"];
  bench("country_summary", fn()=>amiga_countries_query_country_summary($con,$ctx,$hero));
  bench("rivals_rows", fn()=>amiga_country_rivals_rows($con,$hero,$ctx,$s["mode"]==="wdl"));
  if ($s["mode"]==="h2h") {
    bench("pair_game_rows_raw", fn()=>amiga_country_rivals_h2h_game_rows_raw($con,$hero,$s["rival"],$ctx));
    bench("moments_slots", fn()=>amiga_country_rivals_h2h_moments_slots($con,$hero,$s["rival"],$ctx));
    bench("cumulative_payload", fn()=>amiga_country_rivals_h2h_cumulative_payload($con,$hero,$s["rival"],$ctx));
  } else {
    bench("perf_batch", fn()=>amiga_country_rivals_perf_ratings_batch($con,$hero,$ctx));
  }
}
$con->close();
echo "\nOK\n";