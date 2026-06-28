<?php
/**
 * Sortable World Cup stats tables — wing 2 sub-views.
 *
 * @see docs/amiga-world-cup-stats-table-plan.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

const AMIGA_WC_STATS_ANCHOR_COL = 0;
const AMIGA_WC_STATS_DEFAULT_SORT_COL = 1;

/** @var list<string> */
const AMIGA_WC_STATS_VIEWS = ['participation', 'goals', 'dds', 'geography'];

function amiga_world_cup_stats_peak_cell(?int $metric, ?int $gameId): string
{
    if ($metric === null) {
        return k2_fmt_dash();
    }
    $value = (int) $metric;
    if ($gameId !== null && (int) $gameId > 0) {
        $href = k2_h(k2_amiga_route('amiga-game', ['id' => (int) $gameId]));

        return '<a href="' . $href . '">' . $value . '</a>';
    }

    return (string) $value;
}

/**
 * @param array<string, mixed> $row
 */
function amiga_world_cup_stats_games(array $row): int
{
    return (int) ($row['rated_games'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_world_cup_stats_year_sort_value(array $row): string
{
    $year = (int) ($row['calendar_year'] ?? 0);
    $chrono = isset($row['event_chrono']) && $row['event_chrono'] !== null && $row['event_chrono'] !== ''
        ? (float) $row['event_chrono']
        : 0.0;

    return sprintf('%04d.%09f', max(0, $year), $chrono);
}

/**
 * @return list<array{label: string, sort: string, align: string, help: string, render: callable}>
 */
function amiga_world_cup_stats_anchor_columns(): array
{
    return [
        [
            'label' => 'Tournament',
            'sort' => 'text',
            'align' => 'left',
            'help' => '',
            'render' => static function (array $row, array $nameMap): string {
                unset($nameMap);

                return k2_amiga_lb_tournament_cell(
                    (int) ($row['tournament_id'] ?? 0),
                    (string) ($row['tournament_name'] ?? ''),
                    (string) ($row['host_country'] ?? ''),
                );
            },
        ],
        [
            'label' => 'Year',
            'sort' => 'number',
            'align' => 'right',
            'help' => '',
            'sort_value' => static fn (array $row): string => amiga_world_cup_stats_year_sort_value($row),
            'render' => static function (array $row, array $nameMap): string {
                unset($nameMap);

                return k2_fmt_optional_int($row['calendar_year'] ?? null);
            },
        ],
        [
            'label' => 'Players',
            'sort' => 'number',
            'align' => '',
            'help' => 'Distinct players in this World Cup.',
            'render' => static function (array $row, array $nameMap): string {
                unset($nameMap);
                $games = (int) ($row['rated_games'] ?? 0);

                return k2_fmt_count($row['distinct_players'] ?? null, $games);
            },
        ],
        [
            'label' => 'Games',
            'sort' => 'number',
            'align' => '',
            'help' => 'Games in this World Cup.',
            'render' => static function (array $row, array $nameMap): string {
                unset($nameMap);

                return k2_fmt_games_played((int) ($row['rated_games'] ?? 0));
            },
        ],
    ];
}

/**
 * Per-game goal peak columns (shared on DDs & CSs wing).
 *
 * @return list<array<string, mixed>>
 */
function amiga_world_cup_stats_peak_columns(): array
{
    return [
        ['label' => 'Max draw', 'sort' => 'number', 'help' => 'Highest total goals in a drawn game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
            isset($row['highest_scoring_draw_sum']) ? (int) $row['highest_scoring_draw_sum'] : null,
            isset($row['highest_scoring_draw_game_id']) ? (int) $row['highest_scoring_draw_game_id'] : null,
        )],
        ['label' => 'Max win', 'sort' => 'number', 'help' => 'Largest win by goal margin in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
            isset($row['biggest_margin']) ? (int) $row['biggest_margin'] : null,
            isset($row['biggest_margin_game_id']) ? (int) $row['biggest_margin_game_id'] : null,
        )],
        ['label' => 'Max GF', 'sort' => 'number', 'tooltip_label' => 'Max goals for', 'help' => 'Most goals scored by one player in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
            isset($row['most_goals_one_player_game']) ? (int) $row['most_goals_one_player_game'] : null,
            isset($row['most_goals_one_player_game_id']) ? (int) $row['most_goals_one_player_game_id'] : null,
        )],
        ['label' => 'Max sum', 'sort' => 'number', 'help' => 'Highest total goals in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
            isset($row['highest_goal_sum']) ? (int) $row['highest_goal_sum'] : null,
            isset($row['highest_goal_sum_game_id']) ? (int) $row['highest_goal_sum_game_id'] : null,
        )],
        ['label' => 'Min sum', 'sort' => 'number', 'help' => 'Lowest total goals in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
            isset($row['lowest_goal_sum']) ? (int) $row['lowest_goal_sum'] : null,
            isset($row['lowest_goal_sum_game_id']) ? (int) $row['lowest_goal_sum_game_id'] : null,
        )],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function amiga_world_cup_stats_view_columns(): array
{
    return [
        'goals' => [
            ['label' => 'Goals', 'sort' => 'number', 'help' => 'Total goals in games.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['goals'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'G/G', 'sort' => 'number', 'help' => 'Goals per game.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['goals_per_game'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'High', 'sort' => 'number', 'help' => 'Games with ten or more goals scored (both sides).', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['high_scoring_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'High %', 'sort' => 'number', 'help' => 'High-scoring games as a share of games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['high_scoring_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Low', 'sort' => 'number', 'help' => 'Games with three or fewer total goals.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['low_scoring_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Low %', 'sort' => 'number', 'help' => 'Low-scoring games as a share of games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['low_scoring_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Blowouts', 'sort' => 'number', 'help' => 'Games with a winning margin of five goals or more.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['blowout_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Blowout %', 'sort' => 'number', 'help' => 'Blowout games as a share of games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['blowout_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Draw %', 'sort' => 'number', 'help' => 'Draws as a share of games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['draw_rate'] ?? null, amiga_world_cup_stats_games($row))],
        ],
        'dds' => array_merge([
            ['label' => 'DDs', 'sort' => 'number', 'help' => 'Times a player scored ten or more goals in a game (each player counts separately; up to two per game).', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['double_digit_slots'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'DD %', 'sort' => 'number', 'help' => 'Double digits per game (total DDs ÷ games).', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['double_digit_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'CSs', 'sort' => 'number', 'help' => 'Times a player held the opponent scoreless in a game (each player counts separately; up to two per game).', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['clean_sheet_slots'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'CS %', 'sort' => 'number', 'help' => 'Clean sheets per game (total CSs ÷ games).', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['clean_sheet_rate'] ?? null, amiga_world_cup_stats_games($row))],
        ], amiga_world_cup_stats_peak_columns()),
        'participation' => [
            ['label' => '1st WC', 'sort' => 'number', 'help' => 'Players appearing in their first World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['first_time_wc_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Matchups', 'sort' => 'number', 'help' => 'Unique player pairings in this World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_opponent_pairs'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'G/player', 'sort' => 'number', 'help' => 'Average games per participant.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['avg_games_per_player'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'Opp/player', 'sort' => 'number', 'help' => 'Average distinct opponents per participant.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['avg_opponents_per_player'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'Champ g', 'sort' => 'number', 'help' => 'Games played by the gold medalist.', 'render' => static fn (array $row, array $m) => k2_fmt_optional_int($row['champion_game_count'] ?? null)],
            ['label' => 'Group', 'sort' => 'number', 'help' => 'Games in group or league phases.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['group_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'KO', 'sort' => 'number', 'help' => 'Games in knockout phases.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['knockout_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Year %', 'sort' => 'number', 'help' => 'This World Cup as a share of all games in the calendar year.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['share_of_year_games'] ?? null, amiga_world_cup_stats_games($row))],
        ],
        'geography' => [
            ['label' => 'Nations', 'sort' => 'number', 'help' => 'Distinct player nationalities in this World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_player_nationalities'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Guests', 'sort' => 'number', 'help' => 'Players not from the host country.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_guest_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Host players', 'sort' => 'number', 'help' => 'Players from the host country.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_host_country_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Guest %', 'sort' => 'number', 'help' => 'Guest players as a share of all participants.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['guest_player_share'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Nation pairs', 'sort' => 'number', 'help' => 'Distinct nationality pairings that met in a game.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_opponent_countries_pairs'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Intl games', 'tooltip_label' => 'International games', 'sort' => 'number', 'help' => 'Games where the players are from 2 different countries.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['international_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Intl %', 'sort' => 'number', 'help' => 'International games as a share of games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['international_game_share'] ?? null, amiga_world_cup_stats_games($row))],
        ],
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_world_cup_stats_columns_for_view(string $view): array
{
    $views = amiga_world_cup_stats_view_columns();
    $statCols = $views[$view] ?? $views['participation'];
    $anchorCols = amiga_world_cup_stats_anchor_columns();

    if ($view !== 'participation') {
        return array_merge($anchorCols, $statCols);
    }

    $firstWcCol = null;
    $restStats = [];
    foreach ($statCols as $col) {
        if (($col['label'] ?? '') === '1st WC') {
            $firstWcCol = $col;
        } else {
            $restStats[] = $col;
        }
    }

    if ($firstWcCol === null) {
        return array_merge($anchorCols, $statCols);
    }

    return array_merge(
        array_slice($anchorCols, 0, 3),
        [$firstWcCol],
        [$anchorCols[3]],
        $restStats,
    );
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<int, string> $nameMap
 */
function amiga_world_cup_stats_render_view(string $view, array $rows, array $nameMap): void
{
    $views = amiga_world_cup_stats_view_columns();
    if (!isset($views[$view])) {
        $view = 'participation';
    }

    $allCols = amiga_world_cup_stats_columns_for_view($view);
    $colCount = count($allCols);

    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_WC_STATS_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $viewSlug = preg_replace('/[^a-z0-9-]/', '', $view);
    $tableClass = k2_table_ranked_sortable_class('k2-table--world-cup-stats k2-table--world-cup-stats-' . $viewSlug);
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo AMIGA_WC_STATS_ANCHOR_COL; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>" data-k2-skip-initial-sort="1">
	<thead>
		<tr>
<?php foreach ($allCols as $colIndex => $col) {
    $align = $col['align'] ?? '';
    $thExtra = $align === 'left' ? 'k2-table-cell--left' : ($align === 'right' ? 'k2-table-cell--right' : ($align === 'center' ? 'k2-table-cell--center' : ''));
    $thSortAttr = k2_table_sortable_th_attr($colIndex, $defaultSortCol, $defaultSortDir, $thExtra);
    $help = trim((string) ($col['help'] ?? ''));
    $helpAttr = $help !== '' ? ' data-k2-help="' . k2_h($help) . '"' : '';
    $tooltipLabel = trim((string) ($col['tooltip_label'] ?? ''));
    $tooltipLabelAttr = $tooltipLabel !== '' ? ' data-k2-tooltip-label="' . k2_h($tooltipLabel) . '"' : '';
    $thLabel = k2_h($col['label']);
    ?>
			<th<?php echo $thSortAttr; ?> data-k2-sort="<?php echo k2_h($col['sort']); ?>"<?php echo $helpAttr; ?><?php echo $tooltipLabelAttr; ?>><?php echo $thLabel; ?></th>
<?php } ?>
		</tr>
	</thead>
	<tbody class="black">
<?php if ($rows === []) { ?>
		<tr>
			<td colspan="<?php echo $colCount; ?>" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No World Cups in scope.</td>
		</tr>
<?php } ?>
<?php
    $anchorCol = AMIGA_WC_STATS_ANCHOR_COL;
    foreach ($rows as $row) {
        ?>
		<tr>
<?php
        foreach ($allCols as $colIndex => $col) {
            $align = $col['align'] ?? '';
            $tdClass = $align === 'left' ? 'k2-table-cell--left' : ($align === 'right' ? 'k2-table-cell--right' : ($align === 'center' ? 'k2-table-cell--center' : ''));
            $tdAttr = k2_table_body_td_attr($colIndex, $anchorCol, $defaultSortCol, $tdClass);
            $sortValue = '';
            if (isset($col['sort_value']) && is_callable($col['sort_value'])) {
                $sortValue = ' data-k2-sort-value="' . k2_h($col['sort_value']($row)) . '"';
            }
            $render = $col['render'];
            ?>
			<td<?php echo $tdAttr !== '' ? ' ' . trim($tdAttr) : ''; ?><?php echo $sortValue; ?>><?php echo $render($row, $nameMap); ?></td>
<?php } ?>
		</tr>
<?php } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows
 */
function amiga_world_cup_stats_render_footer(int $rowCount): void
{
    require_once __DIR__ . '/amiga_snapshot_url.php';
    $activityHref = htmlspecialchars(amiga_url_with_context('/amiga/activity.php'), ENT_QUOTES, 'UTF-8');
    ?>
<p class="k2-amiga-world-cups-stats-foot" style="margin:0 0 2rem;color:var(--k2-text-secondary)">
	<?php echo number_format($rowCount); ?> World Cup<?php echo $rowCount === 1 ? '' : 's'; ?> in scope.
	Realm-wide WC trends by calendar year: <a href="<?php echo $activityHref; ?>">Activity</a>.
</p>
    <?php
}
