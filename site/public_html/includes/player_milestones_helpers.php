<?php
/**
 * Milestones read helpers (garden, profile hero counts, meta leaderboard, achiever lists).
 * Data: milestone_definitions + player_milestones only — no ratedresults aggregation.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_garden_order.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_garden_links.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/milestone_catalog_seed_sync.php';
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

/** Hub catalog grid — always first card (then tier band + holders). */
const K2_MILESTONE_CATALOG_FIRST_KEY = 'entered_arena';

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
    $text = k2_milestone_repair_rule_utf8_mojibake($text);
    $text = str_replace('**', '', $text);
    $text = preg_replace('/\s*·\s*/u', ' ', $text) ?? $text;

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function k2_milestone_tier_label(string $tierBand): string
{
    return K2_MILESTONE_TIER_LABELS[$tierBand] ?? ucfirst($tierBand);
}

function k2_milestone_tier_heading_class(string $tierBand): string
{
    $token = K2_MILESTONE_TIER_CHART_TOKEN[$tierBand] ?? 'pitch';

    return 'k2-panel-heading k2-ms-garden__heading k2-lb-ms-tier--' . $token;
}

/** Stable fragment id for a garden tier section (player milestones tab). */
function k2_milestone_garden_tier_anchor_id(string $tierBand): string
{
    return 'garden-' . $tierBand;
}

function k2_milestone_garden_tier_href(int $playerId, string $tierBand): string
{
    $playerId = max(0, $playerId);
    $base = k2_route('player-milestones-garden', ['id' => $playerId]);
    if ($playerId < 1 || !in_array($tierBand, K2_MILESTONE_TIER_ORDER, true)) {
        return $base;
    }

    return $base . '#' . k2_milestone_garden_tier_anchor_id($tierBand);
}

/**
 * Per-tier counts for display (aspirational → legendary).
 *
 * @param array{aspirational?: int, dedicated?: int, accomplished?: int, legendary?: int} $counts
 * @return array<int, array{band: string, count: int, token: string}>
 */
function k2_milestone_player_tier_rows(array $counts): array
{
    $rows = [];
    foreach (K2_MILESTONE_TIER_ORDER as $band) {
        $countKey = match ($band) {
            'veteran' => 'dedicated',
            'key' => 'accomplished',
            default => $band,
        };
        $rows[] = [
            'band' => $band,
            'count' => (int) ($counts[$countKey] ?? 0),
            'token' => K2_MILESTONE_TIER_CHART_TOKEN[$band] ?? 'pitch',
        ];
    }

    return $rows;
}

function k2_milestone_tier_count_tooltip(int $count, string $band): string
{
    $label = strtolower(k2_milestone_tier_label($band));
    $word = $count === 1 ? 'milestone' : 'milestones';

    return $count . ' ' . $label . ' ' . $word;
}

/**
 * Hero / glance payload: tier rows with href + help (all four tiers by default).
 *
 * @param array{aspirational?: int, dedicated?: int, accomplished?: int, legendary?: int}|null $counts
 * @return array<int, array{band: string, count: int, token: string, href: string, help: string}>
 */
function k2_milestone_hero_tier_payload(?array $counts, int $playerId, bool $omitZeros = false): array
{
    if (!is_array($counts)) {
        return [];
    }

    $rows = [];
    foreach (k2_milestone_player_tier_rows($counts) as $tier) {
        $count = (int) $tier['count'];
        if ($omitZeros && $count < 1) {
            continue;
        }
        $band = (string) $tier['band'];
        $rows[] = [
            'band' => $band,
            'count' => $count,
            'token' => (string) $tier['token'],
            'href' => k2_milestone_garden_tier_href($playerId, $band),
            'help' => k2_milestone_tier_count_tooltip($count, $band),
        ];
    }

    return $rows;
}

/**
 * Hero stat value: space-separated tier counts (no dot separators).
 *
 * @param array{aspirational?: int, dedicated?: int, accomplished?: int, legendary?: int}|null $counts
 */
function k2_milestone_render_hero_tier_counts(?array $counts, int $playerId): string
{
    $tiers = k2_milestone_hero_tier_payload($counts, $playerId);
    if ($tiers === []) {
        return '';
    }

    $parts = [];
    foreach ($tiers as $tier) {
        $token = k2_h($tier['token']);
        $parts[] = '<a href="' . k2_h($tier['href']) . '"'
            . ' class="k2-player-hero__ms-tier k2-lb-ms-tier--' . $token . ' k2-player-hero__ms-tier--' . $token . ' k2-table-helped"'
            . ' data-k2-coarse-tap="1"'
            . ' data-k2-tooltip-hide-title="1"'
            . ' data-k2-help="' . k2_h($tier['help']) . '"'
            . ' data-k2-tooltip-tier="' . $token . '"'
            . ' data-k2-tooltip-action="Click to open the milestone garden"'
            . ' data-k2-tooltip-action-coarse="Tap again to open the milestone garden"'
            . '>' . (int) $tier['count'] . '</a>';
    }

    return '<span class="k2-player-hero__ms-tiers">' . implode('', $parts) . '</span>';
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

    return gmdate('M j, Y, H:i', $ts);
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
    if ($playerId < 1 || $gameId < 1) {
        return;
    }
    require_once __DIR__ . '/milestone_unlock.php';
    k2_milestone_unlock_insert($con, [
        'player_id' => $playerId,
        'milestone_key' => $milestoneKey,
        'achieved_at' => $achievedAt,
        'value' => $value,
        'source_kind' => 'game',
        'source_game_id' => $gameId,
        'source_league_kind' => null,
        'source_period_type' => null,
        'source_period_start' => null,
    ]);
}

