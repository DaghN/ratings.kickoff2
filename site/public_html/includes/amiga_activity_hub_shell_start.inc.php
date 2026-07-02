<?php
/**
 * Activity hub chrome — open through <main> (wing nav included).
 *
 * Set $k2AmigaActivityWingView: growth | people | geography | world-cups | texture | shape
 * When 'geography': set $k2AmigaActivityGeographyView: hosts | nations
 * Optional: $k2AmigaActivityPageTitle
 *
 * @see docs/amiga-activity-charts-policy.md
 */
declare(strict_types=1);

$k2AmigaActivityWingView = $k2AmigaActivityWingView ?? 'growth';
$k2AmigaActivityPageTitle = $k2AmigaActivityPageTitle ?? 'Activity';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — <?php echo htmlspecialchars($k2AmigaActivityPageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script type="text/javascript" src="/js/amiga-activity-charts.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-activity-charts.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-activity-charts k2-amiga-activity-charts">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'activity';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_community_stats_lib.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$k2ActCtx = amiga_snapshot_context_from_request($con);
$k2ActCutoffEntry = $k2ActCtx->isActive() ? $k2ActCtx->cutoff() : null;
$k2ActCutoffTid = $k2ActCutoffEntry !== null ? (int) $k2ActCutoffEntry['tournament_id'] : null;

$k2ActHeadline = amiga_community_headline_load($con, $k2ActCutoffTid);
$k2HubChapterLede = 'Community statistics are not available yet.';
if ($k2ActHeadline !== null) {
    $k2ActPlayers = (int) ($k2ActHeadline['NumberOfPlayers'] ?? 0);
    $k2ActGames = (int) ($k2ActHeadline['GamesPlayed'] ?? 0);
    $k2ActCountries = amiga_lb_rated_country_count($con, $k2ActCtx);
    $k2ActTournaments = amiga_tournament_index_count($con, $k2ActCtx);

    $k2ActKoaStartYear = 2001;
    $k2ActYearSpan = (int) date('Y') - $k2ActKoaStartYear;
    $k2ActLedeOpen = $k2ActYearSpan === 1
        ? 'One year of the KOA: Since ' . $k2ActKoaStartYear . ', '
        : $k2ActYearSpan . ' years of the KOA: Since ' . $k2ActKoaStartYear . ', ';
    $k2HubChapterLede = htmlspecialchars($k2ActLedeOpen, ENT_QUOTES, 'UTF-8')
        . '<span class="blue">' . number_format($k2ActPlayers) . '</span> players from '
        . '<span class="blue">' . number_format($k2ActCountries) . '</span> ' . ($k2ActCountries === 1 ? 'nation' : 'nations') . ' have played '
        . '<span class="blue">' . number_format($k2ActGames) . '</span> rated games in '
        . '<span class="blue">' . number_format($k2ActTournaments) . '</span> ' . ($k2ActTournaments === 1 ? 'tournament' : 'tournaments') . '.';
}
mysqli_close($con);
unset($con);

$k2HubChapterTitle = 'Activity';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
$k2AmigaActivitySummaryHideLede = true;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_summary.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_hub_nav.php';
if ($k2AmigaActivityWingView === 'geography') {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_activity_geography_nav.php';
}
?>

<main class="k2-amiga-activity-hub" id="main">