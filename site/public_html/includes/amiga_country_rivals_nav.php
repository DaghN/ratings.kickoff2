<?php
/**
 * Amiga country Rivals inner wings — H2H · W/D/L · Goals · DDs.
 * Set $k2AmigaCountryRivalsView and $k2AmigaCountryToken before include.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_rivals_lib.php';

$k2AmigaCountryRivalsView = amiga_country_rivals_parse_view($k2AmigaCountryRivalsView ?? null);
$k2AmigaCountryToken = (string) ($k2AmigaCountryToken ?? '');
$rivalToken = amiga_countries_normalize_country_param((string) ($_GET['rival'] ?? ''));

$k2AmigaCountryRivalsTabs = [
    'h2h' => 'Head-to-head',
    'wdl' => 'W/D/L',
    'goals' => 'Goals',
    'dds' => 'DDs',
];
?>
<div class="k2-chrome-tabs k2-player-opponents k2-country-rivals__wings">
	<nav class="k2-player-opponents__nav" data-k2-carry-scroll aria-label="Rivals views">
		<div class="k2-chrome-tabs__bar k2-player-opponents__bar" role="tablist" aria-label="Rivals wing">
<?php foreach ($k2AmigaCountryRivalsTabs as $viewId => $label) {
    $active = $k2AmigaCountryRivalsView === $viewId;
    ?>
			<a
				href="<?php echo htmlspecialchars(k2_amiga_country_rivals_href($k2AmigaCountryToken, $viewId, $rivalToken !== '' ? $rivalToken : null), ENT_QUOTES, 'UTF-8'); ?>"
				class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
		</div>
	</nav>
</div>