function k2_milestone_tables_ready(mysqli $con): bool
{
    return k2_status_table_exists($con, 'milestone_definitions')
        && k2_status_table_exists($con, 'player_milestones');
}

function k2_milestone_totals_read_ready(mysqli $con): bool
{
    if (!function_exists('k2_milestone_totals_table_ready')) {
        require_once __DIR__ . '/milestone_unlock.php';
    }

    return k2_milestone_totals_table_ready($con);
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
    if ($pid < 1) {
        return null;
    }

    if (k2_milestone_totals_read_ready($con)) {
        $stmt = $con->prepare(
            'SELECT `total`, `aspirational`, `dedicated`, `accomplished`, `legendary` '
            . 'FROM `player_milestone_totals` WHERE `player_id` = ? LIMIT 1'
        );
        if ($stmt !== false) {
            $stmt->bind_param('i', $pid);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                if ($res) {
                    $res->free();
                }
                $stmt->close();
                if ($row) {
                    return [
                        'total' => (int) $row['total'],
                        'aspirational' => (int) $row['aspirational'],
                        'dedicated' => (int) $row['dedicated'],
                        'accomplished' => (int) $row['accomplished'],
                        'legendary' => (int) $row['legendary'],
                    ];
                }

                return [
                    'total' => 0,
                    'aspirational' => 0,
                    'dedicated' => 0,
                    'accomplished' => 0,
                    'legendary' => 0,
                ];
            }
            $stmt->close();
        }
    }

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
    mysqli_free_result($result);
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

        return '<a href="' . htmlspecialchars(k2_game_page_url($gid), ENT_QUOTES, 'UTF-8') . '">Game</a>';
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
function k2_milestone_unlock_event_context_html(
    int $playerId,
    array $row,
    string $surface = K2_MILESTONE_EVENT_SURFACE_DETAIL,
    string $chartToken = ''
): ?string {
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
            return k2_milestone_game_match_html($playerId, $row, $chartToken);
        }

        return '—';
    }

    return '—';
}

/** Default ORDER BY tail for milestones meta LB (no leading ORDER BY). */
function k2_milestone_meta_leaderboard_default_order_sql(): string
{
    return 'total DESC, aspirational DESC, dedicated DESC, accomplished DESC, legendary DESC, p.Name ASC';
}

/**
 * Sortable column index → SQL expression for milestones meta LB SSR order.
 *
 * @return array<int, string>
 */
function k2_milestone_meta_leaderboard_order_column_map(): array
{
    return [
        1 => 'p.Name',
        2 => 'p.Rating',
        3 => 'p.NumberGames',
        4 => 'aspirational',
        5 => 'dedicated',
        6 => 'accomplished',
        7 => 'legendary',
        8 => 'total',
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_meta_leaderboard_rows(mysqli $con, ?string $orderClause = null): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }

    $orderClause ??= k2_milestone_meta_leaderboard_default_order_sql();

    if (k2_milestone_totals_read_ready($con)) {
        $sql = "
            SELECT
                p.ID AS player_id,
                p.Name AS player_name,
                p.Rating AS rating,
                p.NumberGames AS games,
                COALESCE(t.total, 0) AS total,
                COALESCE(t.aspirational, 0) AS aspirational,
                COALESCE(t.dedicated, 0) AS dedicated,
                COALESCE(t.accomplished, 0) AS accomplished,
                COALESCE(t.legendary, 0) AS legendary
            FROM playertable p
            LEFT JOIN player_milestone_totals t ON t.player_id = p.ID
            WHERE p.NumberGames >= 1
            ORDER BY
                " . $orderClause . "
        ";
    } else {
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
            WHERE p.NumberGames >= 1
            GROUP BY p.ID, p.Name, p.Rating, p.NumberGames
            ORDER BY
                " . $orderClause . "
        ";
    }

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

/** @deprecated Client-side sort via k2-table.js on milestone.php; kept for any legacy callers */
function k2_milestone_achiever_sort_param(): string
{
    return 'newest';
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
        return k2_route('milestones-recent');
    }

    return k2_route('milestones-recent', ['tier' => $tierBand]);
}

