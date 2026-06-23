<?php
/**
 * Sortable World Cup stats tables — wing 2 sub-views.
 *
 * @see docs/amiga-world-cup-stats-table-plan.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_player_display_names.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

const AMIGA_WC_STATS_ANCHOR_COL = 0;
const AMIGA_WC_STATS_DEFAULT_SORT_COL = 1;

/** @var list<string> */
const AMIGA_WC_STATS_VIEWS = ['goals', 'dds', 'participation', 'geography', 'podium'];

function amiga_world_cup_stats_tournament_link(int $tournamentId, string $name): string
{
    $href = amiga_tournament_href(amiga_tournament_event_stats_url($tournamentId));

    return '<a href="' . k2_h($href) . '">' . k2_h($name) . '</a>';
}

function amiga_world_cup_stats_podium_cell(int $playerId, array $nameMap): string
{
    if ($playerId < 1) {
        return k2_fmt_dash();
    }

    $name = k2_player_display_name($nameMap, $playerId);

    return k2_amiga_player_link($playerId, $name);
}

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

                return amiga_world_cup_stats_tournament_link(
                    (int) ($row['tournament_id'] ?? 0),
                    (string) ($row['tournament_name'] ?? ''),
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
            'help' => 'Rated games in this World Cup.',
            'render' => static function (array $row, array $nameMap): string {
                unset($nameMap);

                return k2_fmt_games_played((int) ($row['rated_games'] ?? 0));
            },
        ],
    ];
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function amiga_world_cup_stats_view_columns(): array
{
    return [
        'goals' => [
            ['label' => 'Goals', 'sort' => 'number', 'help' => 'Total goals in rated games.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['goals'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'G/G', 'sort' => 'number', 'help' => 'Goals per rated game.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['goals_per_game'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'High', 'sort' => 'number', 'help' => 'Games with ten or more goals scored (both sides).', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['high_scoring_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'High %', 'sort' => 'number', 'help' => 'High-scoring games as a share of rated games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['high_scoring_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Low', 'sort' => 'number', 'help' => 'Games with three or fewer total goals.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['low_scoring_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Low %', 'sort' => 'number', 'help' => 'Low-scoring games as a share of rated games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['low_scoring_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Blowouts', 'sort' => 'number', 'help' => 'Games with a winning margin of five goals or more.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['blowout_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Blowout %', 'sort' => 'number', 'help' => 'Blowout games as a share of rated games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio(
                amiga_world_cup_stats_games($row) > 0 ? ((int) ($row['blowout_games'] ?? 0)) / amiga_world_cup_stats_games($row) : null,
                amiga_world_cup_stats_games($row),
            )],
            ['label' => 'Draw %', 'sort' => 'number', 'help' => 'Draws as a share of rated games.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['draw_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Max sum', 'sort' => 'number', 'help' => 'Highest total goals in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
                isset($row['highest_goal_sum']) ? (int) $row['highest_goal_sum'] : null,
                isset($row['highest_goal_sum_game_id']) ? (int) $row['highest_goal_sum_game_id'] : null,
            )],
            ['label' => 'Min sum', 'sort' => 'number', 'help' => 'Lowest total goals in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
                isset($row['lowest_goal_sum']) ? (int) $row['lowest_goal_sum'] : null,
                isset($row['lowest_goal_sum_game_id']) ? (int) $row['lowest_goal_sum_game_id'] : null,
            )],
            ['label' => 'Max draw', 'sort' => 'number', 'help' => 'Highest total goals in a drawn game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
                isset($row['highest_scoring_draw_sum']) ? (int) $row['highest_scoring_draw_sum'] : null,
                isset($row['highest_scoring_draw_game_id']) ? (int) $row['highest_scoring_draw_game_id'] : null,
            )],
            ['label' => 'Max margin', 'sort' => 'number', 'help' => 'Largest winning margin in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
                isset($row['biggest_margin']) ? (int) $row['biggest_margin'] : null,
                isset($row['biggest_margin_game_id']) ? (int) $row['biggest_margin_game_id'] : null,
            )],
            ['label' => 'Max player goals', 'sort' => 'number', 'help' => 'Most goals scored by one player in a single game.', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_peak_cell(
                isset($row['most_goals_one_player_game']) ? (int) $row['most_goals_one_player_game'] : null,
                isset($row['most_goals_one_player_game_id']) ? (int) $row['most_goals_one_player_game_id'] : null,
            )],
        ],
        'dds' => [
            ['label' => 'DD slots', 'sort' => 'number', 'help' => 'Double-digit participant slots across all games.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['double_digit_slots'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'DD %', 'sort' => 'number', 'help' => 'DD slots per rated game.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['double_digit_rate'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'CS slots', 'sort' => 'number', 'help' => 'Clean-sheet participant slots across all games.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['clean_sheet_slots'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'CS %', 'sort' => 'number', 'help' => 'CS slots per rated game.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['clean_sheet_rate'] ?? null, amiga_world_cup_stats_games($row))],
        ],
        'participation' => [
            ['label' => '1st WC', 'sort' => 'number', 'help' => 'Players appearing in their first World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['first_time_wc_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Year %', 'sort' => 'number', 'help' => 'This World Cup as a share of all rated games in the calendar year.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['share_of_year_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Matchups', 'sort' => 'number', 'help' => 'Unique player pairings in this World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_opponent_pairs'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'G/player', 'sort' => 'number', 'help' => 'Average rated games per participant.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['avg_games_per_player'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'Opp/player', 'sort' => 'number', 'help' => 'Average distinct opponents per participant.', 'render' => static fn (array $row, array $m) => k2_fmt_decimal($row['avg_opponents_per_player'] ?? null, amiga_world_cup_stats_games($row), 2)],
            ['label' => 'Champ g', 'sort' => 'number', 'help' => 'Rated games played by the gold medalist.', 'render' => static fn (array $row, array $m) => k2_fmt_optional_int($row['champion_game_count'] ?? null)],
            ['label' => 'Group', 'sort' => 'number', 'help' => 'Rated games in group or league phases.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['group_games'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'KO', 'sort' => 'number', 'help' => 'Rated games in knockout phases.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['knockout_games'] ?? null, amiga_world_cup_stats_games($row))],
        ],
        'geography' => [
            ['label' => 'Nations', 'sort' => 'number', 'help' => 'Distinct player nationalities in this World Cup.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_player_nationalities'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Host players', 'sort' => 'number', 'help' => 'Distinct players from the host country.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_host_country_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Guests', 'sort' => 'number', 'help' => 'Distinct players not from the host country.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_guest_players'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Guest %', 'sort' => 'number', 'help' => 'Guest players as a share of all participants.', 'render' => static fn (array $row, array $m) => k2_fmt_pct_from_ratio($row['guest_player_share'] ?? null, amiga_world_cup_stats_games($row))],
            ['label' => 'Nation pairs', 'sort' => 'number', 'help' => 'Distinct nationality pairings that met in a game.', 'render' => static fn (array $row, array $m) => k2_fmt_count($row['distinct_opponent_countries_pairs'] ?? null, amiga_world_cup_stats_games($row))],
        ],
        'podium' => [
            ['label' => 'Gold', 'sort' => 'text', 'align' => 'left', 'help' => '', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_podium_cell((int) ($row['gold_player_id'] ?? 0), $m)],
            ['label' => 'Silver', 'sort' => 'text', 'align' => 'left', 'help' => '', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_podium_cell((int) ($row['silver_player_id'] ?? 0), $m)],
            ['label' => 'Bronze', 'sort' => 'text', 'align' => 'left', 'help' => '', 'render' => static fn (array $row, array $m) => amiga_world_cup_stats_podium_cell((int) ($row['bronze_player_id'] ?? 0), $m)],
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<int, string> $nameMap
 */
function amiga_world_cup_stats_render_view(string $view, array $rows, array $nameMap): void
{
    $views = amiga_world_cup_stats_view_columns();
    if (!isset($views[$view])) {
        $view = 'goals';
    }

    $anchorCols = amiga_world_cup_stats_anchor_columns();
    $statCols = $views[$view];
    $allCols = array_merge($anchorCols, $statCols);
    $colCount = count($allCols);

    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_WC_STATS_DEFAULT_SORT_COL);
    $tableClass = 'k2-table k2-table--numeric-default k2-table--calm-stats k2-table--world-cup-stats k2-table--world-cup-stats-' . preg_replace('/[^a-z0-9-]/', '', $view);
    $useScrollMirror = $view === 'goals';
    $wrapClass = 'k2-table-wrap' . ($useScrollMirror ? '' : ' k2-table-wrap--natural-width');
    $wrapMirrorAttr = $useScrollMirror ? ' data-k2-scroll-mirror' : '';
    ?>
<div class="k2-amiga-world-cups-stats-table">
<div class="<?php echo $wrapClass; ?>"<?php echo $wrapMirrorAttr; ?>>
<table class="<?php echo $tableClass; ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo AMIGA_WC_STATS_ANCHOR_COL; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="desc">
	<thead>
		<tr>
<?php foreach ($allCols as $col) {
    $align = $col['align'] ?? '';
    $thClass = $align === 'left' ? ' class="k2-table-cell--left"' : ($align === 'right' ? ' class="k2-table-cell--right"' : '');
    $help = trim((string) ($col['help'] ?? ''));
    $helpAttr = $help !== '' ? ' data-k2-help="' . k2_h($help) . '"' : '';
    ?>
			<th<?php echo $thClass; ?> data-k2-sort="<?php echo k2_h($col['sort']); ?>"<?php echo $helpAttr; ?>><?php echo k2_h($col['label']); ?></th>
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
            $tdClass = $align === 'left' ? 'k2-table-cell--left' : ($align === 'right' ? 'k2-table-cell--right' : '');
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
</div>
</div>
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
