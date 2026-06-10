<?php
/**
 * Amiga leaderboard wings — shared read-path SQL (amiga_player_stats only).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';

/** WHERE clause for career-stat wings (players with at least one rated game). */
function amiga_lb_player_where_sql(): string
{
    return 's.NumberGames > 0';
}
