<?php
/**
 * Amiga History page — Event / Month / Year wing tabs.
 *
 * Set $k2AmigaHistoryWingActive before include: event | world-cup | month | year
 */
declare(strict_types=1);

$k2AmigaHistoryWingActive = amiga_rating_history_normalize_wing($k2AmigaHistoryWingActive ?? 'event');
$k2AmigaHistoryAtKey = isset($k2AmigaHistoryAtKey) ? (string) $k2AmigaHistoryAtKey : '';

$k2AmigaHistoryWingTabs = [
    'event' => ['label' => 'Event'],
    'world-cup' => ['label' => 'World Cup'],
    'month' => ['label' => 'Month'],
    'year' => ['label' => 'Year'],
];

$k2HubChapterTitle = 'Historical ladder';
$k2HubChapterLede = 'Rating ladder as it stood after tournament finalize. Ratings commit at event end; '
    . 'within a month or year the list stays flat until the next finalized event.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
?>
<div class="k2-chrome-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Historical ladder view">
<?php foreach ($k2AmigaHistoryWingTabs as $wingId => $tab) {
    $href = '/amiga/history.php?wing=' . rawurlencode($wingId);
    if ($k2AmigaHistoryAtKey !== '' && $wingId === $k2AmigaHistoryWingActive) {
        $href .= '&at=' . rawurlencode($k2AmigaHistoryAtKey);
    }
    $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
    $activeClass = $k2AmigaHistoryWingActive === $wingId ? ' is-active' : '';
    ?>
		<a href="<?php echo $hrefEsc; ?>" class="k2-chrome-tabs__tab<?php echo $activeClass; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
