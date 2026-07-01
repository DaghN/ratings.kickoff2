<?php
/**
 * Status-style league tables (points + activity) — shared by status.php and league.php.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

function k2_league_table_sort_value_attr(bool $sortable, int|float|string $value): string
{
    if (!$sortable) {
        return '';
    }

    return ' data-k2-sort-value="' . k2_status_h((string) $value) . '"';
}

if (!function_exists('k2_status_player_link')) {
    function k2_status_player_link(int $id, string $name): string
    {
        return '<a class="k2-link-star" href="' . k2_h(k2_player_profile_href($id)) . '">' . k2_status_h($name) . '</a>';
    }
}

if (!function_exists('k2_status_player_link_or_name')) {
    function k2_status_player_link_or_name(int $id, string $name): string
    {
        if ($id > 0) {
            return k2_status_player_link($id, $name);
        }

        return k2_status_h($name);
    }
}

if (!function_exists('k2_status_league_podium_medal')) {
    function k2_status_league_podium_medal(int $rank, string $paletteSet = 'classic'): string
    {
        static $medalByRank = [
            1 => ['gold', '1st place', '1'],
            2 => ['silver', '2nd place', '2'],
            3 => ['bronze', '3rd place', '3'],
        ];
        static $medalInstance = 0;

        if (!isset($medalByRank[$rank])) {
            return '';
        }

        ++$medalInstance;
        [$variant, $ariaLabel, $place] = $medalByRank[$rank];

        return k2_status_league_podium_medal_svg($variant, $ariaLabel, $place, $medalInstance, $paletteSet);
    }

    /** Honour-table column headers — SVG disk matches k2-lb-honours-medal-td value gradients. */
    function k2_lb_honours_medal_th(int $rank): string
    {
        return k2_status_league_podium_medal($rank, 'honours');
    }

    function k2_status_league_podium_medal_svg(string $variant, string $ariaLabel, string $place, int $instance, string $paletteSet = 'classic'): string
    {
        $id = 'k2-medal-' . $variant . '-' . $instance;
        $classicPalettes = [
            'gold' => [
                'disk' => ['#fff9e6', '#ffe566', '#d4af37', '#8b6914'],
                'rim' => '#5c4508',
                'glyph' => '#4a3606',
                'glyphShadow' => '#f7e7a8',
                'ribbon' => ['#ff4d6d', '#c9184a', '#800f2f'],
                'ribbonFold' => '#590d22',
            ],
            'silver' => [
                'disk' => ['#ffffff', '#f0f4f8', '#b8c4ce', '#6b7a86'],
                'rim' => '#3d4852',
                'glyph' => '#2a3238',
                'glyphShadow' => '#eef2f6',
                'ribbon' => ['#4cc9f0', '#4895ef', '#2b4a7a'],
                'ribbonFold' => '#1b2f4d',
            ],
            'bronze' => [
                'disk' => ['#ffe8d6', '#e8a87c', '#cd7f32', '#7a4a1f'],
                'rim' => '#4a2c12',
                'glyph' => '#3a220f',
                'glyphShadow' => '#f5d4bc',
                'ribbon' => ['#95d5b2', '#52b788', '#2d6a4f'],
                'ribbonFold' => '#1b4332',
            ],
        ];
        /* Keep in sync with honour-table .k2-country-hero__medal-value--* gradients in theme.css */
        $honoursPalettes = [
            'gold' => [
                'disk' => ['#fffef8', '#fff59d', '#ffc107', '#ffab00'],
                'rim' => '#c88600',
                'glyph' => '#5a4200',
                'glyphShadow' => '#ffe082',
                'ribbon' => ['#ff4d6d', '#c9184a', '#800f2f'],
                'ribbonFold' => '#590d22',
            ],
            'silver' => [
                'disk' => ['#ffffff', '#f5f9fc', '#b0bec9', '#8899a8'],
                'rim' => '#5c6773',
                'glyph' => '#2a3238',
                'glyphShadow' => '#f0f4f8',
                'ribbon' => ['#4cc9f0', '#4895ef', '#2b4a7a'],
                'ribbonFold' => '#1b2f4d',
            ],
            'bronze' => [
                'disk' => ['#fff6ed', '#ffbc85', '#e07a3a', '#c4622d'],
                'rim' => '#a04e22',
                'glyph' => '#4a280f',
                'glyphShadow' => '#ffbc85',
                'ribbon' => ['#95d5b2', '#52b788', '#2d6a4f'],
                'ribbonFold' => '#1b4332',
            ],
        ];
        $palettes = $paletteSet === 'honours' ? $honoursPalettes : $classicPalettes;
        $p = $palettes[$variant] ?? $palettes['bronze'];
        $aria = k2_status_h($ariaLabel);
        $placeEsc = k2_status_h($place);

        return '<span class="k2-status-medal k2-status-medal--' . k2_status_h($variant) . '" role="img" aria-label="' . $aria . '">'
            . '<svg class="k2-status-medal__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
            . '<defs>'
            . '<radialGradient id="' . $id . '-disk" cx="38%" cy="36%" r="68%">'
            . '<stop offset="0%" stop-color="' . $p['disk'][0] . '"/>'
            . '<stop offset="38%" stop-color="' . $p['disk'][1] . '"/>'
            . '<stop offset="72%" stop-color="' . $p['disk'][2] . '"/>'
            . '<stop offset="100%" stop-color="' . $p['disk'][3] . '"/>'
            . '</radialGradient>'
            . '<linearGradient id="' . $id . '-shine" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#ffffff" stop-opacity="0"/>'
            . '<stop offset="45%" stop-color="#ffffff" stop-opacity="0.85"/>'
            . '<stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>'
            . '</linearGradient>'
            . '</defs>'
            . '<circle cx="16" cy="16" r="14" fill="' . $p['rim'] . '"/>'
            . '<circle cx="16" cy="16" r="12.5" fill="url(#' . $id . '-disk)"/>'
            . '<circle cx="16" cy="16" r="12.5" fill="none" stroke="' . $p['disk'][0] . '" stroke-opacity="0.55" stroke-width="0.65"/>'
            . '<circle cx="16" cy="16" r="9" fill="none" stroke="' . $p['rim'] . '" stroke-opacity="0.35" stroke-width="0.5"/>'
            . '<ellipse class="k2-status-medal__glint" cx="12" cy="12.5" rx="5" ry="3" fill="url(#' . $id . '-shine)" transform="rotate(-28 12 12.5)"/>'
            . '<text x="16" y="18.5" text-anchor="middle" font-family="Georgia, \'Times New Roman\', serif" font-size="10" font-weight="700" fill="' . $p['glyphShadow'] . '" opacity="0.45">' . $placeEsc . '</text>'
            . '<text x="16" y="18" text-anchor="middle" font-family="Georgia, \'Times New Roman\', serif" font-size="10" font-weight="700" fill="' . $p['glyph'] . '">' . $placeEsc . '</text>'
            . '</svg></span>';
    }
}

