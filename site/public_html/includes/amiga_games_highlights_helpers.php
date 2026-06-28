<?php
/**
 * Amiga Games hub — all-time spectacle boards.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_realm_games_hub_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_realm_games_hub_table.php';

const AMIGA_GAMES_HIGHLIGHTS_LIMIT = 100;
const AMIGA_GAMES_HIGHLIGHTS_ANCHOR = 'k2-amiga-games-highlights';

/** @var array<string, array{label: string, heading: string, default_sort_col: int}> */
const AMIGA_GAMES_HIGHLIGHT_BOARDS = [
    'most_goals' => [
        'label' => 'Most goals',
        'heading' => 'Most total goals',
        'default_sort_col' => 9,
    ],
    'biggest_draws' => [
        'label' => 'Biggest draws',
        'heading' => 'Biggest draws',
        'default_sort_col' => 9,
    ],
    'biggest_wins' => [
        'label' => 'Biggest wins',
        'heading' => 'Biggest wins',
        'default_sort_col' => 8,
    ],
    'top_score' => [
        'label' => 'Top score',
        'heading' => 'Top score',
        'default_sort_col' => 10,
    ],
];

function amiga_games_highlights_valid_board(string $board): string
{
    return isset(AMIGA_GAMES_HIGHLIGHT_BOARDS[$board]) ? $board : 'most_goals';
}

function amiga_games_highlights_href(string $board, bool $scrollToAnchor = true): string
{
    $url = k2_amiga_route('amiga-games-highlights', [
        'board' => amiga_games_highlights_valid_board($board),
    ]);

    return $scrollToAnchor ? $url . '#' . AMIGA_GAMES_HIGHLIGHTS_ANCHOR : $url;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_games_highlights_fetch(
    mysqli $con,
    string $board,
    AmigaSnapshotContext $ctx,
    int $limit = AMIGA_GAMES_HIGHLIGHTS_LIMIT,
): array {
    $board = amiga_games_highlights_valid_board($board);
    $limit = max(1, min(200, $limit));

    $types = '';
    $params = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $types, $params);
    $where = '1=1' . $cutoffSql;

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
        case 'most_goals':
        default:
            $sql = $select . ' ORDER BY r.SumOfGoals DESC, r.id ASC LIMIT ' . (int) $limit;
            break;
    }

    return amiga_realm_games_hub_query_all($con, $sql, $types, $params);
}

function amiga_games_render_highlights_board_filter(string $activeBoard): void
{
    ?>
<nav class="k2-games-highlights-board-filter" data-k2-carry-scroll aria-label="Highlight board">
	<div class="k2-chrome-tabs__bar k2-games-highlights-board-filter__bar">
<?php foreach (AMIGA_GAMES_HIGHLIGHT_BOARDS as $boardId => $meta) {
    $isActive = $boardId === $activeBoard;
    ?>
		<a href="<?php echo amiga_realm_games_all_h(amiga_games_highlights_href($boardId, false)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo amiga_realm_games_all_h($meta['label']); ?></a>
<?php } ?>
	</div>
</nav>
    <?php
}

function amiga_games_render_highlights_table(array $rows, string $board): void
{
    $board = amiga_games_highlights_valid_board($board);
    $meta = AMIGA_GAMES_HIGHLIGHT_BOARDS[$board];
    ?>
<section class="k2-games-highlights" aria-labelledby="k2-amiga-games-highlights-heading">
	<h2 class="k2-panel-heading" id="k2-amiga-games-highlights-heading"><?php echo amiga_realm_games_all_h($meta['heading']); ?></h2>
	<?php amiga_realm_games_hub_render_table($rows, [
        'show_rank' => true,
        'default_sort_col' => (int) $meta['default_sort_col'],
        'default_sort_dir' => 'desc',
        'empty_message' => 'No rated games match this board yet.',
    ]); ?>
</section>
    <?php
}
