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
    if ($kind !== AMIGA_PLAYER_CHRONOLOGY_KIND_OPPONENTS) {
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
			href="<?php echo k2_h(amiga_player_chronology_opponents_href($playerId, $segmentId)); ?>"
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