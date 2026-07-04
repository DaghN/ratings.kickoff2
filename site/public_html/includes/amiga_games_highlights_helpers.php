<?php
/**
 * Amiga Games hub — all-time spectacle boards.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_realm_games_hub_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_realm_games_all.php';
require_once __DIR__ . '/k2_rated_game_row.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_rated_game_row.php';

const AMIGA_GAMES_HIGHLIGHTS_LIMIT = 100;
const AMIGA_GAMES_HIGHLIGHTS_ANCHOR = 'k2-amiga-games-highlights';

/** @var array<string, array{label: string, heading: string, default_sort_key: string, default_sort_col: int, help?: string}> */
const AMIGA_GAMES_HIGHLIGHT_BOARDS = [
    // default_sort_col is the 0-based column index in the fixed full layout:
    // 0 # · 1 ID · 2 Date · 3 Tournament · 4 Player A · 5 A · 6 B · 7 Player B · 8 GD · 9 Sum · 10 TS ·
    // 11 Rating A · 12 Rating B · 13 Elo Diff · 14 Fav ES · 15 Adjustment · 16 Adjustment lost.
    'most_goals' => [
        'label' => 'Most goals',
        'heading' => 'Most total goals',
        'default_sort_key' => 'sum',
        'default_sort_col' => 9,
    ],
    'biggest_draws' => [
        'label' => 'Biggest draws',
        'heading' => 'Biggest draws',
        'default_sort_key' => 'sum',
        'default_sort_col' => 9,
    ],
    'biggest_wins' => [
        'label' => 'Biggest wins',
        'heading' => 'Biggest wins',
        'default_sort_key' => 'gd',
        'default_sort_col' => 8,
    ],
    'top_score' => [
        'label' => 'Top scores',
        'heading' => 'Top score',
        'default_sort_key' => 'top_score',
        'default_sort_col' => 10,
        'help' => 'The highest goals one player scored in a single game — e.g. 10 in a 10–2.',
    ],
    'biggest_upsets' => [
        'label' => 'Biggest upsets',
        'heading' => 'Biggest upsets',
        'default_sort_key' => 'adjustment',
        'default_sort_col' => 15,
        'help' => 'The biggest rating gaps overcome by the underdog.',
    ],
];

/** Decisive games where the winner had the lower pre-game rating (excludes flat early-era ties). */
function amiga_games_highlights_underdog_win_sql(): string
{
    return amiga_games_highlights_underdog_win_predicate_sql('r.ActualScore', 'r.RatingA', 'r.RatingB');
}

function amiga_games_highlights_lean_underdog_win_sql(): string
{
    return amiga_games_highlights_underdog_win_predicate_sql('gr.actual_score', 'gr.rating_a', 'gr.rating_b');
}

function amiga_games_highlights_underdog_win_predicate_sql(
    string $actualScoreColumn,
    string $ratingAColumn,
    string $ratingBColumn,
): string {
    return ' AND ABS(' . $actualScoreColumn . ' - 0.5) >= 0.001'
        . ' AND ('
        . '(ABS(' . $actualScoreColumn . ' - 1.0) < 0.001 AND ' . $ratingAColumn . ' < ' . $ratingBColumn . ')'
        . ' OR (ABS(' . $actualScoreColumn . ' - 0.0) < 0.001 AND ' . $ratingBColumn . ' < ' . $ratingAColumn . ')'
        . ')';
}

function amiga_games_highlights_winner_adjustment_sql(): string
{
    return amiga_games_highlights_winner_adjustment_expr_sql('r.ActualScore', 'r.AdjustmentA', 'r.AdjustmentB');
}

function amiga_games_highlights_lean_winner_adjustment_sql(): string
{
    return amiga_games_highlights_winner_adjustment_expr_sql('gr.actual_score', 'gr.adjustment_a', 'gr.adjustment_b');
}

