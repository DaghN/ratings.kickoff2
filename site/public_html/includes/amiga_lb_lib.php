<?php
/**
 * Amiga leaderboard wings — shared read-path SQL (amiga_player_current).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/lb_player_filters.php';

/** WHERE clause for career-stat wings (players with at least one rated game). */
function amiga_lb_player_where_sql(): string
{
    return 's.NumberGames > 0';
}

/** Load time-travel context for leaderboard pages (after DB connect). */
function amiga_lb_context(mysqli $con): AmigaSnapshotContext
{
    return amiga_snapshot_context_from_request($con);
}

/**
 * Amiga leaderboard wing URL that scrolls to the table top (profile hero stat links).
 *
 * @param array<string, scalar> $query
 */
function amiga_lb_table_href(string $wingPath, array $query = []): string
{
    return amiga_url_with_context($wingPath, $query) . k2_lb_table_anchor_hash();
}