function player_milestones_chronology_href(int $playerId, ?string $tierBand = null): string
{
    $params = ['id' => max(0, $playerId)];
    if ($tierBand !== null && $tierBand !== '' && in_array($tierBand, K2_MILESTONE_TIER_ORDER, true)) {
        $params['tier'] = $tierBand;
    }

    return k2_route('player-milestones-chronology', $params);
}

function k2_milestones_catalog_href(): string
{
    return k2_route('milestones-catalog');
}

/** @return 'unlockers'|'graphs' */
function k2_milestone_detail_panel_param(): string
{
    $panel = isset($_GET['panel']) ? strtolower(trim((string) $_GET['panel'])) : 'unlockers';

    return $panel === 'graphs' ? 'graphs' : 'unlockers';
}

/** Scroll target on milestone.php — spotlight card at viewport top (hub chrome above). */
const K2_MILESTONE_DETAIL_FRAGMENT = 'k2-ms-detail-spotlight';

function k2_milestone_detail_anchor_hash(): string
{
    return '#' . K2_MILESTONE_DETAIL_FRAGMENT;
}

function k2_milestone_detail_href(string $milestoneKey, string $panel = 'unlockers', bool $withSpotlightAnchor = true): string
{
    $params = ['key' => $milestoneKey];
    if ($panel === 'graphs') {
        $params['panel'] = 'graphs';
    }

    $href = k2_route('milestone', $params);

    return $withSpotlightAnchor ? $href . k2_milestone_detail_anchor_hash() : $href;
}

/** @deprecated Use k2_milestones_recent_href / k2_milestone_detail_href */
function k2_milestone_hub_href(?string $key = null, ?string $sort = null): string
{
    if ($key !== null && $key !== '') {
        return k2_milestone_detail_href($key);
    }

    return k2_milestones_recent_href();
}

/**
 * Join used for public holder counts — must match {@see k2_milestone_achievers()} (excludes orphan player_id rows).
 */
function k2_milestone_playertable_join_clause(string $pmAlias = 'pm'): string
{
    return "INNER JOIN `playertable` p ON p.ID = {$pmAlias}.`player_id`";
}

/**
 * @return array<string, int>
 */
function k2_milestone_holder_counts(mysqli $con): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }

    if (!function_exists('k2_milestone_holder_count_column_ready')) {
        require_once __DIR__ . '/milestone_unlock.php';
    }

    if (k2_milestone_holder_count_column_ready($con)) {
        $counts = [];
        $result = k2_query_or_public_error(
            $con,
            'SELECT `milestone_key`, `holder_count` AS holders FROM `milestone_definitions`',
            'milestone holder counts stored'
        );
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[(string) $row['milestone_key']] = (int) $row['holders'];
        }

        return $counts;
    }

    $counts = [];
    $join = k2_milestone_playertable_join_clause('pm');
    $result = k2_query_or_public_error(
        $con,
        "SELECT pm.`milestone_key`, COUNT(*) AS holders FROM `player_milestones` pm {$join} GROUP BY pm.`milestone_key`",
        'milestone holder counts'
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[(string) $row['milestone_key']] = (int) $row['holders'];
    }

    return $counts;
}

/**
 * Full catalog: `entered_arena` first, then tier band order, then holder count within band.
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
            `tier_band`,
            `chart_token`
        FROM `milestone_definitions`
    ';
    $result = k2_query_or_public_error($con, $sql, 'milestone catalog');
    $cards = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $mKey = (string) $row['milestone_key'];
        $cards[] = [
            'milestone_key' => $mKey,
            'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
            'rule_short' => k2_milestone_strip_markdown((string) $row['rule_short']),
            'tier_band' => (string) $row['tier_band'],
            'chart_token' => (string) $row['chart_token'],
            'holders' => $holderCounts[$mKey] ?? 0,
            'detail_href' => k2_milestone_detail_href($mKey),
        ];
    }

    usort($cards, static function (array $a, array $b): int {
        $ka = (string) $a['milestone_key'];
        $kb = (string) $b['milestone_key'];
        if ($ka === K2_MILESTONE_CATALOG_FIRST_KEY && $kb !== K2_MILESTONE_CATALOG_FIRST_KEY) {
            return -1;
        }
        if ($kb === K2_MILESTONE_CATALOG_FIRST_KEY && $ka !== K2_MILESTONE_CATALOG_FIRST_KEY) {
            return 1;
        }
        $ta = array_search((string) $a['tier_band'], K2_MILESTONE_TIER_ORDER, true);
        $tb = array_search((string) $b['tier_band'], K2_MILESTONE_TIER_ORDER, true);
        $ta = $ta === false ? 99 : (int) $ta;
        $tb = $tb === false ? 99 : (int) $tb;
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }
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

    if (!function_exists('k2_milestone_holder_count_column_ready')) {
        require_once __DIR__ . '/milestone_unlock.php';
    }

    $keyEsc = mysqli_real_escape_string($con, $milestoneKey);
    if (k2_milestone_holder_count_column_ready($con)) {
        $sql = "
            SELECT
                d.milestone_key,
                d.display_name,
                d.rule_short,
                d.description,
                d.tier_band,
                d.chart_token,
                d.holder_count AS holders
            FROM milestone_definitions d
            WHERE d.milestone_key = '$keyEsc'
            LIMIT 1
        ";
    } else {
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
                SELECT pm.milestone_key, COUNT(*) AS holders
                FROM player_milestones pm
                INNER JOIN playertable p ON p.ID = pm.player_id
                GROUP BY pm.milestone_key
            ) h ON h.milestone_key = d.milestone_key
            WHERE d.milestone_key = '$keyEsc'
            LIMIT 1
        ";
    }
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
 * @param array<string, mixed> $row SQL row with milestone_key, achieved_at, player_id, display_name, …
 * @return array<string, mixed>
 */