function amiga_games_highlights_winner_adjustment_expr_sql(
    string $actualScoreColumn,
    string $adjustmentAColumn,
    string $adjustmentBColumn,
): string {
    return 'CASE'
        . ' WHEN ABS(' . $actualScoreColumn . ' - 1.0) < 0.001 THEN ' . $adjustmentAColumn
        . ' WHEN ABS(' . $actualScoreColumn . ' - 0.0) < 0.001 THEN ' . $adjustmentBColumn
        . ' ELSE 0'
        . ' END';
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_games_highlights_lean_scope_where_sql(
    AmigaSnapshotContext $ctx,
    string $scope,
    string &$types,
    array &$params,
): string {
    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_tournament_cutoff_and_sql(
        $ctx,
        $types,
        $params,
        't.event_date',
        't.chrono',
        't.id',
    );
    $where = '1=1' . $cutoffSql;
    if (amiga_games_highlights_valid_scope($scope) === 'world-cup') {
        $where .= ' AND ' . amiga_games_world_cup_name_sql('t.name');
    }

    return $where;
}

/**
 * Narrow g/gr/t scan for LIMIT — metric-first index use, then join-back for display cols.
 *
 * @return array{filter: string, order: string}
 */
function amiga_games_highlights_board_limit_scan(string $board): array
{
    switch (amiga_games_highlights_valid_board($board)) {
        case 'biggest_draws':
            return [
                'filter' => ' AND ABS(gr.actual_score - 0.5) < 0.001',
                'order' => 'gr.sum_of_goals DESC, g.id ASC',
            ];
        case 'top_score':
            return [
                'filter' => '',
                'order' => 'GREATEST(g.goals_a, g.goals_b) DESC, gr.sum_of_goals DESC, g.id ASC',
            ];
        case 'biggest_wins':
            return [
                'filter' => ' AND ABS(gr.actual_score - 0.5) >= 0.001',
                'order' => 'gr.goal_difference DESC, g.id ASC',
            ];
        case 'biggest_upsets':
            return [
                'filter' => amiga_games_highlights_lean_underdog_win_sql(),
                'order' => amiga_games_highlights_lean_winner_adjustment_sql() . ' DESC, g.id ASC',
            ];
        case 'most_goals':
        default:
            return [
                'filter' => '',
                'order' => 'gr.sum_of_goals DESC, g.id ASC',
            ];
    }
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function amiga_games_highlights_lean_limit_subquery_sql(
    string $board,
    AmigaSnapshotContext $ctx,
    string $scope,
    int $limit,
    string &$types,
    array &$params,
): string {
    $where = amiga_games_highlights_lean_scope_where_sql($ctx, $scope, $types, $params);
    $scan = amiga_games_highlights_board_limit_scan($board);

    return 'FROM amiga_game_ratings gr '
        . 'INNER JOIN amiga_games g ON g.id = gr.game_id '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'WHERE ' . $where . $scan['filter']
        . ' ORDER BY ' . $scan['order']
        . ' LIMIT ' . (int) $limit;
}

/** @param array{label: string, heading: string, default_sort_key: string, help?: string} $meta */
function amiga_games_highlights_board_tab_help_attrs(array $meta): string
{
    $help = trim((string) ($meta['help'] ?? ''));
    if ($help === '') {
        return '';
    }

    return ' data-k2-help="' . amiga_realm_games_all_h($help) . '" data-k2-tooltip-hide-title="1"';
}

function amiga_games_highlights_valid_board(string $board): string
{
    return isset(AMIGA_GAMES_HIGHLIGHT_BOARDS[$board]) ? $board : 'most_goals';
}

/** @return 'all'|'world-cup' */
function amiga_games_highlights_valid_scope(string $scope): string
{
    return amiga_games_valid_event_filter($scope);
}

function amiga_games_highlights_href(string $board, bool $scrollToAnchor = true, string $scope = 'all'): string
{
    $params = ['board' => amiga_games_highlights_valid_board($board)];
    if (amiga_games_highlights_valid_scope($scope) === 'world-cup') {
        $params['scope'] = 'world-cup';
    }
    $url = k2_amiga_route('amiga-games-highlights', $params);

    return $scrollToAnchor ? $url . '#' . AMIGA_GAMES_HIGHLIGHTS_ANCHOR : $url;
}

function amiga_games_highlights_context_href(string $board, bool $scrollToAnchor = true, string $scope = 'all'): string
{
    return amiga_url_with_context(amiga_games_highlights_href($board, $scrollToAnchor, $scope));
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_games_highlights_fetch(
    mysqli $con,
    string $board,
    AmigaSnapshotContext $ctx,
    int $limit = AMIGA_GAMES_HIGHLIGHTS_LIMIT,
    string $scope = 'all',
): array {
    $board = amiga_games_highlights_valid_board($board);
    $scope = amiga_games_highlights_valid_scope($scope);
    $limit = max(1, min(200, $limit));

    static $cache = [];
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = $board . '|' . $scope . '|' . $limit . '|'
        . ($cutoffForKey === null
            ? 'present'
            : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id']);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $types = '';
    $params = [];
    $limitScan = amiga_games_highlights_lean_limit_subquery_sql($board, $ctx, $scope, $limit, $types, $params);
    $scan = amiga_games_highlights_board_limit_scan($board);
    $sql = amiga_realm_games_hub_lean_select_sql()
        . 'FROM (SELECT g.id AS id ' . $limitScan . ') top_g '
        . 'INNER JOIN amiga_games g ON g.id = top_g.id '
        . amiga_realm_games_hub_lean_join_sql()
        . ' ORDER BY ' . $scan['order'];

    return $cache[$cacheKey] = amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}

function amiga_games_render_highlights_board_filter(string $activeBoard, string $activeScope = 'all'): void
{
    $activeBoard = amiga_games_highlights_valid_board($activeBoard);
    $activeScope = amiga_games_highlights_valid_scope($activeScope);
    $scopeTabs = [
        'all' => ['label' => 'All games'],
        'world-cup' => ['label' => 'World Cups'],
    ];
    ?>
<div class="k2-amiga-games-highlights-nav">
<nav class="k2-games-highlights-board-filter" data-k2-carry-scroll aria-label="Highlight board">
	<div class="k2-chrome-tabs__bar k2-games-highlights-board-filter__bar">
<?php foreach (AMIGA_GAMES_HIGHLIGHT_BOARDS as $boardId => $meta) {
    $isActive = $boardId === $activeBoard;
    ?>
		<a href="<?php echo amiga_realm_games_all_h(amiga_games_highlights_context_href($boardId, false, $activeScope)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?><?php echo amiga_games_highlights_board_tab_help_attrs($meta); ?>><?php echo amiga_realm_games_all_h($meta['label']); ?></a>
<?php } ?>
	</div>
</nav>
<nav class="k2-games-highlights-scope-filter" data-k2-carry-scroll aria-label="Game scope">
	<div class="k2-chrome-tabs__bar k2-games-highlights-scope-filter__bar">
<?php foreach ($scopeTabs as $scopeId => $tab) {
    $isActive = $scopeId === $activeScope;
    ?>
		<a href="<?php echo amiga_realm_games_all_h(amiga_games_highlights_context_href($activeBoard, false, $scopeId)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo amiga_realm_games_all_h($tab['label']); ?></a>
<?php } ?>
	</div>
</nav>
</div>
    <?php
}

function amiga_games_render_highlights_table(array $rows, string $board, string $scope = 'all'): void
{
    $board = amiga_games_highlights_valid_board($board);
    $scope = amiga_games_highlights_valid_scope($scope);
    $meta = AMIGA_GAMES_HIGHLIGHT_BOARDS[$board];
    $heading = $meta['heading'];
    if ($scope === 'world-cup') {
        $heading .= ' — World Cups';
    }
    if ($board === 'biggest_upsets') {
        $emptyMessage = $scope === 'world-cup'
            ? 'No World Cup underdog wins match this board yet.'
            : 'No underdog wins match this board yet.';
    } else {
        $emptyMessage = $scope === 'world-cup'
            ? 'No rated World Cup games match this board yet.'
            : 'No rated games match this board yet.';
    }
    ?>
<section class="k2-games-highlights" aria-labelledby="k2-amiga-games-highlights-heading">
	<h2 class="k2-panel-heading" id="k2-amiga-games-highlights-heading"><?php echo amiga_realm_games_all_h($heading); ?></h2>
	<?php k2_table_wrap_open(false); ?>
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-games-highlights-table" data-k2-table="sortable" data-k2-autorank="true"
	data-k2-default-sort="<?php echo (int) $meta['default_sort_col']; ?>" data-k2-default-direction="desc">
<thead>
	<tr>
		<th class="<?php echo k2_games_highlights_col_classes('rank'); ?>" data-k2-sort="number" data-k2-help="Rank in this board. Equal scores tie-break to lower game ID.">#</th>
		<th class="<?php echo k2_games_highlights_col_classes('id'); ?>" data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
		<th class="<?php echo k2_games_highlights_col_classes('date', 'k2-table-cell--left k2-table-cell--pad-left-xs'); ?>" data-k2-sort="number">Date</th>
		<th class="<?php echo k2_games_highlights_col_classes('tournament', 'k2-table-cell--left'); ?>" data-k2-sort="text" data-k2-help="Official KOA tournament that hosted this game.">Tournament</th>
		<th class="<?php echo k2_games_highlights_col_classes('team-a', 'k2-table-cell--right'); ?>" data-k2-sort="text" data-k2-help="Player listed as Team A in the result row.">Player A</th>
		<th class="<?php echo k2_games_highlights_col_classes('goals-a'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A.">A</th>
		<th class="<?php echo k2_games_highlights_col_classes('goals-b', 'k2-table-cell--left'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B.">B</th>
		<th class="<?php echo k2_games_highlights_col_classes('team-b', 'k2-table-cell--left'); ?>" data-k2-sort="text" data-k2-help="Player listed as Team B in the result row.">Player B</th>
		<th class="<?php echo k2_games_highlights_col_classes('gd', 'k2-table-cell--pad-left-md'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
		<th class="<?php echo k2_games_highlights_col_classes('sum'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
		<th class="<?php echo k2_games_highlights_col_classes('ts'); ?>" data-k2-sort="number" data-k2-tooltip-label="Top score" data-k2-help="Top score — the most goals either player scored in this game (e.g. 10 in 10–2).">TS</th>
		<th class="<?php echo k2_games_highlights_col_classes('rating-a', 'k2-table-cell--pad-left-md'); ?>" data-k2-sort="number" data-k2-help="Player A's Elo rating before this game.">Rating A</th>
		<th class="<?php echo k2_games_highlights_col_classes('rating-b'); ?>" data-k2-sort="number" data-k2-help="Player B's Elo rating before this game.">Rating B</th>
		<th class="<?php echo k2_games_highlights_col_classes('elo-diff'); ?>" data-k2-sort="number" data-k2-tooltip-label="Elo difference" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Elo Diff</th>
		<th class="<?php echo k2_games_highlights_col_classes('fav-es', 'k2-table-cell--pad-right-xs'); ?>" data-k2-sort="number" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite.">Fav ES</th>
		<th class="<?php echo k2_games_highlights_col_classes('adjustment', 'k2-table-cell--left'); ?>" data-k2-sort="number" data-k2-tooltip-label="Adjustment" data-k2-help="Rating change from expected vs actual score.">Adjustment</th>
		<th class="<?php echo k2_games_highlights_col_classes('adjustment-lost', 'k2-table-cell--left'); ?>" data-k2-sort="number"><span class="visually-hidden">Adjustment lost</span></th>
	</tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
	<tr>
		<td colspan="17" class="k2-games-day__empty k2-table-cell--left"><?php echo amiga_realm_games_all_h($emptyMessage); ?></td>
	</tr>
<?php } else { ?>
<?php foreach ($rows as $row) { ?>
	<?php echo amiga_rated_game_highlights_row_html($row, [
        'id_mode' => 'link',
        'show_ts_column' => true,
        'show_gd_column' => true,
        'show_sum_column' => true,
        'highlight_winner_goal' => true,
        'team_a_align' => 'right',
    ]); ?>
<?php } ?>
<?php } ?>
</tbody>
</table>
	<?php k2_table_wrap_close(); ?>
</section>
    <?php
}
