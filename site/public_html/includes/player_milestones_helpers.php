<?php
/**
 * Milestones read helpers (garden, profile glance, meta leaderboard, achiever lists).
 * Data: milestone_definitions + player_milestones only — no ratedresults aggregation.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_garden_order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';

const K2_MILESTONE_CATALOG_TOTAL = 110;

/** @var array<string, string> DB tier_band → garden section label */
const K2_MILESTONE_TIER_LABELS = [
    'aspirational' => 'Aspirational',
    'veteran' => 'Dedicated',
    'key' => 'Accomplished',
    'legendary' => 'Legendary',
];

/** @var array<int, string> Section order for garden */
const K2_MILESTONE_TIER_ORDER = ['aspirational', 'veteran', 'key', 'legendary'];

function k2_milestone_strip_markdown(string $text): string
{
    $text = str_replace('**', '', $text);
    $text = preg_replace('/\s*·\s*/u', ' ', $text) ?? $text;

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function k2_milestone_tier_label(string $tierBand): string
{
    return K2_MILESTONE_TIER_LABELS[$tierBand] ?? ucfirst($tierBand);
}

/**
 * Sort garden cards: most common milestones first within tier (see garden_order.php).
 *
 * @param array<int, array<string, mixed>> $cards
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_sort_garden_cards(array $cards, string $tierBand): array
{
    $order = K2_MILESTONE_GARDEN_KEY_ORDER[$tierBand] ?? [];
    if ($order === []) {
        return $cards;
    }
    $rank = array_flip($order);
    usort($cards, static function (array $a, array $b) use ($rank): int {
        $ra = $rank[$a['milestone_key']] ?? 9999;
        $rb = $rank[$b['milestone_key']] ?? 9999;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        return strcmp((string) $a['milestone_key'], (string) $b['milestone_key']);
    });

    return $cards;
}

function k2_milestone_format_utc(?string $achievedAt): string
{
    if ($achievedAt === null || $achievedAt === '') {
        return '';
    }
    $ts = strtotime($achievedAt . ' UTC');
    if ($ts === false) {
        return '';
    }

    return gmdate('M j, Y · H:i', $ts) . ' UTC';
}

/**
 * @return bool
 */
function k2_milestone_tables_ready(mysqli $con): bool
{
    return k2_status_table_exists($con, 'milestone_definitions')
        && k2_status_table_exists($con, 'player_milestones');
}

/**
 * @return array{total: int, aspirational: int, dedicated: int, accomplished: int, legendary: int}|null
 */
function k2_milestone_player_counts(mysqli $con, int $playerId): ?array
{
    if (!k2_milestone_tables_ready($con)) {
        return null;
    }
    $pid = (int) $playerId;
    $sql = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(md.tier_band = 'aspirational'), 0) AS aspirational,
            COALESCE(SUM(md.tier_band = 'veteran'), 0) AS dedicated,
            COALESCE(SUM(md.tier_band = 'key'), 0) AS accomplished,
            COALESCE(SUM(md.tier_band = 'legendary'), 0) AS legendary
        FROM player_milestones pm
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE pm.player_id = $pid
    ";
    $result = mysqli_query($con, $sql);
    if ($result === false) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    if (!$row) {
        return [
            'total' => 0,
            'aspirational' => 0,
            'dedicated' => 0,
            'accomplished' => 0,
            'legendary' => 0,
        ];
    }

    return [
        'total' => (int) $row['total'],
        'aspirational' => (int) $row['aspirational'],
        'dedicated' => (int) $row['dedicated'],
        'accomplished' => (int) $row['accomplished'],
        'legendary' => (int) $row['legendary'],
    ];
}

/**
 * @return array<string, array<int, array<string, mixed>>>
 */
function k2_milestone_garden_by_tier(mysqli $con, int $playerId): array
{
    $sections = [];
    foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        $sections[$tier] = [];
    }
    if (!k2_milestone_tables_ready($con)) {
        return $sections;
    }

    $pid = (int) $playerId;
    $sql = "
        SELECT
            d.milestone_key,
            d.display_name,
            d.rule_short,
            d.tier_band,
            d.chart_token,
            d.sort_order,
            pm.achieved_at,
            pm.source_kind,
            pm.source_game_id,
            pm.source_league_kind,
            pm.source_period_type,
            pm.source_period_start
        FROM milestone_definitions d
        LEFT JOIN player_milestones pm
            ON pm.milestone_key = d.milestone_key AND pm.player_id = $pid
        ORDER BY FIELD(d.tier_band, 'aspirational', 'veteran', 'key', 'legendary'), d.milestone_key
    ";
    $result = k2_query_or_public_error($con, $sql, 'milestone garden');
    while ($row = mysqli_fetch_assoc($result)) {
        $tier = (string) $row['tier_band'];
        $unlocked = $row['achieved_at'] !== null && $row['achieved_at'] !== '';
        $card = [
            'milestone_key' => (string) $row['milestone_key'],
            'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'rule_short' => k2_milestone_strip_markdown((string) $row['rule_short']),
            'tier_band' => $tier,
            'chart_token' => (string) $row['chart_token'],
            'unlocked' => $unlocked,
            'achieved_at' => $unlocked ? (string) $row['achieved_at'] : null,
            'achieved_label' => $unlocked ? k2_milestone_format_utc((string) $row['achieved_at']) : '',
            'source_link' => $unlocked ? k2_milestone_source_link_html($row) : null,
        ];
        if (!isset($sections[$tier])) {
            $sections[$tier] = [];
        }
        $sections[$tier][] = $card;
    }

    foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        if (!empty($sections[$tier])) {
            $sections[$tier] = k2_milestone_sort_garden_cards($sections[$tier], $tier);
        }
    }

    return $sections;
}