function k2_milestone_format_unlock_feed_row(array $row): array
{
    $mKey = (string) $row['milestone_key'];
    $playerId = (int) $row['player_id'];
    $unlockRow = $row;
    $unlockRow['milestone_key'] = $mKey;

    return [
        'milestone_key' => $mKey,
        'achieved_at' => (string) $row['achieved_at'],
        'achieved_label' => k2_milestone_format_utc((string) $row['achieved_at']),
        'player_id' => $playerId,
        'player_name' => (string) ($row['player_name'] ?? ''),
        'display_name' => k2_milestone_strip_markdown((string) $row['display_name']),
        'rule_short' => k2_milestone_strip_markdown((string) $row['rule_short']),
        'tier_band' => (string) $row['tier_band'],
        'chart_token' => (string) $row['chart_token'],
        'detail_href' => k2_milestone_detail_href($mKey),
        'event_link_html' => k2_milestone_unlock_event_link_html($playerId, $unlockRow),
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
        INNER JOIN playertable p ON p.ID = pm.player_id
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE 1=1$tierFilterSql
        ORDER BY pm.achieved_at DESC
        LIMIT $limit
    ";
    $result = k2_query_or_public_error($con, $sql, 'recent milestone unlocks');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = k2_milestone_format_unlock_feed_row($row);
    }

    return $rows;
}

/**
 * One player's unlock timeline (Chronology wing) — newest first, full career.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_player_unlocks(mysqli $con, int $playerId, ?string $tierBand = null): array
{
    if (!k2_milestone_tables_ready($con) || $playerId < 1) {
        return [];
    }
    $playerId = (int) $playerId;
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
            md.display_name,
            md.rule_short,
            md.tier_band,
            md.chart_token
        FROM player_milestones pm
        INNER JOIN milestone_definitions md ON md.milestone_key = pm.milestone_key
        WHERE pm.player_id = $playerId$tierFilterSql
        ORDER BY pm.achieved_at DESC, pm.milestone_key DESC
    ";
    $result = k2_query_or_public_error($con, $sql, 'player milestone unlocks');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = k2_milestone_format_unlock_feed_row($row);
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
    return ['ms_year', 'ms_cumulative'];
}

/**
 * Status-style match line for a game-sourced unlock (team A · GoalsA–GoalsB · team B).
 * Uses official ratedresults side order, not unlocker-first.
 *
 * @param array<string, mixed> $game ratedresults row
 */
