<?php
/**
 * Milestones read helpers (garden, profile hero counts, meta leaderboard, achiever lists).
 * Data: milestone_definitions + player_milestones only — no ratedresults aggregation.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_garden_order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_garden_links.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';

/** Fallback when `milestone_definitions` is missing (dev before SCH-011). */
const K2_MILESTONE_CATALOG_TOTAL_FALLBACK = 112;

/**
 * Live catalog size from milestone_definitions (not hard-coded).
 */
function k2_milestone_catalog_total(mysqli $con): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!k2_status_table_exists($con, 'milestone_definitions')) {
        $cached = K2_MILESTONE_CATALOG_TOTAL_FALLBACK;

        return $cached;
    }
    $res = mysqli_query($con, 'SELECT COUNT(*) AS n FROM `milestone_definitions`');
    if ($res === false) {
        $cached = K2_MILESTONE_CATALOG_TOTAL_FALLBACK;

        return $cached;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    $cached = max(1, (int) ($row['n'] ?? K2_MILESTONE_CATALOG_TOTAL_FALLBACK));

    return $cached;
}

/** @var array<string, string> DB tier_band → garden section label */
const K2_MILESTONE_TIER_LABELS = [
    'aspirational' => 'Aspirational',
    'veteran' => 'Dedicated',
    'key' => 'Accomplished',
    'legendary' => 'Legendary',
];

/** @var array<int, string> Section order for garden */
const K2_MILESTONE_TIER_ORDER = ['aspirational', 'veteran', 'key', 'legendary'];

/** Server-wide Recent feed row cap (fixed; no UI to change). */
const K2_MILESTONE_RECENT_FEED_LIMIT = 100;

/** @var array<string, string> tier_band → chart_token (catalog colors) */
const K2_MILESTONE_TIER_CHART_TOKEN = [
    'aspirational' => 'pitch',
    'veteran' => 'chrome',
    'key' => 'amber',
    'legendary' => 'holo',
];

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

    return gmdate('M j, Y, H:i', $ts) . ' UTC';
}

