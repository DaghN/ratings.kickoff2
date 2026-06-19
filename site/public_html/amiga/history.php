<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — Historical ladder</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'history';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_rating_history_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_archive_listbox.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
k2_site_ensure_utc();
$con->query("SET time_zone = '+00:00'");

$wing = amiga_rating_history_normalize_wing(isset($_GET['wing']) ? (string) $_GET['wing'] : 'event');
$atKey = isset($_GET['at']) ? trim((string) $_GET['at']) : '';

try {
    $view = amiga_rating_history_resolve_view($con, $wing, $atKey !== '' ? $atKey : null);
} catch (Throwable $e) {
    mysqli_close($con);
    k2_public_error('Could not load historical ladder.');
}

$entry = $view['entry'];
$currentKey = $entry !== null ? (string) $entry['key'] : '';
$k2AmigaHistoryWingActive = $view['wing'];
$k2AmigaHistoryAtKey = $currentKey;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_history_nav.php';

mysqli_close($con);

/**
 * @param list<array<string, mixed>> $catalog
 */
function amiga_history_render_picker(string $wing, string $currentKey, array $catalog): void
{
    $choices = [];
    foreach ($catalog as $item) {
        $choices[] = [
            'value' => (string) $item['key'],
            'label' => (string) $item['label'],
        ];
    }
    ?>
<form class="k2-player-games-controls k2-amiga-history__picker" method="get" action="/amiga/history.php" data-k2-carry-scroll>
    <input type="hidden" name="wing" value="<?php echo k2_h($wing); ?>" />
    <span class="k2-amiga-history__picker-label">Jump to</span>
    <?php k2_archive_listbox_render(
        'at',
        'k2-amiga-history-at',
        $currentKey,
        $choices,
        'Jump to snapshot',
    ); ?>
</form>
    <?php
}

function amiga_history_render_stepper(
    string $wing,
    ?string $prevKey,
    ?string $nextKey,
    ?array $entry
): void {
    $label = $entry !== null ? (string) $entry['label'] : '—';
    $stepperClass = 'k2-amiga-history__stepper k2-player-games-day-steps';
    if ($wing === 'world-cup') {
        $stepperClass .= ' k2-amiga-history__stepper--world-cup';
    }
    echo '<nav class="' . $stepperClass . '" data-k2-carry-scroll aria-label="Snapshot">';
    if ($prevKey !== null) {
        $href = amiga_rating_history_page_url($wing, $prevKey);
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="'
            . k2_h($href) . '" aria-label="Previous snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    echo '<span class="k2-amiga-history__label">' . k2_h($label) . '</span>';
    if ($nextKey !== null) {
        $href = amiga_rating_history_page_url($wing, $nextKey);
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--next" href="'
            . k2_h($href) . '" aria-label="Next snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    echo '</nav>';
}
?>

<div class="k2-amiga-history__controls">
<?php
amiga_history_render_stepper($view['wing'], $view['prev_key'], $view['next_key'], $entry);
if ($view['catalog'] !== [] && $currentKey !== '') {
    amiga_history_render_picker($view['wing'], $currentKey, $view['catalog']);
}
?>
</div>

<?php if ($entry !== null && empty($entry['has_finalize_in_period']) && !in_array($view['wing'], ['event', 'world-cup'], true)) { ?>
<p class="k2-amiga-history__note">No tournament finalized in this <?php echo $view['wing'] === 'year' ? 'year' : 'month'; ?> — ladder unchanged from the previous snapshot.</p>
<?php } ?>

<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="false" data-k2-anchor-col="2" data-k2-default-sort="2" data-k2-default-direction="desc">
<thead>
    <tr>
        <th data-k2-sort="number">Rank</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">Elo</th>
        <th data-k2-sort="text">Country</th>
    </tr>
</thead>
<tbody class="black">
<?php foreach ($view['ladder'] as $row) { ?>
    <tr>
        <td><?php echo (int) $row['rank']; ?></td>
        <td class="k2-table-cell--left"><?php echo k2_amiga_player_link((int) $row['player_id'], (string) $row['name']); ?></td>
        <td><?php echo k2_fmt_int($row['rating_after']); ?></td>
        <td><?php echo k2_h($row['country']); ?></td>
    </tr>
<?php } ?>
<?php if ($view['ladder'] === []) { ?>
    <tr>
        <td colspan="4">No rated players at this snapshot.</td>
    </tr>
<?php } ?>
</tbody>
</table>
</div>

</body>
</html>
