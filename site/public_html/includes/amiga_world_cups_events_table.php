<?php
/**
 * World Cups hub wing 1 — events catalog table.
 *
 * @see docs/amiga-world-cups-hub-policy.md §4.1
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_player_display_names.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_profile_blocks.php';
require_once __DIR__ . '/amiga_world_cup_stats_table.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';

const AMIGA_WORLD_CUPS_EVENTS_ANCHOR_COL = 2;
const AMIGA_WORLD_CUPS_EVENTS_DEFAULT_SORT_COL = 0;
/** Date is default order on Chronology; sortable but never active-sort emphasis. */
const AMIGA_WORLD_CUPS_EVENTS_QUIET_SORT_COL = 0;

function amiga_world_cups_events_sort_col_for_emphasis(int $colIndex, int $activeSortCol): int
{
    if ($colIndex === AMIGA_WORLD_CUPS_EVENTS_QUIET_SORT_COL && $activeSortCol === AMIGA_WORLD_CUPS_EVENTS_QUIET_SORT_COL) {
        return -1;
    }

    return $activeSortCol;
}

/** @deprecated Use amiga_wc_podium_th_markup() — kept for call sites in this file. */
function amiga_world_cups_events_podium_th(int $place): string
{
    return amiga_wc_podium_th_markup($place);
}

/**
 * @param array<int, string> $nameMap
 */
function amiga_world_cups_events_podium_sort_value(int $playerId, array $nameMap): string
{
    if ($playerId < 1) {
        return '';
    }

    return k2_player_display_name($nameMap, $playerId);
}

/**
 * @param list<array<string, mixed>> $rows from amiga_world_cup_stats_rows()
 * @param array<int, string> $nameMap
 * @param array<int, string> $countryMap
 */
function amiga_world_cups_events_render_table(array $rows, array $nameMap, array $countryMap): void
{
    $anchorCol = AMIGA_WORLD_CUPS_EVENTS_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_WORLD_CUPS_EVENTS_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-index k2-table--world-cups-events');
    $skipInitialSort = $defaultSortCol === AMIGA_WORLD_CUPS_EVENTS_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<div class="k2-table--world-cups-events-wrap">
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>" data-k2-quiet-sort-cols="<?php echo AMIGA_WORLD_CUPS_EVENTS_QUIET_SORT_COL; ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
<thead>
    <tr>
        <th<?php echo k2_table_sortable_th_attr(0, amiga_world_cups_events_sort_col_for_emphasis(0, $defaultSortCol), $defaultSortDir, 'k2-table-cell--right k2-wc-events-date'); ?> data-k2-sort="number">Date</th>
        <th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
        <th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="number">Players</th>
        <th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="number">Games</th>
        <th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center k2-amiga-wc-podium-th-cell k2-wc-events-podium-pad-start'); ?> data-k2-sort="text" data-k2-tooltip-label="Gold"><?php echo amiga_world_cups_events_podium_th(1); ?></th>
        <th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center k2-amiga-wc-podium-th-cell'); ?> data-k2-sort="text" data-k2-tooltip-label="Silver"><?php echo amiga_world_cups_events_podium_th(2); ?></th>
        <th<?php echo k2_table_sortable_th_attr(7, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center k2-amiga-wc-podium-th-cell'); ?> data-k2-sort="text" data-k2-tooltip-label="Bronze"><?php echo amiga_world_cups_events_podium_th(3); ?></th>
    </tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
    <tr>
        <td colspan="8" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No World Cups at this cutoff.</td>
    </tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $games = (int) ($row['rated_games'] ?? 0);
    $players = (int) ($row['distinct_players'] ?? 0);
    $hostCountry = (string) ($row['host_country'] ?? '');
    $goldId = (int) ($row['gold_player_id'] ?? 0);
    $silverId = (int) ($row['silver_player_id'] ?? 0);
    $bronzeId = (int) ($row['bronze_player_id'] ?? 0);
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $anchorCol, amiga_world_cups_events_sort_col_for_emphasis(0, $defaultSortCol), 'k2-table-cell--right k2-wc-events-date'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value([
            'event_date' => $row['event_date'] ?? null,
            'event_chrono' => $row['event_chrono'] ?? null,
        ]); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
        <td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($hostCountry); ?>"><?php echo k2_amiga_country_table_cell($hostCountry); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
            echo amiga_world_cup_stats_tournament_link(
                $tournamentId,
                (string) ($row['tournament_name'] ?? ''),
            );
        ?></td>
        <td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?>><?php echo $players > 0 ? (string) $players : '—'; ?></td>
        <td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?>><?php echo $games; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol, 'k2-table-cell--left k2-wc-events-podium-pad-start'); ?> data-k2-sort-value="<?php echo k2_h(amiga_world_cups_events_podium_sort_value($goldId, $nameMap)); ?>"><?php echo $goldId < 1 ? k2_fmt_dash() : k2_amiga_lb_player_cell($goldId, k2_player_display_name($nameMap, $goldId), trim($countryMap[$goldId] ?? '')); ?></td>
        <td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h(amiga_world_cups_events_podium_sort_value($silverId, $nameMap)); ?>"><?php echo $silverId < 1 ? k2_fmt_dash() : k2_amiga_lb_player_cell($silverId, k2_player_display_name($nameMap, $silverId), trim($countryMap[$silverId] ?? '')); ?></td>
        <td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h(amiga_world_cups_events_podium_sort_value($bronzeId, $nameMap)); ?>"><?php echo $bronzeId < 1 ? k2_fmt_dash() : k2_amiga_lb_player_cell($bronzeId, k2_player_display_name($nameMap, $bronzeId), trim($countryMap[$bronzeId] ?? '')); ?></td>
    </tr>
<?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
</div>
<p class="k2-amiga-world-cups-events-footnote" style="margin:0 0 2rem;color:var(--k2-text-secondary)"><?php echo number_format(count($rows)); ?> World Cup<?php echo count($rows) === 1 ? '' : 's'; ?>.</p>
    <?php
}