/** Idempotent game unlock (post-game / live writer). */
function k2_milestone_insert_game_unlock(
    mysqli $con,
    int $playerId,
    string $milestoneKey,
    int $gameId,
    string $achievedAt,
    int $value
): void {
    if (!k2_milestone_tables_ready($con) || $playerId < 1 || $gameId < 1) {
        return;
    }
    $stmt = $con->prepare(
        'INSERT IGNORE INTO `player_milestones` '
        . '(`player_id`, `milestone_key`, `achieved_at`, `value`, '
        . '`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) '
        . 'VALUES (?, ?, ?, ?, \'game\', ?, NULL, NULL, NULL)'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('issii', $playerId, $milestoneKey, $achievedAt, $value, $gameId);
    $stmt->execute();
    $stmt->close();
}

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
        $mKey = (string) $row['milestone_key'];
        $unlockRow = $row;
        $unlockRow['milestone_key'] = $mKey;
        $card = [
            'milestone_key' => $mKey,
            'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'rule_short' => k2_milestone_strip_markdown((string) $row['rule_short']),
            'tier_band' => $tier,
            'chart_token' => (string) $row['chart_token'],
            'unlocked' => $unlocked,
            'achieved_at' => $unlocked ? (string) $row['achieved_at'] : null,
            'achieved_label' => $unlocked ? k2_milestone_format_utc((string) $row['achieved_at']) : '',
            'detail_href' => k2_milestone_detail_href($mKey),
            'source_link' => $unlocked ? k2_milestone_garden_link_html($pid, $unlockRow) : null,
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
 * Event context HTML for list / achiever surfaces (see docs/milestones-unlock-event-ui.md).
 *
 * @param array<string, mixed> $row unlock row with source_* (+ ratedresults join for match_line)
 */
function k2_milestone_unlock_event_context_html(int $playerId, array $row, string $surface = K2_MILESTONE_EVENT_SURFACE_DETAIL): ?string
{
    if ($surface === K2_MILESTONE_EVENT_SURFACE_COMPACT) {
        return null;
    }

    $mKey = (string) ($row['milestone_key'] ?? '');
    $profile = k2_milestone_unlock_event_entry($mKey);
    $contextKind = $profile['event_context'];

    if ($contextKind === 'none') {
        return '—';
    }

    if ($contextKind === 'lobby_copy') {
        return 'Joined the ladder';
    }

    if ($contextKind === 'day_games') {
        return k2_h(k2_milestone_day_games_context_label($mKey));
    }

    if ($contextKind === 'league_period') {
        $cup = (string) ($row['source_league_kind'] ?? '');
        $period = (string) ($row['source_period_type'] ?? '');
        $start = (string) ($row['source_period_start'] ?? '');
        if ($cup === '' || $period === '' || $start === '') {
            return '—';
        }
        $cupNorm = $cup === 'activity' ? 'activity' : 'points';

        return k2_h(k2_league_period_short_label($cupNorm, $period, $start));
    }

    if ($contextKind === 'match_line') {
        $kind = (string) ($row['source_kind'] ?? '');
        if ($kind === 'game' && !empty($row['id'])) {
            return k2_milestone_game_match_html($playerId, $row);
        }

        return '—';
    }

    return '—';
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
 * Optional `?key=` milestone slug for hub detail.
 */
function k2_milestone_key_param(string $name = 'key'): ?string
{
    $raw = $_GET[$name] ?? '';
    if (!is_string($raw)) {
        return null;
    }
    $key = trim($raw);
    if ($key === '') {
        return null;
    }
    if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
        k2_public_error('Unknown milestone.', 400);
    }

    return $key;
}

function k2_milestone_achiever_sort_param(): string
{
    $sort = isset($_GET['sort']) ? strtolower(trim((string) $_GET['sort'])) : 'newest';
    if ($sort !== 'first' && $sort !== 'newest') {
        return 'newest';
    }

    return $sort;
}

function k2_milestone_recent_tier_param(): ?string
{
    $raw = isset($_GET['tier']) ? strtolower(trim((string) $_GET['tier'])) : '';
    if ($raw === '' || $raw === 'all') {
        return null;
    }
    if (!in_array($raw, K2_MILESTONE_TIER_ORDER, true)) {
        return null;
    }

    return $raw;
}

function k2_milestones_recent_href(?string $tierBand = null): string
{
    if ($tierBand === null || $tierBand === '') {
        return 'milestones.php';
    }

    return 'milestones.php?' . http_build_query(['tier' => $tierBand]);
}

function k2_milestones_catalog_href(): string
{
    return 'milestones.php?view=catalog';
}

function k2_milestone_detail_href(string $milestoneKey, ?string $sort = null): string
{
    $params = ['key' => $milestoneKey];
    if ($sort !== null && $sort !== '' && $sort !== 'newest') {
        $params['sort'] = $sort;
    }

    return 'milestone.php?' . http_build_query($params);
}

/** @deprecated Use k2_milestones_recent_href / k2_milestone_detail_href */
function k2_milestone_hub_href(?string $key = null, ?string $sort = null): string
{
    if ($key !== null && $key !== '') {
        return k2_milestone_detail_href($key, $sort);
    }

    return k2_milestones_recent_href();
}

/**
 * @return array<string, int>
 */
function k2_milestone_holder_counts(mysqli $con): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }
    $counts = [];
    $result = k2_query_or_public_error(
        $con,
        'SELECT `milestone_key`, COUNT(*) AS holders FROM `player_milestones` GROUP BY `milestone_key`',
        'milestone holder counts'
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[(string) $row['milestone_key']] = (int) $row['holders'];
    }

    return $counts;
}

