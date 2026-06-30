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
require_once __DIR__ . '/amiga_realm_games_hub_table.php';

const AMIGA_GAMES_HIGHLIGHTS_LIMIT = 100;
const AMIGA_GAMES_HIGHLIGHTS_ANCHOR = 'k2-amiga-games-highlights';

/** @var array<string, array{label: string, heading: string, default_sort_key: string, help?: string}> */
const AMIGA_GAMES_HIGHLIGHT_BOARDS = [
    'most_goals' => [
        'label' => 'Most goals',
        'heading' => 'Most total goals',
        'default_sort_key' => 'sum',
    ],
    'biggest_draws' => [
        'label' => 'Biggest draws',
        'heading' => 'Biggest draws',
        'default_sort_key' => 'sum',
    ],
    'biggest_wins' => [
        'label' => 'Biggest wins',
        'heading' => 'Biggest wins',
        'default_sort_key' => 'gd',
    ],
    'top_score' => [
        'label' => 'Top score',
        'heading' => 'Top score',
        'default_sort_key' => 'top_score',
        'help' => 'The highest goals one player scored in a single game — e.g. 10 in a 10–2.',
    ],
    'biggest_upsets' => [
        'label' => 'Biggest upsets',
        'heading' => 'Biggest upsets',
        'default_sort_key' => 'adjustment',
        'help' => 'When the lower-rated player won — ranked by how many rating points they gained.',
    ],
];

/** Decisive games where the winner had the lower pre-game rating (excludes flat early-era ties). */
function amiga_games_highlights_underdog_win_sql(): string
{
    return ' AND ABS(r.ActualScore - 0.5) >= 0.001'
        . ' AND ('
        . '(ABS(r.ActualScore - 1.0) < 0.001 AND r.RatingA < r.RatingB)'
        . ' OR (ABS(r.ActualScore - 0.0) < 0.001 AND r.RatingB < r.RatingA)'
        . ')';
}

function amiga_games_highlights_winner_adjustment_sql(): string
{
    return 'CASE'
        . ' WHEN ABS(r.ActualScore - 1.0) < 0.001 THEN r.AdjustmentA'
        . ' WHEN ABS(r.ActualScore - 0.0) < 0.001 THEN r.AdjustmentB'
        . ' ELSE 0'
        . ' END';
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

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $where = '1=1' . $cutoffSql;
    if ($scope === 'world-cup') {
        $where .= ' AND ' . amiga_games_world_cup_name_sql('r.tournament_name');
    }

    $select = amiga_realm_games_hub_select_sql() . amiga_rated_games_from_sql() . ' WHERE ' . $where;

    switch ($board) {
        case 'biggest_draws':
            $sql = $select . ' AND ABS(r.ActualScore - 0.5) < 0.001'
                . ' ORDER BY r.SumOfGoals DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
        case 'top_score':
            $sql = $select . ' ORDER BY GREATEST(r.GoalsA, r.GoalsB) DESC, r.SumOfGoals DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
        case 'biggest_wins':
            $sql = $select . ' AND ABS(r.ActualScore - 0.5) >= 0.001'
                . ' ORDER BY r.GoalDifference DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
        case 'biggest_upsets':
            $sql = $select . amiga_games_highlights_underdog_win_sql()
                . ' ORDER BY ' . amiga_games_highlights_winner_adjustment_sql() . ' DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
        case 'most_goals':
        default:
            $sql = $select . ' ORDER BY r.SumOfGoals DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
    }

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
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
	<?php amiga_realm_games_hub_render_table($rows, [
        'show_rank' => true,
        'default_sort_col' => amiga_realm_games_all_sort_col_index(
            (string) ($meta['default_sort_key'] ?? 'sum'),
            true,
        ),
        'default_sort_dir' => 'desc',
        'empty_message' => $emptyMessage,
    ]); ?>
</section>
    <?php
}