if (!function_exists('k2_status_render_league_table')) {
    function k2_status_render_league_table(?array $monthly, bool $showPodiumMedals = false, bool $sortable = false): void
    {
        if ($monthly === null || $monthly['rows'] === []) {
            return;
        }
        $tableClass = 'k2-table k2-status-table k2-status-table--dense k2-table--calm-stats';
        if ($showPodiumMedals) {
            $tableClass .= ' k2-status-table--podium';
        }
        if ($sortable) {
            // Status league tables: sortable + cloak only — not hub Rank/Player column widths.
            $tableClass .= ' ranked-pages-table ranked-table-pending';
        }
        $tableAttrs = '';
        if ($sortable) {
            $tableAttrs = ' data-k2-table="sortable" data-k2-autorank="true" data-k2-default-sort="9" data-k2-default-direction="desc" data-k2-sort-scope="league-standings"';
        }
        ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="<?php echo k2_status_h($tableClass); ?>"<?php echo $tableAttrs; ?>>
					<thead>
						<tr>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>#</th>
							<th class="k2-status-table__player"<?php echo $sortable ? ' data-k2-sort="text"' : ''; ?>>Player</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>Games</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>W</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>D</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>L</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>GF</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>GA</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>GD</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>Pts</th>
<?php if ($showPodiumMedals) { ?>
							<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>
<?php } ?>
						</tr>
					</thead>
					<tbody class="black">
<?php
        $rank = 1;
        foreach ($monthly['rows'] as $row) {
            $gd = (int) $row['gd'];
            $playerId = (int) $row['id'];
            $playerName = (string) $row['name'];
            ?>
						<tr<?php echo $sortable ? ' data-k2-sort-tie-value="' . $rank . '"' : ''; ?>>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, $rank); ?>><?php echo $rank; ?></td>
							<td class="k2-status-table__player"<?php echo k2_league_table_sort_value_attr($sortable, $playerName); ?>><?php echo k2_status_player_link($playerId, $playerName); ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['played']); ?>><?php echo (int) $row['played']; ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['wins']); ?>><span class="blue"><?php echo (int) $row['wins']; ?></span></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['draws']); ?>><?php echo (int) $row['draws']; ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['losses']); ?>><span class="red"><?php echo (int) $row['losses']; ?></span></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['gf']); ?>><?php echo (int) $row['gf']; ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['ga']); ?>><?php echo (int) $row['ga']; ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, $gd); ?>><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
							<td class="k2-status-table__num"<?php echo k2_league_table_sort_value_attr($sortable, (int) $row['pts']); ?>><span class="blue"><?php echo (int) $row['pts']; ?></span></td>