/**
 * Full catalog sorted by holder count (rarest last → most holders first).
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_catalog_by_holders(mysqli $con): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }

    $holderCounts = k2_milestone_holder_counts($con);
    $sql = '
        SELECT
            `milestone_key`,
            `display_name`,
            `rule_short`,
            `description`,
            `tier_band`,
            `chart_token`
        FROM `milestone_definitions`
    ';
    $result = k2_query_or_public_error($con, $sql, 'milestone catalog');
    $cards = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $mKey = (string) $row['milestone_key'];
        $description = trim((string) ($row['description'] ?? ''));
        $ruleShort = k2_milestone_strip_markdown((string) $row['rule_short']);
        $cards[] = [
            'milestone_key' => $mKey,
            'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'rule_short' => $ruleShort,
            'tooltip' => $description !== '' ? k2_milestone_strip_markdown($description) : $ruleShort,
            'tier_band' => (string) $row['tier_band'],
            'chart_token' => (string) $row['chart_token'],
            'holders' => $holderCounts[$mKey] ?? 0,
            'detail_href' => k2_milestone_detail_href($mKey),
        ];
    }

    usort($cards, static function (array $a, array $b): int {
        $ha = (int) $a['holders'];
        $hb = (int) $b['holders'];
        if ($ha !== $hb) {
            return $hb <=> $ha;
        }

        return strcmp((string) $a['display_name'], (string) $b['display_name']);
    });

    return $cards;
}

/**
 * @return array<string, mixed>|null
 */
function k2_milestone_definition_hub(mysqli $con, string $milestoneKey): ?array
{
    if (!k2_milestone_tables_ready($con)) {
        return null;
    }
    $keyEsc = mysqli_real_escape_string($con, $milestoneKey);
    $sql = "
        SELECT
            d.milestone_key,
            d.display_name,
            d.rule_short,
            d.description,
            d.tier_band,
            d.chart_token,
            COALESCE(h.holders, 0) AS holders
        FROM milestone_definitions d
        LEFT JOIN (
            SELECT milestone_key, COUNT(*) AS holders
            FROM player_milestones
            GROUP BY milestone_key
        ) h ON h.milestone_key = d.milestone_key
        WHERE d.milestone_key = '$keyEsc'
        LIMIT 1
    ";
    $result = mysqli_query($con, $sql);
    if ($result === false) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    if (!$row) {
        return null;
    }
    $description = trim((string) ($row['description'] ?? ''));
    $ruleShort = k2_milestone_strip_markdown((string) $row['rule_short']);

    return [
        'milestone_key' => (string) $row['milestone_key'],
        'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
        'rule_short' => $ruleShort,
        'description' => $description !== '' ? k2_milestone_strip_markdown($description) : '',
        'tier_band' => (string) $row['tier_band'],
        'chart_token' => (string) $row['chart_token'],
        'holders' => (int) $row['holders'],
    ];
}

/**
 * Latest server-wide unlocks for the Recent feed.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_recent_unlocks(mysqli $con, int $limit = K2_MILESTONE_RECENT_FEED_LIMIT, ?string $tierBand = null): array
{
    if (!k2_milestone_tables_ready($con) || $limit < 1) {
        return [];
    }
    $limit = min(K2_MILESTONE_RECENT_FEED_LIMIT, max(1, $limit));
    $tierFilterSql = '';
    if ($tierBand !== null && in_array($tierBand, K2_MILESTONE_TIER_ORDER, true)) {
        $tierEsc = mysqli_real_escape_string($con, $tierBand);
        $tierFilterSql = " AND md.tier_band = '$tierEsc'";
    }
    $sql = "
        SELECT
            pm.milestone_key,
            pm.achieved_at,
            pm.player_id,
            pm.source_kind,
            pm.source_game_id,
            pm.source_league_kind,
            pm.source_period_type,
            pm.source_period_start,
            p.Name AS player_name,
            md.display_name,
            md.rule_short,
            md.tier_band,
            md.chart_token
        FROM player_milestones pm
        INNER JOIN playertable p ON p.ID = pm.player_id AND p.Display = 1
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE 1=1$tierFilterSql
        ORDER BY pm.achieved_at DESC
        LIMIT $limit
    ";
    $result = k2_query_or_public_error($con, $sql, 'recent milestone unlocks');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $mKey = (string) $row['milestone_key'];
        $playerId = (int) $row['player_id'];
        $unlockRow = $row;
        $unlockRow['milestone_key'] = $mKey;
        $rows[] = [
            'milestone_key' => $mKey,
            'achieved_at' => (string) $row['achieved_at'],
            'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
            'player_id' => $playerId,
            'player_name' => (string) $row['player_name'],
            'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'rule_short' => k2_milestone_strip_markdown((string) $row['rule_short']),
            'tier_band' => (string) $row['tier_band'],
            'chart_token' => (string) $row['chart_token'],
            'detail_href' => k2_milestone_detail_href($mKey),
            'event_link_html' => k2_milestone_unlock_event_link_html($playerId, $unlockRow),
        ];
    }

    return $rows;
}

/**
 * Chart bundles for milestone detail page (signature charts + universal timeline).
 *
 * @return array<int, string>
 */