function k2_milestone_game_match_html(int $playerId, array $game, string $chartToken = ''): string
{
    if (empty($game['id']) && !empty($game['game_id'])) {
        $game['id'] = $game['game_id'];
    }
    $game = k2_rated_game_normalize_row($game);
    $gameId = (int) $game['id'];
    if ($gameId < 1) {
        return '—';
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $nameA = (string) $game['NameA'];
    $nameB = (string) $game['NameB'];
    if ($chartToken !== '') {
        $teamA = k2_milestone_tier_link(k2_player_profile_href($idA), $nameA, $chartToken);
        $teamB = k2_milestone_tier_link(k2_player_profile_href($idB), $nameB, $chartToken);
    } else {
        $teamA = k2_player_link($idA, $nameA);
        $teamB = k2_player_link($idB, $nameB);
    }

    ob_start();
    ?>
<span class="k2-status-match k2-ms-achiever-match">
	<span class="k2-status-match__side"><?php echo $teamA; ?></span>
	<span class="k2-status-score"><?php echo (int) $game['GoalsA']; ?>–<?php echo (int) $game['GoalsB']; ?></span>
	<span class="k2-status-match__side"><?php echo $teamB; ?></span>
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
function k2_milestone_achiever_row_cells(int $playerId, array $row, string $milestoneKey, string $chartToken = ''): array
{
    $row['milestone_key'] = $milestoneKey;
    $eventHtml = k2_milestone_unlock_event_context_html(
        $playerId,
        $row,
        K2_MILESTONE_EVENT_SURFACE_DETAIL,
        $chartToken
    ) ?? '—';
    $eventLink = k2_milestone_unlock_event_link_html($playerId, $row);
    $linkHtml = $eventLink !== null && $eventLink !== '' ? $eventLink : '—';
    if ($linkHtml !== '—' && $chartToken !== '') {
        $linkHtml = k2_milestone_tier_event_link_html($linkHtml, $chartToken);
    }

    return [
        'event_html' => $eventHtml,
        'link_html' => $linkHtml,
    ];
}

/**
 * Achievers for one milestone key.
 *
 * @return array<int, array<string, mixed>>
 */
function k2_milestone_achievers(mysqli $con, string $milestoneKey, string $chartToken = ''): array
{
    if (!k2_milestone_tables_ready($con)) {
        return [];
    }
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
            r.GoalsB,
            ROW_NUMBER() OVER (ORDER BY pm.achieved_at ASC, pm.player_id ASC) AS unlock_rank
        FROM player_milestones pm
        " . k2_milestone_playertable_join_clause('pm') . "
        LEFT JOIN ratedresults r ON r.id = pm.source_game_id AND pm.source_kind = 'game'
        WHERE pm.milestone_key = '$keyEsc'
        ORDER BY pm.achieved_at DESC, unlock_rank DESC
    ";
    $result = k2_query_or_public_error($con, $sql, 'milestone achievers');
    $rawRows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rawRows[] = $row;
    }
    $gameRows = array_values(array_filter($rawRows, static fn (array $row): bool => !empty($row['id'])));
    $nameMap = k2_player_display_names_for_rated_rows($con, $gameRows);
    $rows = [];
    foreach ($rawRows as $row) {
        if (!empty($row['id'])) {
            $row = k2_rated_game_apply_display_names($row, $nameMap);
        }
        $playerId = (int) $row['player_id'];
        $cells = k2_milestone_achiever_row_cells($playerId, $row, $milestoneKey, $chartToken);
        $rows[] = [
            'player_id' => $playerId,
            'player_name' => (string) $row['player_name'],
            'unlock_rank' => (int) $row['unlock_rank'],
            'achieved_at' => (string) $row['achieved_at'],
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
    return k2_milestone_achievers($con, 'dd_merchant_10');
}

/**
 * Milestone tier ink (pitch/chrome/amber/holo) — not k2-link-star.
 *
 * @param string $extraClasses e.g. k2-ms-recent-line__link for Recent feed layout
 */
function k2_milestone_tier_link(string $href, string $label, string $chartToken, string $extraClasses = ''): string
{
    $token = k2_h($chartToken);
    $class = 'k2-ms-tier-event-link k2-lb-ms-tier--' . $token;
    if ($extraClasses !== '') {
        $class .= ' ' . $extraClasses;
    }

    return '<a href="' . k2_h($href) . '" class="' . k2_h(trim($class)) . '">'
        . k2_h($label) . '</a>';
}

/** Recent feed — tier link + line layout class. */
function k2_milestone_recent_tier_link(string $href, string $label, string $chartToken): string
{
    return k2_milestone_tier_link($href, $label, $chartToken, 'k2-ms-recent-line__link');
}

function k2_milestone_recent_player_link(int $playerId, string $playerName, string $chartToken): string
{
    return k2_milestone_recent_tier_link(k2_player_profile_href($playerId), $playerName, $chartToken);
}

/**
 * Re-wrap unlock event anchor (Game / League / Games) with milestone tier classes.
 * Source HTML already entity-encodes href once (e.g. league.php query); decode before k2_h to avoid &amp;amp;.
 */
function k2_milestone_tier_event_link_html(?string $eventLinkHtml, string $chartToken): string
{
    if ($eventLinkHtml === null || $eventLinkHtml === '') {
        return '';
    }
    if (preg_match('#<a\s+href="([^"]+)"[^>]*>([^<]*)</a>#i', $eventLinkHtml, $m)) {
        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return k2_milestone_tier_link($href, $label, $chartToken);
    }

    return $eventLinkHtml;
}

/** Recent feed event column — tier wrap + line layout class. */
function k2_milestone_recent_event_link_html(?string $eventLinkHtml, string $chartToken): string
{
    if ($eventLinkHtml === null || $eventLinkHtml === '') {
        return '';
    }
    if (preg_match('#<a\s+href="([^"]+)"[^>]*>([^<]*)</a>#i', $eventLinkHtml, $m)) {
        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return k2_milestone_tier_link($href, $label, $chartToken, 'k2-ms-recent-line__link');
    }

    return $eventLinkHtml;
}

/**
 * @param callable(?string): string $hrefForTier null tier = All
 */
function k2_milestone_render_unlock_tier_filter(?string $activeTier, callable $hrefForTier, string $ariaLabel): void
{
    ?>
<nav class="k2-ms-recent-tier-filter" data-k2-carry-scroll aria-label="<?php echo k2_h($ariaLabel); ?>">
	<div class="k2-chrome-tabs__bar k2-ms-recent-tier-filter__bar">
		<a href="<?php echo k2_h($hrefForTier(null)); ?>"
			class="k2-chrome-tabs__tab k2-ms-recent-tier-filter__tab k2-ms-recent-tier-filter__tab--all<?php echo $activeTier === null ? ' is-active' : ''; ?>"
			<?php echo $activeTier === null ? ' aria-current="page"' : ''; ?>>All</a>
    <?php foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        $token = K2_MILESTONE_TIER_CHART_TOKEN[$tier] ?? 'pitch';
        $isActive = $activeTier === $tier;
        ?>
		<a href="<?php echo k2_h($hrefForTier($tier)); ?>"
			class="k2-chrome-tabs__tab k2-ms-recent-tier-filter__tab k2-ms-recent-tier-filter__tab--<?php echo k2_h($token); ?><?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo k2_h(k2_milestone_tier_label($tier)); ?></a>
    <?php } ?>
	</div>
</nav>
    <?php
}

function k2_milestone_render_recent_tier_filter(?string $activeTier): void
{
    k2_milestone_render_unlock_tier_filter(
        $activeTier,
        static fn (?string $tier): string => k2_milestones_recent_href($tier),
        'Filter recent unlocks by tier'
    );
}

function k2_milestone_render_player_chronology_tier_filter(int $playerId, ?string $activeTier): void
{
    k2_milestone_render_unlock_tier_filter(
        $activeTier,
        static fn (?string $tier): string => player_milestones_chronology_href($playerId, $tier),
        'Filter unlock timeline by tier'
    );
}

/**
 * @param array<int, array<string, mixed>> $unlockRows
 * @param array{
 *   show_heading?: bool,
 *   show_player?: bool,
 *   heading?: string,
 *   heading_id?: string,
 *   empty_all?: string,
 *   empty_tier?: string,
 *   aria_label?: string,
 *   list_class?: string
 * } $opts
 */
function k2_milestone_render_unlock_feed(array $unlockRows, ?string $tierBand = null, array $opts = []): void
{
    $showHeading = $opts['show_heading'] ?? true;
    $showPlayer = $opts['show_player'] ?? true;
    $heading = $opts['heading'] ?? 'Recent unlocks';
    $headingId = $opts['heading_id'] ?? 'k2-ms-recent-list-heading';
    $emptyAll = $opts['empty_all'] ?? 'No unlocks recorded yet.';
    $emptyTier = $opts['empty_tier'] ?? 'No recent unlocks in this tier yet.';
    $ariaLabel = $opts['aria_label'] ?? 'Recent milestone unlocks';
    $listClass = trim('k2-ms-recent-list' . ($showPlayer ? '' : ' k2-ms-recent-list--no-player') . ' ' . ($opts['list_class'] ?? ''));
    $sectionLabelAttr = $showHeading
        ? ' aria-labelledby="' . k2_h($headingId) . '"'
        : ' aria-label="' . k2_h($ariaLabel) . '"';
    ?>
<section class="k2-ms-recent-list-block"<?php echo $sectionLabelAttr; ?>>
	<div class="k2-ms-recent-list-inner">
    <?php if ($showHeading) { ?>
	<h2 class="k2-panel-heading k2-ms-recent-list__heading" id="<?php echo k2_h($headingId); ?>"><?php echo k2_h($heading); ?></h2>
    <?php } ?>
    <?php
    if ($unlockRows === []) {
        $emptyHint = $tierBand !== null ? $emptyTier : $emptyAll;
        ?>
	<p class="k2-ms-meta-hint"><?php echo k2_h($emptyHint); ?></p>
        <?php
    } else {
        ?>
<ul class="<?php echo k2_h($listClass); ?>">
    <?php foreach ($unlockRows as $item) {
        $token = (string) $item['chart_token'];
        $eventLink = (string) ($item['event_link_html'] ?? '');
        ?>
	<li class="k2-ms-recent-list__item">
		<time class="k2-ms-recent-line__when" datetime="<?php echo k2_h((string) $item['achieved_at']); ?>"><?php echo k2_h((string) $item['achieved_label']); ?></time>
        <?php if ($showPlayer) { ?>
		<span class="k2-ms-recent-line__player"><?php echo k2_milestone_recent_player_link((int) $item['player_id'], (string) $item['player_name'], $token); ?></span>
        <?php } ?>
		<span class="k2-ms-recent-line__feat"><?php echo k2_milestone_recent_tier_link((string) $item['detail_href'], (string) $item['display_name'], $token); ?></span>
		<span class="k2-ms-recent-line__rule"><?php echo k2_h((string) $item['rule_short']); ?></span>
		<span class="k2-ms-recent-line__event"><?php
        $eventHtml = k2_milestone_recent_event_link_html($eventLink !== '' ? $eventLink : null, $token);
        if ($eventHtml !== '') {
            echo $eventHtml;
        } else {
            echo '<span class="k2-ms-recent-line__none" aria-hidden="true">—</span>';
        }
        ?></span>
	</li>
    <?php } ?>
</ul>
        <?php
    }
    ?>
	</div>
</section>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $recentRows
 */
function k2_milestone_render_recent_feed(array $recentRows, ?string $tierBand = null): void
{
    k2_milestone_render_unlock_feed($recentRows, $tierBand);
}

/**
 * @param array<int, array<string, mixed>> $unlockRows
 */
function k2_milestone_render_player_chronology_feed(array $unlockRows, ?string $tierBand = null): void
{
    k2_milestone_render_unlock_feed($unlockRows, $tierBand, [
        'show_heading' => false,
        'show_player' => false,
        'empty_all' => 'No milestones unlocked yet.',
        'empty_tier' => 'No milestones unlocked in this tier yet.',
        'aria_label' => 'Milestone unlock timeline',
    ]);
}

/**
 * @param array<int, array<string, mixed>> $catalogCards sorted list from k2_milestone_catalog_by_holders()
 * @return array<string, array<int, array<string, mixed>>>
 */
function k2_milestone_catalog_cards_by_tier(array $catalogCards): array
{
    $byTier = array_fill_keys(K2_MILESTONE_TIER_ORDER, []);
    foreach ($catalogCards as $card) {
        $tier = (string) ($card['tier_band'] ?? '');
        if (!array_key_exists($tier, $byTier)) {
            continue;
        }
        $byTier[$tier][] = $card;
    }

    return $byTier;
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
    $byTier = k2_milestone_catalog_cards_by_tier($catalogCards);
    ?>
<div class="k2-ms-catalog">
    <?php foreach (K2_MILESTONE_TIER_ORDER as $tier) {
        $cards = $byTier[$tier] ?? [];
        if ($cards === []) {
            continue;
        }
        $label = k2_milestone_tier_label($tier);
        ?>
	<section class="k2-ms-garden__section k2-ms-catalog__section" data-tier="<?php echo k2_h($tier); ?>">
		<h2 class="<?php echo k2_h(k2_milestone_tier_heading_class($tier)); ?>"><?php echo k2_h($label); ?></h2>
		<ul class="k2-ms-catalog-grid">
    <?php foreach ($cards as $card) {
        $token = (string) $card['chart_token'];
        $holders = (int) $card['holders'];
        ?>
			<li>
				<a href="<?php echo k2_h((string) $card['detail_href']); ?>"
					class="k2-ms-card is-unlocked k2-ms-card--<?php echo k2_h($token); ?> k2-ms-catalog-card">
					<p class="k2-ms-catalog-card__holders"><?php
        if ($holders === 1) {
            echo '<span class="k2-ms-catalog-card__holders-num">1</span> holder';
        } else {
            echo '<span class="k2-ms-catalog-card__holders-num">' . (int) $holders . '</span> holders';
        }
        ?></p>
					<h3 class="k2-ms-card__title"><?php echo k2_h((string) $card['display_name']); ?></h3>
					<p class="k2-ms-card__rule"><?php echo k2_h((string) $card['rule_short']); ?></p>
				</a>
			</li>
    <?php } ?>
		</ul>
	</section>
    <?php } ?>
</div>
    <?php
}

/**
 * Achievers table (Event + Link columns) — shared by milestone detail and HoF trial block.
 *
 * @param array<int, array<string, mixed>> $achievers rows from k2_milestone_achievers()
 * @param array{rank_help?: bool, link_help?: string, chart_token?: string} $options
 */
function k2_milestone_render_achievers_table(array $achievers, array $options = []): void
{
    $rankHelp = !empty($options['rank_help']);
    $linkHelp = isset($options['link_help']) ? (string) $options['link_help'] : '';
    $chartToken = isset($options['chart_token']) ? (string) $options['chart_token'] : '';
    ?>
	<div class="k2-table-wrap">
	<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-ms-achievers-table"
		data-k2-table="sortable"
		data-k2-anchor-col="2"
		data-k2-sort-tie-order="match"
		data-k2-default-sort="2"
		data-k2-default-direction="desc">
	<thead>
		<tr>
			<th data-k2-sort="number"<?php echo $rankHelp ? ' data-k2-help="1 = earliest unlock for this milestone (fixed). Table sort does not renumber; default view is newest unlock first."' : ''; ?>>#</th>
			<th class="k2-table-cell--left" data-k2-sort="text">Player</th>
			<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="When this milestone was unlocked. Click to sort. Same-time unlocks keep fixed # order (higher # first when sorted newest-first).">Unlocked</th>
			<th class="k2-table-cell--left" data-k2-sort="text">Event</th>
			<th class="k2-table-cell--left"<?php echo $linkHelp !== '' ? ' data-k2-help="' . k2_h($linkHelp) . '"' : ''; ?> data-k2-sort="text">Link</th>
		</tr>
	</thead>
	<tbody class="black">
    <?php
    foreach ($achievers as $ach) {
        $achievedSort = k2_h((string) $ach['achieved_at']);
        $unlockRank = (int) $ach['unlock_rank'];
        $playerId = (int) $ach['player_id'];
        $playerName = (string) $ach['player_name'];
        $playerCell = $chartToken !== ''
            ? k2_milestone_tier_link(k2_player_profile_href($playerId), $playerName, $chartToken)
            : k2_player_link($playerId, $playerName);
        ?>
		<tr data-k2-sort-tie-value="<?php echo $unlockRank; ?>">
			<td data-k2-sort-value="<?php echo $unlockRank; ?>"><?php echo $unlockRank; ?></td>
			<td class="k2-table-cell--left"><?php echo $playerCell; ?></td>
			<td class="k2-ms-achiever-unlocked" data-k2-sort-value="<?php echo $achievedSort; ?>"><?php echo k2_h($ach['achieved_label']); ?></td>
			<td class="k2-table-cell--left k2-ms-achiever-event-cell"><?php echo $ach['event_html']; ?></td>
			<td class="k2-table-cell--left"><?php echo $ach['link_html']; ?></td>
		</tr>
        <?php
    }
    ?>
	</tbody>
	</table>
	</div>
    <?php
}

/**
 * milestone.php — Made it | Graphs segment (URL ?panel=).
 */
function k2_milestone_render_detail_panel_nav(string $milestoneKey, string $activePanel): void
{
    $tabs = [
        'unlockers' => ['label' => 'Made it'],
        'graphs' => ['label' => 'Graphs'],
    ];
    ?>
<div class="k2-chrome-tabs k2-ms-detail-panel-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll role="tablist" aria-label="Milestone detail">
    <?php foreach ($tabs as $id => $tab) {
        $isActive = $activePanel === $id;
        ?>
		<a id="k2-ms-detail-tab-<?php echo k2_h($id); ?>"
			href="<?php echo k2_h(k2_milestone_detail_href($milestoneKey, $id, false)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			role="tab"
			aria-controls="k2-ms-detail-panel-<?php echo k2_h($id); ?>"
			<?php echo $isActive ? ' aria-current="page" aria-selected="true" tabindex="0"' : ' aria-selected="false" tabindex="-1"'; ?>><?php echo k2_h($tab['label']); ?></a>
    <?php } ?>
	</nav>
</div>
    <?php
}

/**
 * milestone.php — single spotlight card (name + rule; garden-style glow, larger type).
 *
 * @param array<string, mixed> $definition from k2_milestone_definition_hub()
 */
function k2_milestone_render_detail_spotlight(array $definition): void
{
    $token = k2_h((string) $definition['chart_token']);
    $displayName = k2_h((string) $definition['display_name']);
    $ruleShort = k2_h((string) $definition['rule_short']);
    ?>
<div id="<?php echo k2_h(K2_MILESTONE_DETAIL_FRAGMENT); ?>" class="k2-ms-detail-spotlight-anchor" tabindex="-1"></div>
<header class="k2-ms-detail-spotlight" aria-labelledby="k2-ms-detail-spotlight-title">
	<article class="k2-ms-card k2-ms-detail-spotlight-card is-unlocked k2-ms-card--<?php echo $token; ?>">
		<h1 id="k2-ms-detail-spotlight-title" class="k2-ms-card__title k2-ms-detail-spotlight-card__title"><?php echo $displayName; ?></h1>
		<p class="k2-ms-card__rule k2-ms-detail-spotlight-card__rule"><?php echo $ruleShort; ?></p>
	</article>
</header>
    <?php
}

/**
 * @param array<string, mixed> $definition
 * @param array<int, array<string, mixed>> $achievers
 */
function k2_milestone_render_detail_achievers(array $definition, array $achievers): void
{
    ?>
<section class="k2-ms-detail-section k2-ms-detail-panel__unlockers" aria-labelledby="k2-ms-detail-panel-unlockers">
	<h2 class="k2-panel-heading visually-hidden" id="k2-ms-detail-panel-unlockers">Made it</h2>
    <?php if ($achievers === []) { ?>
	<p class="k2-ms-meta-hint">Nobody has unlocked this milestone yet.</p>
    <?php } else {
        k2_milestone_render_achievers_table($achievers, [
            'chart_token' => (string) ($definition['chart_token'] ?? ''),
        ]);
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
<section class="k2-ms-garden__section" id="<?php echo k2_h(k2_milestone_garden_tier_anchor_id($tier)); ?>" data-tier="<?php echo k2_h($tier); ?>">
	<h2 class="<?php echo k2_h(k2_milestone_tier_heading_class($tier)); ?>"><?php echo k2_h($label); ?></h2>
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
                    echo ' · ' . k2_milestone_tier_event_link_html((string) $card['source_link'], $token);
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
