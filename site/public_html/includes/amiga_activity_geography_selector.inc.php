<?php
/**
 * Geography wing — country duel + race controls (slice 5+).
 *
 * Requires $k2AmigaActivityGeographyView: hosts | nations
 *
 * @see docs/amiga-activity-charts-policy.md §6
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/k2_archive_listbox.php';

$k2GeoView = $k2AmigaActivityGeographyView ?? 'hosts';
$k2GeoSlice = $k2GeoView === 'nations' ? 'player_nationality' : 'host_country';
$k2GeoParam = $k2GeoView === 'nations' ? 'nats' : 'hosts';
$k2GeoDuelLabel = $k2GeoView === 'nations' ? 'Nationality A' : 'Host A';
$k2GeoDuelLabelB = $k2GeoView === 'nations' ? 'Nationality B' : 'Host B';

include __DIR__ . '/../../config/ko2amiga_config.php';
$k2GeoCon = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$k2GeoCon->query("SET time_zone = '+00:00'");
$k2GeoCtx = amiga_snapshot_context_from_request($k2GeoCon);
$k2GeoCutoffTid = amiga_community_cutoff_tournament_id_for_read($k2GeoCon, $k2GeoCtx);
$k2GeoAvailableRanked = amiga_community_slice_keys_at_cutoff(
    $k2GeoCon,
    (int) ($k2GeoCutoffTid ?? 0),
    $k2GeoSlice,
    'games'
);
$k2GeoCsv = isset($_GET[$k2GeoParam]) ? trim((string) $_GET[$k2GeoParam]) : '';
$k2GeoSelection = amiga_community_geo_page_selection($k2GeoCsv !== '' ? $k2GeoCsv : null, $k2GeoAvailableRanked);
$k2GeoAvailableKeys = array_keys($k2GeoAvailableRanked);
mysqli_close($k2GeoCon);
unset($k2GeoCon);

$k2GeoRaceKeys = $k2GeoSelection['race_keys'];
$k2GeoDuelA = $k2GeoSelection['duel_a'];
$k2GeoDuelB = $k2GeoSelection['duel_b'];
$k2GeoCsvResolved = $k2GeoSelection['csv'];

/**
 * @return list<array{value: string, label: string, flag_html?: string}>
 */
function k2_amiga_act_geo_country_choices(array $keys, bool $withEmpty = false): array
{
    $choices = [];
    if ($withEmpty) {
        $choices[] = ['value' => '', 'label' => '—'];
    }
    foreach ($keys as $key) {
        $key = (string) $key;
        $choices[] = [
            'value' => $key,
            'label' => $key,
            'flag_html' => k2_amiga_country_flag_img($key, ['decorative' => true]),
        ];
    }

    return $choices;
}