<?php if ($showPodiumMedals) { ?>
							<td class="k2-status-table__medal"><?php echo $rank <= 3 ? k2_status_league_podium_medal($rank) : ''; ?></td>
<?php } ?>
						</tr>
<?php
            ++$rank;
        }
        ?>
					</tbody>
				</table>
			</div>
<?php
    }
}

if (!function_exists('k2_status_render_activity_competition_table')) {
    /**
     * @param array<int, array{rank: int, player_id: int, player_name: string, games: int}> $entries
     */
    function k2_status_render_activity_competition_table(array $entries, bool $showPodiumMedals = false, bool $sortable = false): void
    {
        if ($entries === []) {
            return;
        }
        $tableClass = 'k2-table k2-status-table k2-status-table--dense k2-table--calm-stats k2-status-period-competitions__activity-table';
        if ($showPodiumMedals) {
            $tableClass .= ' k2-status-table--podium';
        }
        if ($sortable) {
            // Status league tables: sortable + cloak only — not hub Rank/Player column widths.
            $tableClass .= ' ranked-pages-table ranked-table-pending';
        }
        $tableAttrs = '';
        if ($sortable) {
            $tableAttrs = ' data-k2-table="sortable" data-k2-autorank="true" data-k2-default-sort="2" data-k2-default-direction="desc" data-k2-sort-scope="league-standings"';
        }
        ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="<?php echo k2_status_h($tableClass); ?>"<?php echo $tableAttrs; ?>>
					<thead>
						<tr>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>#</th>
							<th class="k2-status-table__player"<?php echo $sortable ? ' data-k2-sort="text"' : ''; ?>>Player</th>
							<th class="k2-status-table__num"<?php echo $sortable ? ' data-k2-sort="number"' : ''; ?>>Games</th>
<?php if ($showPodiumMedals) { ?>
							<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>
<?php } ?>
						</tr>
					</thead>
					<tbody class="black">
<?php
        foreach ($entries as $entry) {
            $rank = (int) $entry['rank'];
            $playerId = (int) $entry['player_id'];
            $playerName = (string) $entry['player_name'];
            $games = (int) $entry['games'];
            echo '<tr' . ($sortable ? ' data-k2-sort-tie-value="' . $rank . '"' : '') . '>';
            echo '<td class="k2-status-table__num"' . k2_league_table_sort_value_attr($sortable, $rank) . '>' . $rank . '</td>';
            echo '<td class="k2-status-table__player"' . k2_league_table_sort_value_attr($sortable, $playerName) . '>' . k2_status_player_link($playerId, $playerName) . '</td>';
            echo '<td class="k2-status-table__num"' . k2_league_table_sort_value_attr($sortable, $games) . '><span class="blue">' . $games . '</span></td>';
            if ($showPodiumMedals) {
                echo '<td class="k2-status-table__medal">' . ($rank <= 3 ? k2_status_league_podium_medal($rank) : '') . '</td>';
            }
            echo '</tr>';
        }
        ?>
					</tbody>
				</table>
			</div>
<?php
    }
}
