<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player videos</title>
<?php
$k2ScrollTargetId = (isset($_GET['v']) && (string) $_GET['v'] !== '') ? 'k2-tournament-video-player' : '';
?>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="/stylesheets/amiga-tournament-videos.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament-videos.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/amiga-tournament-videos.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-tournament-videos.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-player-wing player-feast-body">

<?php
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_videos_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_videos_render.inc.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

include __DIR__ . '/../../../config/ko2amiga_config.php';

if (!amiga_player_has_videos($playerId)) {
    http_response_code(404);
    exit('Player not found.');
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

try {
    $pm = amiga_player_load($con, $playerId);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$ctx = amiga_snapshot_context_from_request($con);
$playerVideoEntriesAll = amiga_player_videos_game_index($con, $playerId, $ctx);
$opponentFacets = amiga_player_videos_opponent_facets($playerVideoEntriesAll, $playerId);
$opponentFilter = amiga_player_videos_validate_opponent_filter(
    amiga_player_videos_opponent_from_request(),
    $opponentFacets,
);
$deepLinkParams = amiga_tournament_videos_wc_request_params();
if ($opponentFilter > 0 && $deepLinkParams['game'] > 0) {
    foreach ($playerVideoEntriesAll as $entry) {
        if ((int) $entry['game_id'] === $deepLinkParams['game']) {
            if (amiga_player_videos_entry_opponent_id($entry, $playerId) !== $opponentFilter) {
                $opponentFilter = 0;
            }
            break;
        }
    }
}
$playerVideoEntries = amiga_player_videos_filter_by_opponent($playerVideoEntriesAll, $playerId, $opponentFilter);
$opponentChoices = amiga_player_videos_opponent_listbox_choices($opponentFacets);
$indexUrl = amiga_player_videos_url(
    $playerId,
    null,
    null,
    null,
    false,
    $opponentFilter > 0 ? $opponentFilter : null,
);
$spotlight = amiga_player_videos_spotlight_state($playerVideoEntriesAll);
$spotlightEntry = $spotlight['entry'];
$spotlightYoutube = $spotlight['youtube_id'];
$spotlightLabel = $spotlight['label'];
$spotlightStartSec = $spotlight['start_sec'];
$highlightRow = $spotlight['highlight_row'];

amiga_player_publish_hero_context($pm, $con);
$name = $Name;

mysqli_close($con);

$k2AmigaPlayerTabActive = 'videos';
$k2AmigaPlayerTabWiredAtCutoff = true;
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php'; ?>

<?php amiga_player_videos_render_body(
    $playerId,
    $playerVideoEntries,
    $spotlightEntry,
    $spotlightLabel,
    $spotlightYoutube,
    $spotlightStartSec,
    $highlightRow,
    $indexUrl,
    $opponentFilter,
    $opponentChoices,
); ?>

<p class="k2-amiga-tournament-footnote" style="padding-bottom:1rem">
  <?php echo count($playerVideoEntries); ?> video<?php echo count($playerVideoEntries) === 1 ? '' : 's'; ?> · reverse chronological.
</p>

</div><!-- .k2-page-nav -->

</body>
</html>