$k2GeoDuelAChoices = k2_amiga_act_geo_country_choices($k2GeoAvailableKeys);
$k2GeoDuelBChoices = k2_amiga_act_geo_country_choices($k2GeoAvailableKeys);
if ($k2GeoDuelB === null || $k2GeoDuelB === '') {
    [, $k2GeoDefaultB] = amiga_community_geo_default_duel($k2GeoAvailableKeys);
    $k2GeoDuelB = $k2GeoDefaultB ?? '';
}
$k2GeoRaceAddChoices = [];
foreach ($k2GeoAvailableKeys as $k2GeoAddKey) {
    if (!in_array($k2GeoAddKey, $k2GeoRaceKeys, true)) {
        $k2GeoRaceAddChoices[] = [
            'value' => (string) $k2GeoAddKey,
            'label' => (string) $k2GeoAddKey,
            'flag_html' => k2_amiga_country_flag_img($k2GeoAddKey, ['decorative' => true]),
        ];
    }
}
$k2GeoListboxTriggerClass = 'k2-amiga-act-geo-listbox';
?>
<section class="k2-activity-section k2-amiga-act-geo-section" aria-labelledby="k2-act-geography-selector-title">
	<header class="k2-activity-section__head">
		<h2 class="k2-panel-heading" id="k2-act-geography-selector-title"><?php echo $k2GeoView === 'nations' ? 'Where do we come from?' : 'Who\'s hosting tournaments?'; ?></h2>
		<p class="k2-activity-section__intro"><?php echo $k2GeoView === 'nations'
    ? 'Compare nationalities side by side, or race cumulative totals.'
    : 'Compare host countries side by side, or race cumulative totals.'; ?></p>
	</header>

	<div class="k2-amiga-act-geo-root"
		data-k2-geo-slice="<?php echo k2_h($k2GeoSlice); ?>"
		data-k2-geo-param="<?php echo k2_h($k2GeoParam); ?>"
		data-k2-geo-csv="<?php echo k2_h($k2GeoCsvResolved); ?>"
		data-k2-geo-duel-a="<?php echo k2_h($k2GeoDuelA); ?>"
		data-k2-geo-duel-b="<?php echo k2_h((string) ($k2GeoDuelB ?? '')); ?>"
		data-k2-geo-available="<?php echo k2_h(json_encode($k2GeoAvailableKeys, JSON_UNESCAPED_UNICODE)); ?>"
		data-k2-geo-race="<?php echo k2_h(json_encode($k2GeoRaceKeys, JSON_UNESCAPED_UNICODE)); ?>">
		<div class="k2-amiga-act-geo-controls" role="group" aria-label="Country comparison controls">
			<div class="k2-realm-games-filters__row k2-amiga-act-geo-controls__row">
				<span class="k2-realm-games-filters__row-label" id="k2-amiga-act-geo-compare-label">Compare</span>
				<div class="k2-amiga-act-geo-duel" aria-labelledby="k2-amiga-act-geo-compare-label">
					<label class="k2-amiga-act-geo-duel-field">
						<span class="k2-amiga-act-geo-duel-flag" data-k2-geo-flag-for="duel-a" aria-hidden="true"><?php echo k2_amiga_country_flag_img($k2GeoDuelA, ['decorative' => true]); ?></span>
						<?php k2_archive_listbox_render('geo_duel_a', 'k2-amiga-act-geo-duel-a', $k2GeoDuelA, $k2GeoDuelAChoices, $k2GeoDuelLabel, $k2GeoListboxTriggerClass); ?>
					</label>
					<span class="k2-amiga-act-geo-vs">vs</span>
					<label class="k2-amiga-act-geo-duel-field">
						<span class="k2-amiga-act-geo-duel-flag" data-k2-geo-flag-for="duel-b" aria-hidden="true"><?php echo k2_amiga_country_flag_img((string) $k2GeoDuelB, ['decorative' => true]); ?></span>
						<?php k2_archive_listbox_render('geo_duel_b', 'k2-amiga-act-geo-duel-b', (string) $k2GeoDuelB, $k2GeoDuelBChoices, $k2GeoDuelLabelB, $k2GeoListboxTriggerClass); ?>
					</label>
				</div>
			</div>
			<div class="k2-realm-games-filters__row k2-amiga-act-geo-controls__row">
				<span class="k2-realm-games-filters__row-label" id="k2-amiga-act-geo-race-label">Race lines</span>
				<div class="k2-amiga-act-geo-race" aria-labelledby="k2-amiga-act-geo-race-label">
					<div class="k2-amiga-act-geo-race-list" aria-label="Countries in the race chart"></div>
					<label class="k2-amiga-act-geo-race-add-wrap">
						<?php k2_archive_listbox_render('geo_race_add', 'k2-amiga-act-geo-race-add', '', $k2GeoRaceAddChoices, 'Add country to race', $k2GeoListboxTriggerClass, 'Add country', false, ''); ?>
					</label>
				</div>
			</div>
		</div>
	</div>
</section>