<?php
/**
 * Shared Amiga Opponents wing page shell.
 * Set $k2AmigaPlayerOpponentsView before require (h2h | wdl | goals | dds).
 * Set $k2AmigaPlayerOpponentsGrain ('player' | 'country') for country-grain pages.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_lib.php';

$k2AmigaPlayerOpponentsView = amiga_player_opponents_parse_view($k2AmigaPlayerOpponentsView ?? null);
$k2AmigaPlayerOpponentsGrain = amiga_player_opponents_parse_grain($k2AmigaPlayerOpponentsGrain ?? null);
$view = $k2AmigaPlayerOpponentsView;
$grain = $k2AmigaPlayerOpponentsGrain;
$viewLabel = amiga_player_opponents_view_label($view);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player opponents</title>
<?php
$k2AmigaPlayerOpponentsLedgerTable = in_array($view, ['wdl', 'goals', 'dds'], true);
if ($k2AmigaPlayerOpponentsLedgerTable) {
    $k2RankedCloak = true;
}
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php';
?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-sections.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-sections.css'); ?>" rel="stylesheet" type="text/css" />
<?php if ($view === 'h2h') { ?>
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-poster.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-poster.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-moments.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-moments.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-scoreline-heatmap.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-scoreline-heatmap.css'); ?>" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/k2-coarse-tap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-coarse-tap.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/player-opponents-h2h-chart-context.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-opponents-h2h-chart-context.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-opponents-h2h.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-opponents-h2h.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-goals-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-goals-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-total-goals-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-total-goals-histogram.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-scoreline-heatmap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-scoreline-heatmap.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rank-chart-core.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rank-chart-core.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-compare-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-compare-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-compare-rank-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-compare-rank-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-goals-scored-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-goals-scored-histogram.js'); ?>" defer="defer"></script>
<?php } elseif ($k2AmigaPlayerOpponentsLedgerTable) { ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<?php } else { ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<?php } ?>
</head>
<body class="k2-site k2-player-wing player-feast-body">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_tables.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_country_tables.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(404);
    exit('Player not found.');
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$ctx = amiga_snapshot_context_from_request($con);

try {
    $pm = amiga_player_load($con, $id);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

amiga_player_publish_hero_context($pm, $con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php';

$k2AmigaPlayerTabActive = 'opponents';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_nav.php';

if ($grain === 'country') {
    if ($view === 'wdl') {
        amiga_player_opponents_render_country_wdl_table($con, $id, $ctx);
    } elseif ($view === 'goals') {
        amiga_player_opponents_render_country_goals_table($con, $id, $ctx);
    } elseif ($view === 'dds') {
        amiga_player_opponents_render_country_dds_table($con, $id, $ctx);
    } elseif ($view === 'h2h') {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_country_h2h.php';
        $h2hCountryToken = amiga_player_opponents_h2h_parse_country_param($_GET['country'] ?? null);
        $h2hDefaultCountry = !array_key_exists('country', $_GET);
        $h2hPickSource = amiga_player_opponents_h2h_parse_pick_source($_GET['pick'] ?? null);
        if ($h2hPickSource === null && ($h2hCountryToken !== '' || $h2hDefaultCountry)) {
            $h2hPickSource = 'games';
        }
        $h2hPlayerName = (string) ($pm['name'] ?? ('#' . $id));
        amiga_player_opponents_render_country_h2h_panel(
            $con,
            $id,
            $h2hPlayerName,
            $h2hCountryToken,
            $h2hDefaultCountry,
            $h2hPickSource,
            $ctx
        );
    } else {
        ?>
<p class="k2-hub-page-intro k2-player-opponents__country-placeholder">Country opponents — <?php echo htmlspecialchars($viewLabel, ENT_QUOTES, 'UTF-8'); ?> is not available.</p>
        <?php
    }
} elseif ($view === 'h2h') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_opponents_h2h.php';
    $h2hOpponentId = amiga_player_opponents_h2h_parse_opponent_id($_GET['opponent'] ?? null, $id);
    $h2hDefaultOpponent = !array_key_exists('opponent', $_GET);
    $h2hPickSource = amiga_player_opponents_h2h_parse_pick_source($_GET['pick'] ?? null);
    if ($h2hPickSource === null && ($h2hOpponentId > 0 || $h2hDefaultOpponent)) {
        $h2hPickSource = 'games';
    }
    $h2hPlayerName = (string) ($pm['name'] ?? ('#' . $id));
    amiga_player_opponents_render_h2h_panel(
        $con,
        $id,
        $h2hPlayerName,
        $h2hOpponentId,
        $h2hDefaultOpponent,
        $h2hPickSource,
        $ctx
    );
} elseif ($view === 'goals') {
    amiga_player_opponents_render_goals_table($con, $id, $ctx);
} elseif ($view === 'dds') {
    amiga_player_opponents_render_dds_table($con, $id, $ctx);
} else {
    amiga_player_opponents_render_wdl_table($con, $id, $ctx);
}

mysqli_close($con);
?>

</div><!-- .k2-chrome-tabs.k2-player-opponents -->

</div><!-- .k2-page-nav -->

</body>
</html>