<?php
/**
 * Country roster hero — flag, name, summary strip.
 *
 * Expects $k2CountryHeroToken, $k2CountryHeroSummary (array with players, games, wc_entries, wc_gold, wc_silver, wc_bronze).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

if (empty($k2CountryHeroToken)) {
    return;
}

$countryToken = (string) $k2CountryHeroToken;
$summary = is_array($k2CountryHeroSummary ?? null) ? $k2CountryHeroSummary : [];
$players = (int) ($summary['players'] ?? 0);
$games = (int) ($summary['games'] ?? 0);
$wcEntries = (int) ($summary['wc_entries'] ?? 0);
$wcGold = (int) ($summary['wc_gold'] ?? 0);
$wcSilver = (int) ($summary['wc_silver'] ?? 0);
$wcBronze = (int) ($summary['wc_bronze'] ?? 0);
$flagHtml = k2_amiga_country_table_cell($countryToken);
?>
<article class="k2-country-hero">
    <div class="k2-country-hero__inner">
        <div class="k2-country-hero__flag" aria-hidden="true"><?php echo $flagHtml; ?></div>
        <div class="k2-country-hero__body">
            <h2 class="k2-country-hero__title"><?php echo k2_h($countryToken); ?></h2>
            <p class="k2-country-hero__summary"><?php
                echo k2_h(number_format($players) . ' players · '
                    . number_format($games) . ' games · '
                    . number_format($wcEntries) . ' WC entries · '
                    . $wcGold . ' gold · '
                    . $wcSilver . ' silver · '
                    . $wcBronze . ' bronze');
            ?></p>
        </div>
    </div>
</article>