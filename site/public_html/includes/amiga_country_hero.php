<?php
/**
 * Country roster hero — player-feast grid: flag left, name + stats right.
 *
 * Expects $k2CountryHeroToken, $k2CountryHeroSummary (array with players, games, wc_entries, wc_gold, wc_silver, wc_bronze).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';

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

$flagMeta = k2_amiga_country_flag_meta($countryToken);
$flagLabel = $flagMeta !== null ? $flagMeta['label'] : $countryToken;
$rosterHref = k2_amiga_country_roster_href($countryToken);
$flagImg = k2_amiga_country_flag_img($countryToken, [
    'class' => 'k2-country-hero__flag-img',
    'decorative' => false,
]);
?>
<article class="k2-country-hero k2-country-hero--feast">
    <div class="k2-country-hero__inner">
        <div class="k2-country-hero__media"><?php
            if ($flagImg !== '') {
                ?><a class="k2-country-roster-link k2-country-hero__flag-link" href="<?php echo k2_h($rosterHref); ?>" aria-label="Players from <?php echo k2_h($flagLabel); ?>"><span class="k2-country-hero__flag"><?php echo $flagImg; ?></span></a><?php
            } else {
                ?><span class="k2-country-hero__flag k2-country-hero__flag--text" aria-hidden="true"><?php echo k2_h($countryToken); ?></span><?php
            }
        ?></div>
        <div class="k2-country-hero__body">
            <h2 class="k2-country-hero__name"><?php
                $countryNameLink = k2_amiga_country_roster_link($countryToken);
                echo $countryNameLink !== '' ? $countryNameLink : k2_h($countryToken);
            ?></h2>
            <div class="k2-player-hero__stats">
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">Players</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo k2_h(number_format($players)); ?></span>
                </div>
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">Games</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo k2_h(number_format($games)); ?></span>
                </div>
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">WC entries</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo k2_h(number_format($wcEntries)); ?></span>
                </div>
                <div class="k2-player-hero__medals" style="--k2-player-hero-medal-count: 3">
                <?php
                foreach ([1 => $wcGold, 2 => $wcSilver, 3 => $wcBronze] as $place => $medalCount) {
                    $medalMeta = amiga_wc_podium_meta($place);
                    if ($medalMeta === null) {
                        continue;
                    }
                    ?>
                <div class="k2-player-hero__stat k2-country-hero__stat--medal">
                    <span class="k2-country-hero__medal-label"><?php echo amiga_wc_podium_metal_label_markup($place); ?></span>
                    <span class="k2-country-hero__medal-value k2-country-hero__medal-value--<?php echo k2_h($medalMeta['variant']); ?>"><?php echo k2_h((string) $medalCount); ?></span>
                </div>
                <?php } ?>
                </div>
            </div>
        </div>
    </div>
</article>
