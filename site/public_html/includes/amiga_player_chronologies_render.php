<?php
/**
 * Amiga player chronologies — spotlight, segment nav, table, charts markup.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_player_chronologies_lib.php';

const AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_FIRST_MET_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_VICTIMS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_VICTIMS_FIRST_WON_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_VICTIMS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_FIRST_DD_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_FIRST_CS_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_FIRST_MGC_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_FIRST_BL_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CULPRITS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_CULPRITS_FIRST_LOSS_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CULPRITS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_FIRST_DD_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_FIRST_CS_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_FIRST_MGS_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_FIRST_BW_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_FIRST_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_FIRST_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_FIRST_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_DEFAULT_SORT_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_ANCHOR_COL = 1;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_FIRST_COL = 2;
const AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_DEFAULT_SORT_COL = 2;

function amiga_player_chronology_render_spotlight(int $playerId, string $playerName, string $kind): void
{
    $title = amiga_player_chronology_kind_label($kind);
    $ruleHtml = amiga_player_chronology_kind_rule_html($playerId, $playerName, $kind);
    ?>
<div id="<?php echo k2_h(AMIGA_PLAYER_CHRONOLOGY_SPOTLIGHT_FRAGMENT); ?>" class="k2-ms-detail-spotlight-anchor" tabindex="-1"></div>
<header class="k2-ms-detail-spotlight k2-amiga-chronology-spotlight" aria-labelledby="k2-amiga-chronology-spotlight-title">
	<article class="k2-ms-card k2-ms-detail-spotlight-card is-unlocked k2-amiga-chronology-spotlight-card">
		<h1 id="k2-amiga-chronology-spotlight-title" class="k2-ms-card__title k2-ms-detail-spotlight-card__title k2-amiga-chronology-spotlight-card__title"><span class="k2-link-star"><?php echo k2_h($title); ?></span></h1>
		<p class="k2-ms-card__rule k2-ms-detail-spotlight-card__rule k2-amiga-chronology-spotlight-card__rule"><?php echo $ruleHtml; ?></p>
	</article>
</header>
    <?php
}

function amiga_player_chronology_render_segment_nav(int $playerId, string $kind, string $activeSegment): void
{
    if (!in_array(amiga_player_chronology_parse_kind($kind), amiga_player_chronology_valid_kinds(), true)) {
        return;
    }
    $tabs = [
        'made-it' => ['label' => 'Made it'],
        'graphs' => ['label' => 'Graphs'],
    ];
    ?>
<div class="k2-chrome-tabs k2-ms-detail-panel-tabs k2-amiga-chronology-panel-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll role="tablist" aria-label="Chronology detail">
    <?php foreach ($tabs as $segmentId => $tab) {
        $isActive = $activeSegment === $segmentId;
        ?>
		<a id="k2-amiga-chronology-tab-<?php echo k2_h($segmentId); ?>"
			href="<?php echo k2_h(amiga_player_chronology_href($playerId, $kind, $segmentId)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			role="tab"
			<?php echo $isActive ? ' aria-current="page" aria-selected="true" tabindex="0"' : ' aria-selected="false" tabindex="-1"'; ?>><?php echo k2_h($tab['label']); ?></a>
    <?php } ?>
	</nav>
</div>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_opponents_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No opponents yet.</p>
    <?php } else {
        amiga_player_chronology_render_opponents_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_opponents_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstMetCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_FIRST_MET_COL,
        AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-opponents-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_FIRST_MET_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_OPPONENTS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first meeting (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated meeting (event day only).">First met</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first rated meeting took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstMetCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $row
 */