/**
 * @param array<string, mixed> $row player_milestones + definition join row
 */
function k2_milestone_source_link_html(array $row): ?string
{
    $kind = (string) ($row['source_kind'] ?? '');
    if ($kind === 'game' && !empty($row['source_game_id'])) {
        $gid = (int) $row['source_game_id'];

        return '<a href="game.php?id=' . $gid . '">Game</a>';
    }
    if ($kind === 'league') {
        $cup = (string) ($row['source_league_kind'] ?? '');
        $period = (string) ($row['source_period_type'] ?? '');
        $start = (string) ($row['source_period_start'] ?? '');
        if ($cup === '' || $period === '' || $start === '') {
            return null;
        }
        $cupNorm = $cup === 'activity' ? 'activity' : 'points';
        $href = k2_league_period_href($cupNorm, $period, $start);

        return '<a href="' . k2_h($href) . '">League</a>';
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_meta_leaderboard_rows(mysqli $con): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }
    $sql = "
        SELECT
            p.ID AS player_id,
            p.Name AS player_name,
            p.Rating AS rating,
            p.NumberGames AS games,
            COUNT(pm.milestone_key) AS total,
            COALESCE(SUM(md.tier_band = 'aspirational'), 0) AS aspirational,
            COALESCE(SUM(md.tier_band = 'veteran'), 0) AS dedicated,
            COALESCE(SUM(md.tier_band = 'key'), 0) AS accomplished,
            COALESCE(SUM(md.tier_band = 'legendary'), 0) AS legendary
        FROM playertable p
        LEFT JOIN player_milestones pm ON pm.player_id = p.ID
        LEFT JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE p.NumberGames >= 1 AND p.Display = 1
        GROUP BY p.ID, p.Name
        ORDER BY
            total DESC,
            aspirational DESC,
            dedicated DESC,
            accomplished DESC,
            legendary DESC,
            p.Name ASC
    ";
    $result = k2_query_or_public_error($con, $sql, 'milestone meta leaderboard');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'rating' => (float) $row['rating'],
            'games' => (int) $row['games'],
            'total' => (int) $row['total'],
            'aspirational' => (int) $row['aspirational'],
            'dedicated' => (int) $row['dedicated'],
            'accomplished' => (int) $row['accomplished'],
            'legendary' => (int) $row['legendary'],
        ];
    }

    return $rows;
}

/**
 * Status-style match line for a Double Digit Merchant unlock (player · score · opponent).
 *
 * @param array<string, mixed> $game ratedresults row
 */
function k2_milestone_dd_merchant_match_html(int $playerId, array $game): string
{
    if (empty($game['id']) && !empty($game['game_id'])) {
        $game['id'] = $game['game_id'];
    }
    $game = k2_rated_game_normalize_row($game);
    $gameId = (int) $game['id'];
    if ($gameId < 1) {
        return '—';
    }

    $isA = (int) $game['idA'] === $playerId;
    $goalsFor = $isA ? (int) $game['GoalsA'] : (int) $game['GoalsB'];
    $goalsAgainst = $isA ? (int) $game['GoalsB'] : (int) $game['GoalsA'];
    $opponentId = $isA ? (int) $game['idB'] : (int) $game['idA'];
    $opponentName = $isA ? (string) $game['NameB'] : (string) $game['NameA'];
    $playerName = $isA ? (string) $game['NameA'] : (string) $game['NameB'];

    ob_start();
    ?>
<span class="k2-status-match k2-ms-achiever-match">
	<span class="k2-status-match__side"><?php echo k2_player_link($playerId, $playerName); ?></span>
	<span class="k2-status-score"><?php echo (int) $goalsFor; ?>–<?php echo (int) $goalsAgainst; ?></span>
	<span class="k2-status-match__side"><?php echo k2_player_link($opponentId, $opponentName); ?></span>
</span>
    <?php
    return (string) ob_get_clean();
}

