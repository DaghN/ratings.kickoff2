<?php
/**
 * Amiga country entity segment — Roster · Rivals.
 *
 * A single country is an entity page (docs/navigation-model.md NM6): this segment
 * sub-nav sits below the realm hub bar, same grammar as the player wing nav.
 * Set $k2AmigaCountryView ('roster'|'rivals') and $k2AmigaCountryToken before include.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_country_rivals_lib.php';

$k2AmigaCountryView = $k2AmigaCountryView ?? 'roster';
$k2AmigaCountryToken = (string) ($k2AmigaCountryToken ?? '');
$k2AmigaCountryTabs = [
    'roster' => ['href' => k2_amiga_country_roster_href($k2AmigaCountryToken, false), 'label' => 'Roster'],
    'rivals' => ['href' => k2_amiga_country_rivals_href($k2AmigaCountryToken), 'label' => 'Rivals'],
];
?>
<div class="k2-chrome-tabs k2-player-wing-tabs k2-country-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Country sections">
<?php foreach ($k2AmigaCountryTabs as $tabId => $tab) {
    $isActive = $k2AmigaCountryView === $tabId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
	</nav>
</div>