function k2_milestone_detail_chart_ids(string $milestoneKey): array
{
    if ($milestoneKey === 'dd_merchant_10') {
        return ['timeline', 'dd_year', 'dd_cumulative', 'dd_rating'];
    }
    if ($milestoneKey === 'established_20') {
        return ['timeline', 'est_year', 'est_cumulative', 'est_rating'];
    }

    return ['timeline'];
}

/**
 * Status-style match line for a game-sourced unlock (player · score · opponent).
 *
 * @param array<string, mixed> $game ratedresults row
 */
function k2_milestone_game_match_html(int $playerId, array $game): string
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

/** @deprecated Use k2_milestone_game_match_html */
function k2_milestone_dd_merchant_match_html(int $playerId, array $game): string
{
    return k2_milestone_game_match_html($playerId, $game);
}

/**
 * @param array<string, mixed> $row unlock row with source_* fields (+ ratedresults join for games)
 * @return array{event_html: string, link_html: string}
 */
function k2_milestone_achiever_row_cells(int $playerId, array $row, string $milestoneKey): array
{
    $row['milestone_key'] = $milestoneKey;
    $eventHtml = k2_milestone_unlock_event_context_html($playerId, $row, K2_MILESTONE_EVENT_SURFACE_DETAIL) ?? '—';
    $eventLink = k2_milestone_unlock_event_link_html($playerId, $row);

    return [
        'event_html' => $eventHtml,
        'link_html' => $eventLink !== null && $eventLink !== '' ? $eventLink : '—',
    ];
}

/**
 * Achievers for one milestone key.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_achievers(mysqli $con, string $milestoneKey, string $sort = 'newest'): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }
    $order = $sort === 'first' ? 'pm.achieved_at ASC' : 'pm.achieved_at DESC';
    $keyEsc = mysqli_real_escape_string($con, $milestoneKey);
    $sql = "
        SELECT
            pm.player_id,
            pm.achieved_at,
            pm.source_kind,
            pm.source_game_id,
            pm.source_league_kind,
            pm.source_period_type,
            pm.source_period_start,
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
        LEFT JOIN ratedresults r ON r.id = pm.source_game_id AND pm.source_kind = 'game'
        WHERE pm.milestone_key = '$keyEsc'
        ORDER BY $order
    ";
    $result = k2_query_or_public_error($con, $sql, 'milestone achievers');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $playerId = (int) $row['player_id'];
        $cells = k2_milestone_achiever_row_cells($playerId, $row, $milestoneKey);
        $rows[] = [
            'player_id' => $playerId,
            'player_name' => (string) $row['player_name'],
            'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
            'event_html' => $cells['event_html'],
            'link_html' => $cells['link_html'],
        ];
    }

    return $rows;
}

/**
 * Double Digit Merchant achievers — wrapper for Hall of Fame trial block.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_dd_merchant_achievers(mysqli $con): array
{
    return k2_milestone_achievers($con, 'dd_merchant_10', 'newest');
}

function k2_milestone_render_recent_tier_filter(?string $activeTier): void
{
    $links = [];
    $allClass = $activeTier === null ? ' is-active' : '';
    $links[] = '<a href="' . k2_h(k2_milestones_recent_href())
        . '" class="k2-ms-recent-filter__link k2-ms-recent-filter__link--all' . $allClass . '">All</a>';
    foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        $token = K2_MILESTONE_TIER_CHART_TOKEN[$tier] ?? 'pitch';
        $tierClass = $activeTier === $tier ? ' is-active' : '';
        $links[] = '<a href="' . k2_h(k2_milestones_recent_href($tier))
            . '" class="k2-ms-recent-filter__link k2-ms-recent-filter__link--' . k2_h($token) . $tierClass . '">'
            . k2_h(k2_milestone_tier_label($tier)) . '</a>';
    }
    ?>
<nav class="k2-ms-recent-filter" aria-label="Filter recent unlocks by tier">
	<?php echo implode(' <span class="k2-ms-recent-filter__sep" aria-hidden="true">·</span> ', $links); ?>
</nav>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $recentRows
 */