function amiga_player_chronology_opponents_table_row(int $playerId, array $row, string $firstMetCellClass = 'k2-table-cell--left'): string
{
    require_once __DIR__ . '/amiga_rated_game_row.php';

    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $isPlayerA = (int) $game['idA'] === $playerId;
    $goalsFor = $isPlayerA ? (int) $game['GoalsA'] : (int) $game['GoalsB'];
    $goalsAgainst = $isPlayerA ? (int) $game['GoalsB'] : (int) $game['GoalsA'];

    if ($processed) {
        $adjustment = $isPlayerA ? (float) $game['AdjustmentA'] : (float) $game['AdjustmentB'];
        $isDraw = abs((float) $game['ActualScore'] - 0.5) < 0.001;
        $isWin = !$isDraw && (
            ($isPlayerA && abs((float) $game['ActualScore'] - 1.0) < 0.001)
            || (!$isPlayerA && abs((float) $game['ActualScore']) < 0.001)
        );
    } else {
        $outcome = k2_player_game_outcome_from_goals($goalsFor, $goalsAgainst);
        $isWin = $outcome['is_win'];
        $isDraw = $outcome['is_draw'];
        $adjustment = 0.0;
    }

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;

    $countryA = trim((string) ($row['country_a'] ?? ''));
    $countryB = trim((string) ($row['country_b'] ?? ''));
    $opponentId = (int) ($row['opponent_id'] ?? 0);
    $opponentName = (string) ($row['opponent_name'] ?? '');
    $opponentCountry = trim((string) ($row['opponent_country'] ?? ''));
    $opponentInner = k2_amiga_player_link($opponentId, $opponentName);
    $opponentCell = $opponentCountry !== ''
        ? k2_amiga_inline_flag_and_link($opponentCountry, $opponentInner)
        : $opponentInner;

    $unlockRank = (int) ($row['unlock_rank'] ?? 0);
    $firstMetSort = k2_h((string) ($row['first_met_sort'] ?? ''));
    $firstMetLabel = (string) ($row['first_met_label'] ?? '—');
    $eventCell = amiga_rated_game_tournament_cell($row);
    $tournamentSort = trim((string) ($row['tournament_name'] ?? ''));
    $resultCell = k2_player_game_result_html($isWin, $isDraw);
    $dash = k2_fmt_dash();
    $adjustmentCell = $processed ? k2_player_game_signed_number_html($adjustment) : $dash;

    return '<tr data-k2-sort-tie-value="' . $unlockRank . '">'
        . '<td data-k2-sort-value="' . $unlockRank . '">' . $unlockRank . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . k2_h($opponentName) . '">' . $opponentCell . '</td>'
        . '<td class="' . k2_h($firstMetCellClass) . '" data-k2-sort-value="' . $firstMetSort . '">' . $firstMetLabel . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-table-cell--pad-x-md" data-k2-sort-value="' . k2_h($tournamentSort) . '">' . $eventCell . '</td>'
        . '<td class="k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a k2-table-cell--pad-left-md" data-k2-sort-value="' . k2_h((string) $game['NameA']) . '">' . amiga_rated_game_player_side_cell((int) $game['idA'], (string) $game['NameA'], $countryA, 'a') . '</td>'
        . '<td data-k2-sort-value="' . $goalsA . '">' . $goalsACell . '</td>'
        . '<td data-k2-sort-value="' . $goalsB . '">' . $goalsBCell . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b" data-k2-sort-value="' . k2_h((string) $game['NameB']) . '">' . amiga_rated_game_player_side_cell((int) $game['idB'], (string) $game['NameB'], $countryB, 'b') . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort-value="' . k2_h($resultCell) . '">' . $resultCell . '</td>'
        . '<td data-k2-sort-value="' . ($processed ? (string) $adjustment : '') . '">' . $adjustmentCell . '</td>'
        . '</tr>';
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_opponents_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-opponents-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New opponents per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New opponents per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative opponents</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a new opponent is faced.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative opponents over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_victims_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No victims yet.</p>
    <?php } else {
        amiga_player_chronology_render_victims_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_victims_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstWonCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_VICTIMS_FIRST_WON_COL,
        AMIGA_PLAYER_CHRONOLOGY_VICTIMS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_VICTIMS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-victims-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_VICTIMS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_VICTIMS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_VICTIMS_FIRST_WON_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_VICTIMS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first win (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Victim</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated win vs this victim (event day only).">First win</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first win took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstWonCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_victims_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-victims-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New victims per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New victims per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative victims</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a new victim is beaten.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative victims over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_dd_victims_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No DD victims yet.</p>
    <?php } else {
        amiga_player_chronology_render_dd_victims_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_dd_victims_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstDdCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_FIRST_DD_COL,
        AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-dd-victims-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_FIRST_DD_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_DD_VICTIMS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first double-digit game vs this victim (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Victim</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated game where you scored 10+ vs this victim (event day only).">First DD</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first double-digit game took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstDdCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_dd_victims_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-dd-victims-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New DD victims per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New DD victims per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative DD victims</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever you score 10+ against a new victim.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative DD victims over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_cs_victims_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No CS victims yet.</p>
    <?php } else {
        amiga_player_chronology_render_cs_victims_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_cs_victims_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstCsCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_FIRST_CS_COL,
        AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-cs-victims-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_FIRST_CS_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_CS_VICTIMS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first clean sheet vs this victim (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Victim</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated game where you shut this victim out (hero GA = 0; event day only).">First CS</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first clean sheet took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstCsCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_cs_victims_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-cs-victims-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New CS victims per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New CS victims per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative CS victims</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever you shut out a new victim.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative CS victims over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_mgc_victims_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No MGC victims yet.</p>
    <?php } else {
        amiga_player_chronology_render_mgc_victims_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_mgc_victims_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstMgcCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_FIRST_MGC_COL,
        AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-mgc-victims-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_FIRST_MGC_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_MGC_VICTIMS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest credited record game among current MGC victims (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Victim</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the victim's credited most-goals-conceded game vs you (event day only). In a tie on the victim's max GA, the first culprit keeps the credit.">First MGC</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event for the credited record game.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstMgcCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_mgc_victims_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-mgc-victims-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New MGC victims per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New MGC victims per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative MGC victims</h2>
		<p class="k2-chart-block__hint">Steps up when a new victim's worst defensive game credits you (current inventory only).</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative MGC victims over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_bl_victims_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No BL victims yet.</p>
    <?php } else {
        amiga_player_chronology_render_bl_victims_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_bl_victims_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstBlCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_FIRST_BL_COL,
        AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-bl-victims-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_FIRST_BL_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_BL_VICTIMS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest credited record game among current BL victims (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Victim</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the victim's credited biggest-loss game vs you (event day only). In a tie on the victim's max loss margin, the first culprit keeps the credit.">First BL</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event for the credited record game.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstBlCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_bl_victims_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-bl-victims-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New BL victims per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New BL victims per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative BL victims</h2>
		<p class="k2-chart-block__hint">Steps up when a new victim's biggest loss game credits you (current inventory only).</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative BL victims over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_mgs_culprits_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No MGS culprits yet.</p>
    <?php } else {
        amiga_player_chronology_render_mgs_culprits_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_mgs_culprits_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstMgsCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_FIRST_MGS_COL,
        AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-mgs-culprits-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_FIRST_MGS_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_MGS_CULPRITS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest credited record game among current MGS culprits (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Culprit</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the culprit's credited most-goals-scored game vs you (event day only). In a tie on the culprit's max GF, the first victim keeps the credit.">First MGS</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event for the credited record game.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstMgsCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_mgs_culprits_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-mgs-culprits-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New MGS culprits per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New MGS culprits per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative MGS culprits</h2>
		<p class="k2-chart-block__hint">Steps up when a new culprit's most-scored-goals game credits you (current inventory only).</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative MGS culprits over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_bw_culprits_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No BW culprits yet.</p>
    <?php } else {
        amiga_player_chronology_render_bw_culprits_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_bw_culprits_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstBwCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_FIRST_BW_COL,
        AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-bw-culprits-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_FIRST_BW_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_BW_CULPRITS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest credited record game among current BW culprits (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Culprit</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the culprit's credited biggest-win game vs you (event day only). In a tie on the culprit's max win margin, the first victim keeps the credit.">First BW</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event for the credited record game.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstBwCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_bw_culprits_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-bw-culprits-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New BW culprits per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New BW culprits per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative BW culprits</h2>
		<p class="k2-chart-block__hint">Steps up when a new culprit's biggest-win game credits you (current inventory only).</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative BW culprits over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_culprits_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No culprits yet.</p>
    <?php } else {
        amiga_player_chronology_render_culprits_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_culprits_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstLossCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_CULPRITS_FIRST_LOSS_COL,
        AMIGA_PLAYER_CHRONOLOGY_CULPRITS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_CULPRITS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-culprits-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_CULPRITS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_CULPRITS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_CULPRITS_FIRST_LOSS_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_CULPRITS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first loss (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Culprit</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated loss vs this culprit (event day only).">First loss</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first loss took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstLossCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_culprits_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-culprits-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New culprits per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New culprits per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative culprits</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a new culprit beats you.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative culprits over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_dd_culprits_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No DD culprits yet.</p>
    <?php } else {
        amiga_player_chronology_render_dd_culprits_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_dd_culprits_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstDdCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_FIRST_DD_COL,
        AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-dd-culprits-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_FIRST_DD_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_DD_CULPRITS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first double-digit game against you by this culprit (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Culprit</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated game where this culprit scored 10+ against you (event day only).">First DD</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first double-digit game took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstDdCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_dd_culprits_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-dd-culprits-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New DD culprits per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New DD culprits per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative DD culprits</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a new culprit scores 10+ against you.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative DD culprits over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_cs_culprits_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No CS culprits yet.</p>
    <?php } else {
        amiga_player_chronology_render_cs_culprits_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_cs_culprits_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstCsCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_FIRST_CS_COL,
        AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-cs-culprits-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_FIRST_CS_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_CS_CULPRITS_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first shut-out against you by this culprit (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Culprit</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first rated game where this culprit shut you out (hero GF = 0; event day only).">First CS</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event where the first shut-out took place.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_opponents_table_row($playerId, $row, $firstCsCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_cs_culprits_graphs(array $chartPayload): void
{
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-cs-culprits-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New CS culprits per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New CS culprits per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative CS culprits</h2>
		<p class="k2-chart-block__hint">Steps up by one whenever a new culprit shuts you out.</p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative CS culprits over time"></canvas>
	</div>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_host_countries_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No host countries yet.</p>
    <?php } else {
        amiga_player_chronology_render_host_countries_table($playerId, $rows);
    } ?>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_host_countries_table(int $playerId, array $rows): void
{
    require_once __DIR__ . '/k2_table_helpers.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    require_once __DIR__ . '/amiga_rated_game_row.php';
    $firstCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_FIRST_COL,
        AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_DEFAULT_SORT_COL,
        AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_DEFAULT_SORT_COL,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-amiga-chronology-host-countries-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_ANCHOR_COL; ?>"
	data-k2-default-sort="<?php echo AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_DEFAULT_SORT_COL; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_FIRST_COL]); ?><?php echo k2_table_skip_initial_sort_attr(AMIGA_PLAYER_CHRONOLOGY_HOST_COUNTRIES_DEFAULT_SORT_COL, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest first host country (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Country</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Date of the first event hosted in this country that you entered (event day only).">First hosted</th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event of the first host-country unlock.">Event</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_host_countries_table_row($row, $firstCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * @param array<string, mixed> $row
 */
function amiga_player_chronology_host_countries_table_row(array $row, string $firstCellClass = 'k2-table-cell--left'): string
{
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    require_once __DIR__ . '/amiga_rated_game_row.php';

    $unlockRank = (int) ($row['unlock_rank'] ?? 0);
    $country = trim((string) ($row['country_token'] ?? $row['tournament_country'] ?? ''));
    $countryCell = $country !== '' ? k2_amiga_lb_country_cell($country) : k2_fmt_dash();
    $firstMetSort = k2_h((string) ($row['first_met_sort'] ?? ''));
    $firstMetLabel = (string) ($row['first_met_label'] ?? '—');
    $eventCell = amiga_rated_game_tournament_cell($row);
    $tournamentSort = trim((string) ($row['tournament_name'] ?? ''));

    return '<tr data-k2-sort-tie-value="' . $unlockRank . '">'
        . '<td data-k2-sort-value="' . $unlockRank . '">' . $unlockRank . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . k2_h($country) . '">' . $countryCell . '</td>'
        . '<td class="' . k2_h($firstCellClass) . '" data-k2-sort-value="' . $firstMetSort . '">' . $firstMetLabel . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-table-cell--pad-x-md" data-k2-sort-value="' . k2_h($tournamentSort) . '">' . $eventCell . '</td>'
        . '</tr>';
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_host_countries_graphs(array $chartPayload): void
{
    amiga_player_chronology_render_country_kind_graphs($chartPayload, 'host-countries', 'host countries', 'host country', 'Steps up by one whenever you unlock a new host country.');
}

/**
 * Shared Made-it table for opponent-nationality country kinds (game grain + scoreboard).
 *
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_opponent_countries_table(
    int $playerId,
    array $rows,
    string $kindSlug,
    string $dateHeader,
    string $dateHelp,
    int $anchorCol,
    int $firstCol,
    int $defaultSortCol,
): void {
    require_once __DIR__ . '/k2_table_helpers.php';
    $firstCellClass = k2_table_quiet_date_cell_class(
        $firstCol,
        $defaultSortCol,
        $defaultSortCol,
        true,
        'k2-table-cell--left',
    );
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--tournament-games k2-amiga-chronology-<?php echo k2_h($kindSlug); ?>-table"
	data-k2-table="sortable"
	data-k2-anchor-col="<?php echo (int) $anchorCol; ?>"
	data-k2-default-sort="<?php echo (int) $defaultSortCol; ?>"
	data-k2-default-direction="desc"<?php echo k2_table_quiet_default_sort_col_attr([$firstCol]); ?><?php echo k2_table_skip_initial_sort_attr($defaultSortCol, 'desc'); ?>
	data-k2-sort-tie-order="match">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="1 = earliest unlock (fixed). Table sort does not renumber; default view is newest first.">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Country</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="<?php echo k2_h($dateHelp); ?>"><?php echo k2_h($dateHeader); ?></th>
		<th class="k2-table-cell--left k2-table-cell--pad-x-md" data-k2-sort="text" data-k2-help="Tournament or event of the unlock game.">Event</th>
		<th class="k2-table-cell--right k2-table-cell--pad-left-md" data-k2-sort="text">Team A</th>
		<th data-k2-sort="number"></th>
		<th data-k2-sort="number"></th>
		<th class="k2-table-cell--left" data-k2-sort="text">Team B</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort="text">Result</th>
		<th data-k2-sort="number">Adj.</th>
	</tr>
</thead>
<tbody class="black">
    <?php foreach ($rows as $row) {
        echo amiga_player_chronology_country_game_table_row($playerId, $row, $firstCellClass);
    } ?>
</tbody>
</table>
</div>
    <?php
}

/**
 * Game-grain country unlock row — Country anchor instead of opponent name.
 *
 * @param array<string, mixed> $row
 */
function amiga_player_chronology_country_game_table_row(int $playerId, array $row, string $firstMetCellClass = 'k2-table-cell--left'): string
{
    require_once __DIR__ . '/amiga_rated_game_row.php';
    require_once __DIR__ . '/k2_amiga_country_flag.php';

    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $isPlayerA = (int) $game['idA'] === $playerId;
    $goalsFor = $isPlayerA ? (int) $game['GoalsA'] : (int) $game['GoalsB'];
    $goalsAgainst = $isPlayerA ? (int) $game['GoalsB'] : (int) $game['GoalsA'];

    if ($processed) {
        $adjustment = $isPlayerA ? (float) $game['AdjustmentA'] : (float) $game['AdjustmentB'];
        $isDraw = abs((float) $game['ActualScore'] - 0.5) < 0.001;
        $isWin = !$isDraw && (
            ($isPlayerA && abs((float) $game['ActualScore'] - 1.0) < 0.001)
            || (!$isPlayerA && abs((float) $game['ActualScore']) < 0.001)
        );
    } else {
        $outcome = k2_player_game_outcome_from_goals($goalsFor, $goalsAgainst);
        $isWin = $outcome['is_win'];
        $isDraw = $outcome['is_draw'];
        $adjustment = 0.0;
    }

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;

    $countryA = trim((string) ($row['country_a'] ?? ''));
    $countryB = trim((string) ($row['country_b'] ?? ''));
    $country = trim((string) ($row['country_token'] ?? $row['opponent_country'] ?? ''));
    $countryCell = $country !== '' ? k2_amiga_lb_country_cell($country) : k2_fmt_dash();

    $unlockRank = (int) ($row['unlock_rank'] ?? 0);
    $firstMetSort = k2_h((string) ($row['first_met_sort'] ?? ''));
    $firstMetLabel = (string) ($row['first_met_label'] ?? '—');
    $eventCell = amiga_rated_game_tournament_cell($row);
    $tournamentSort = trim((string) ($row['tournament_name'] ?? ''));
    $resultCell = k2_player_game_result_html($isWin, $isDraw);
    $dash = k2_fmt_dash();
    $adjustmentCell = $processed ? k2_player_game_signed_number_html($adjustment) : $dash;

    return '<tr data-k2-sort-tie-value="' . $unlockRank . '">'
        . '<td data-k2-sort-value="' . $unlockRank . '">' . $unlockRank . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . k2_h($country) . '">' . $countryCell . '</td>'
        . '<td class="' . k2_h($firstMetCellClass) . '" data-k2-sort-value="' . $firstMetSort . '">' . $firstMetLabel . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-table-cell--pad-x-md" data-k2-sort-value="' . k2_h($tournamentSort) . '">' . $eventCell . '</td>'
        . '<td class="k2-table-cell--right k2-amiga-tgame-team k2-amiga-tgame-team--a k2-table-cell--pad-left-md" data-k2-sort-value="' . k2_h((string) $game['NameA']) . '">' . amiga_rated_game_player_side_cell((int) $game['idA'], (string) $game['NameA'], $countryA, 'a') . '</td>'
        . '<td data-k2-sort-value="' . $goalsA . '">' . $goalsACell . '</td>'
        . '<td data-k2-sort-value="' . $goalsB . '">' . $goalsBCell . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b" data-k2-sort-value="' . k2_h((string) $game['NameB']) . '">' . amiga_rated_game_player_side_cell((int) $game['idB'], (string) $game['NameB'], $countryB, 'b') . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-md" data-k2-sort-value="' . k2_h($resultCell) . '">' . $resultCell . '</td>'
        . '<td data-k2-sort-value="' . ($processed ? (string) $adjustment : '') . '">' . $adjustmentCell . '</td>'
        . '</tr>';
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_countries_faced_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No countries faced yet.</p>
    <?php } else {
        amiga_player_chronology_render_opponent_countries_table(
            $playerId,
            $rows,
            'countries-faced',
            'First faced',
            'Date of the first rated game vs an opponent from this country (event day only).',
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_ANCHOR_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_FIRST_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_FACED_DEFAULT_SORT_COL,
        );
    } ?>
</section>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_countries_faced_graphs(array $chartPayload): void
{
    amiga_player_chronology_render_country_kind_graphs($chartPayload, 'countries-faced', 'countries faced', 'country faced', 'Steps up by one whenever you face a new opponent country.');
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_countries_beaten_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No countries beaten yet.</p>
    <?php } else {
        amiga_player_chronology_render_opponent_countries_table(
            $playerId,
            $rows,
            'countries-beaten',
            'First win',
            'Date of the first rated win vs an opponent from this country (event day only).',
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_ANCHOR_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_FIRST_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_DEFAULT_SORT_COL,
        );
    } ?>
</section>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_countries_beaten_graphs(array $chartPayload): void
{
    amiga_player_chronology_render_country_kind_graphs($chartPayload, 'countries-beaten', 'countries beaten', 'country beaten', 'Steps up by one whenever you beat a new opponent country.');
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_player_chronology_render_countries_beaten_by_made_it(int $playerId, array $rows): void
{
    ?>
<section class="k2-ms-detail-section k2-amiga-chronology-made-it" aria-labelledby="k2-amiga-chronology-made-it-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-made-it-heading">Made it</h2>
    <?php if ($rows === []) { ?>
	<p class="k2-ms-meta-hint">No countries beaten by yet.</p>
    <?php } else {
        amiga_player_chronology_render_opponent_countries_table(
            $playerId,
            $rows,
            'countries-beaten-by',
            'First loss',
            'Date of the first rated loss to an opponent from this country (event day only).',
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_ANCHOR_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_FIRST_COL,
            AMIGA_PLAYER_CHRONOLOGY_COUNTRIES_BEATEN_BY_DEFAULT_SORT_COL,
        );
    } ?>
</section>
    <?php
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_countries_beaten_by_graphs(array $chartPayload): void
{
    amiga_player_chronology_render_country_kind_graphs($chartPayload, 'countries-beaten-by', 'countries beaten by', 'country beaten by', 'Steps up by one whenever a new opponent country beats you.');
}

/**
 * @param array<string, mixed> $chartPayload
 */
function amiga_player_chronology_render_country_kind_graphs(
    array $chartPayload,
    string $slug,
    string $labelPlural,
    string $labelSingular,
    string $cumulativeHint,
): void {
    $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        $json = '{}';
    }
    ?>
<section class="k2-ms-detail-section k2-ms-detail-charts k2-amiga-chronology-graphs" aria-labelledby="k2-amiga-chronology-graphs-heading">
	<h2 class="k2-panel-heading visually-hidden" id="k2-amiga-chronology-graphs-heading">Graphs</h2>
	<p class="k2-ms-detail-charts__empty-note" id="k2-amiga-chronology-charts-empty-note" hidden></p>
	<script type="application/json" id="k2-amiga-chronology-<?php echo k2_h($slug); ?>-chart-data"><?php echo $json; ?></script>
	<div class="k2-amiga-chronology-year-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">New <?php echo k2_h($labelPlural); ?> per year</h2>
		<p class="k2-amiga-chronology-year-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="New <?php echo k2_h($labelPlural); ?> per calendar year"></canvas>
	</div>
	<div class="k2-amiga-chronology-cumulative-chart k2-ms-detail-chart">
		<h2 class="k2-panel-heading">Cumulative <?php echo k2_h($labelPlural); ?></h2>
		<p class="k2-chart-block__hint"><?php echo k2_h($cumulativeHint); ?></p>
		<p class="k2-amiga-chronology-cumulative-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
		<canvas width="960" height="271" aria-label="Cumulative <?php echo k2_h($labelPlural); ?> over time"></canvas>
	</div>
</section>
    <?php
}