/**
 * Double Digit Merchant achievers (trial list), newest first.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_dd_merchant_achievers(mysqli $con): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }
    $sql = "
        SELECT
            pm.player_id,
            pm.achieved_at,
            pm.source_kind,
            pm.source_game_id,
            p.Name AS player_name,
            r.id,
            r.Date,
            r.idA,
            r.idB,
            r.nameA AS NameA,
            r.nameB AS NameB,
            r.GoalsA,
            r.GoalsB
        FROM player_milestones pm
        INNER JOIN playertable p ON p.ID = pm.player_id
        LEFT JOIN ratedresults r ON r.id = pm.source_game_id
        WHERE pm.milestone_key = 'dd_merchant_10'
        ORDER BY pm.achieved_at DESC
    ";
    $result = k2_query_or_public_error($con, $sql, 'dd_merchant_10 achievers');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $playerId = (int) $row['player_id'];
        $gameId = !empty($row['id']) ? (int) $row['id'] : 0;
        $matchHtml = $gameId > 0 ? k2_milestone_dd_merchant_match_html($playerId, $row) : '—';
        $gameIdHtml = $gameId > 0
            ? '<a href="game.php?id=' . $gameId . '">' . $gameId . '</a>'
            : '—';
        $rows[] = [
            'player_id' => $playerId,
            'player_name' => (string) $row['player_name'],
            'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
            'match_html' => $matchHtml,
            'game_id_html' => $gameIdHtml,
        ];
    }

    return $rows;
}

function k2_milestone_render_glance(mysqli $con, int $playerId, int $numberGames): void
{
    if ($numberGames < 1 || !k2_milestone_tables_ready($con)) {
        return;
    }
    $counts = k2_milestone_player_counts($con, $playerId);
    if ($counts === null) {
        return;
    }
    $gardenHref = 'individual_milestones.php?id=' . (int) $playerId;
    $total = $counts['total'];
    ?>
<p class="k2-ms-glance">
	<a class="k2-ms-glance__link" href="<?php echo k2_h($gardenHref); ?>">
		<strong><?php echo (int) $total; ?></strong> / <?php echo K2_MILESTONE_CATALOG_TOTAL; ?> milestones
	</a>
	<span class="k2-ms-glance__tiers" aria-label="Unlocked per tier">
		<span class="k2-ms-glance__tier k2-ms-glance__tier--pitch" title="Aspirational"><?php echo (int) $counts['aspirational']; ?></span>
		<span class="k2-ms-glance__sep" aria-hidden="true">·</span>
		<span class="k2-ms-glance__tier k2-ms-glance__tier--chrome" title="Dedicated"><?php echo (int) $counts['dedicated']; ?></span>
		<span class="k2-ms-glance__sep" aria-hidden="true">·</span>
		<span class="k2-ms-glance__tier k2-ms-glance__tier--amber" title="Accomplished"><?php echo (int) $counts['accomplished']; ?></span>
		<span class="k2-ms-glance__sep" aria-hidden="true">·</span>
		<span class="k2-ms-glance__tier k2-ms-glance__tier--holo" title="Legendary"><?php echo (int) $counts['legendary']; ?></span>
	</span>
</p>
    <?php
}

/**
 * @param array<string, array<int, array<string, mixed>>> $gardenByTier
 */
function k2_milestone_render_garden(array $gardenByTier): void
{
    foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        $cards = $gardenByTier[$tier] ?? [];
        if ($cards === []) {
            continue;
        }
        $label = k2_milestone_tier_label($tier);
        ?>
<section class="k2-ms-garden__section" data-tier="<?php echo k2_h($tier); ?>">
	<h2 class="k2-panel-heading k2-ms-garden__heading"><?php echo k2_h($label); ?></h2>
	<ul class="k2-ms-garden__grid">
        <?php foreach ($cards as $card) {
            $token = (string) $card['chart_token'];
            $state = !empty($card['unlocked']) ? 'is-unlocked' : 'is-locked';
            ?>
		<li class="k2-ms-card <?php echo k2_h($state); ?> k2-ms-card--<?php echo k2_h($token); ?>">
			<h3 class="k2-ms-card__title"><?php echo k2_h((string) $card['display_name']); ?></h3>
			<p class="k2-ms-card__rule"><?php echo k2_h((string) $card['rule_short']); ?></p>
            <?php if (!empty($card['unlocked'])) { ?>
			<p class="k2-ms-card__when">
				<span class="k2-ms-card__date"><?php echo k2_h((string) $card['achieved_label']); ?></span>
                <?php if (!empty($card['source_link'])) {
                    echo ' · ' . $card['source_link'];
                } ?>
			</p>
            <?php } ?>
		</li>
        <?php } ?>
	</ul>
</section>
        <?php
    }
}