function k2_milestone_render_recent_feed(array $recentRows, ?string $tierBand = null): void
{
    if ($recentRows === []) {
        $emptyHint = $tierBand !== null
            ? 'No recent unlocks in this tier yet.'
            : 'No unlocks recorded yet.';
        ?>
	<p class="k2-ms-meta-hint"><?php echo k2_h($emptyHint); ?></p>
        <?php

        return;
    }
    ?>
<ul class="k2-ms-recent-list" aria-label="Recent milestone unlocks">
    <?php foreach ($recentRows as $item) {
        $token = (string) $item['chart_token'];
        $eventLink = (string) ($item['event_link_html'] ?? '');
        ?>
	<li class="k2-ms-recent-list__item">
		<time class="k2-ms-recent-line__when" datetime="<?php echo k2_h((string) $item['achieved_at']); ?>"><?php echo k2_h((string) $item['achieved_label']); ?></time>
		<span class="k2-ms-recent-line__player"><?php echo k2_player_link((int) $item['player_id'], (string) $item['player_name']); ?></span>
		<span class="k2-ms-recent-line__ms">
			<a href="<?php echo k2_h((string) $item['detail_href']); ?>" class="k2-ms-recent-line__feat k2-lb-ms-tier--<?php echo k2_h($token); ?>"><?php echo k2_h((string) $item['display_name']); ?></a><span class="k2-ms-recent-line__rule"><?php echo k2_h((string) $item['rule_short']); ?></span><?php
        if ($eventLink !== '') {
            echo ' · ' . $eventLink;
        }
        ?>
		</span>
	</li>
    <?php } ?>
</ul>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $catalogCards
 */
function k2_milestone_render_catalog_grid(array $catalogCards): void
{
    if ($catalogCards === []) {
        ?>
	<p class="k2-ms-meta-hint">Catalog is empty.</p>
        <?php

        return;
    }
    ?>
<ul class="k2-ms-catalog-grid">
    <?php foreach ($catalogCards as $card) {
        $token = (string) $card['chart_token'];
        $holders = (int) $card['holders'];
        ?>
	<li>
		<a href="<?php echo k2_h((string) $card['detail_href']); ?>"
			class="k2-ms-card is-unlocked k2-ms-card--<?php echo k2_h($token); ?> k2-ms-catalog-card"
			title="<?php echo k2_h((string) $card['tooltip']); ?>">
			<p class="k2-ms-catalog-card__holders"><?php echo $holders === 1 ? '1 holder' : k2_h((string) $holders) . ' holders'; ?></p>
			<h3 class="k2-ms-card__title"><?php echo k2_h((string) $card['display_name']); ?></h3>
			<p class="k2-ms-card__rule"><?php echo k2_h((string) $card['rule_short']); ?></p>
			<p class="k2-ms-catalog-card__tier">
				<span class="k2-lb-ms-tier--<?php echo k2_h($token); ?>"><?php echo k2_h(k2_milestone_tier_label((string) $card['tier_band'])); ?></span>
			</p>
		</a>
	</li>
    <?php } ?>
</ul>
    <?php
}

/**
 * Achievers table (Event + Link columns) — shared by milestone detail and HoF trial block.
 *
 * @param array<int, array<string, mixed>> $achievers rows from k2_milestone_achievers()
 * @param array{rank_help?: bool, link_help?: string} $options
 */
function k2_milestone_render_achievers_table(array $achievers, array $options = []): void
{
    $rankHelp = !empty($options['rank_help']);
    $linkHelp = isset($options['link_help']) ? (string) $options['link_help'] : '';
    ?>
	<div class="k2-table-wrap">
	<table class="k2-table k2-table--numeric-default">
	<thead>
		<tr>
			<th<?php echo $rankHelp ? ' data-k2-help="Order unlocked, newest at the top (highest number)."' : ''; ?>>#</th>
			<th class="k2-table-cell--left">Player</th>
			<th class="k2-table-cell--left">Unlocked (UTC)</th>
			<th class="k2-table-cell--left">Event</th>
			<th<?php echo $linkHelp !== '' ? ' data-k2-help="' . k2_h($linkHelp) . '"' : ''; ?>>Link</th>
		</tr>
	</thead>
	<tbody class="black">
    <?php
    $memberNum = count($achievers);
    foreach ($achievers as $ach) {
        ?>
		<tr>
			<td><?php echo (int) $memberNum; ?></td>
			<td class="k2-table-cell--left"><?php echo k2_player_link($ach['player_id'], $ach['player_name']); ?></td>
			<td><?php echo k2_h($ach['achieved_label']); ?></td>
			<td class="k2-table-cell--left k2-ms-achiever-event-cell"><?php echo $ach['event_html']; ?></td>
			<td><?php echo $ach['link_html']; ?></td>
		</tr>
        <?php
        --$memberNum;
    }
    ?>
	</tbody>
	</table>
	</div>
    <?php
}

/**
 * @param array<string, mixed> $definition
 * @param array<int, array<string, mixed>> $achievers
 */
function k2_milestone_render_detail_achievers(array $definition, array $achievers, string $sort): void
{
    $mKey = (string) $definition['milestone_key'];
    $newestHref = k2_h(k2_milestone_detail_href($mKey, 'newest'));
    $firstHref = k2_h(k2_milestone_detail_href($mKey, 'first'));
    ?>
<section class="k2-ms-detail-section" aria-labelledby="k2-ms-achievers-heading">
	<h2 class="k2-panel-heading" id="k2-ms-achievers-heading">Who unlocked it</h2>
	<nav class="k2-ms-detail-section__sort" aria-label="Achiever sort">
		<a href="<?php echo $newestHref; ?>" class="k2-ms-detail-section__sort-link<?php echo $sort === 'newest' ? ' is-active' : ''; ?>">Newest first</a>
		<span aria-hidden="true">·</span>
		<a href="<?php echo $firstHref; ?>" class="k2-ms-detail-section__sort-link<?php echo $sort === 'first' ? ' is-active' : ''; ?>">First unlock</a>
	</nav>
    <?php if ($achievers === []) { ?>
	<p class="k2-ms-meta-hint">Nobody has unlocked this milestone yet.</p>
    <?php } else {
        k2_milestone_render_achievers_table($achievers);
    } ?>
</section>
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
			<h3 class="k2-ms-card__title">
                <?php if (!empty($card['detail_href'])) {
                    $titleLinkClass = 'k2-ms-card__title-link';
                    if (empty($card['unlocked'])) {
                        $titleLinkClass .= ' k2-ms-card__title-link--locked';
                    } ?>
				<a href="<?php echo k2_h((string) $card['detail_href']); ?>" class="<?php echo k2_h($titleLinkClass); ?>"><?php echo k2_h((string) $card['display_name']); ?></a>
                <?php } else {
                    echo k2_h((string) $card['display_name']);
                } ?>
			</h3>
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
