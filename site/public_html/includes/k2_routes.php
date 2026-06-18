<?php
/**
 * Canonical page paths (site-root absolute). Single source for hub, nav, and cross-links.
 */
declare(strict_types=1);

/** @var array<string, string> */
const K2_ROUTES = [
	'status' => 'status.php',
	'activity' => 'activity.php',
	'hall-of-fame' => 'hall-of-fame.php',
	'games' => 'games/recent.php',
	'games-recent' => 'games/recent.php',
	'games-highlights' => 'games/highlights.php',
	'games-all' => 'games/all.php',
	'join' => 'join.php',
	'milestones' => 'milestones/recent.php',
	'milestones-recent' => 'milestones/recent.php',
	'milestones-catalog' => 'milestones/catalog.php',
	'milestone' => 'milestone.php',
	'game' => 'game.php',
	'league' => 'league.php',
	'lb-rating' => 'leaderboards/rating.php',
	'lb-peak-rating' => 'leaderboards/peak-rating.php',
	'lb-goals' => 'leaderboards/goals.php',
	'lb-double-digits' => 'leaderboards/double-digits.php',
	'lb-streaks' => 'leaderboards/streaks.php',
	'lb-victims' => 'leaderboards/victims.php',
	'lb-activity' => 'leaderboards/activity/participation.php',
	'lb-activity-peaks' => 'leaderboards/activity/peaks.php',
	'lb-activity-participation' => 'leaderboards/activity/participation.php',
	'lb-activity-in-a-row' => 'leaderboards/activity/in-a-row.php',
	'lb-league-honours' => 'leaderboards/league-honours.php',
	'lb-milestones' => 'leaderboards/milestones.php',
	'player-profile' => 'player/profile.php',
	'player-games' => 'player/games.php',
	'player-opponents' => 'player/opponents/h2h.php', /* default Opponents pill → Head-to-head */
	'player-opponents-h2h' => 'player/opponents/h2h.php',
	'player-opponents-wdl' => 'player/opponents/wdl.php',
	'player-opponents-goals' => 'player/opponents/goals.php',
	'player-opponents-dds' => 'player/opponents/dds.php',
	'player-milestones' => 'player/milestones/garden.php', /* default Milestones pill → Garden */
	'player-milestones-garden' => 'player/milestones/garden.php',
	'player-milestones-chronology' => 'player/milestones/chronology.php',
];

function k2_route(string $name, array $params = []): string
{
	$relative = K2_ROUTES[$name] ?? ltrim($name, '/');
	$url = '/' . $relative;
	if ($params === []) {
		return $url;
	}

	return $url . '?' . http_build_query($params);
}

/** Current script path relative to site root (e.g. leaderboards/rating.php). */
function k2_current_page_path(): string
{
	return ltrim((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/');
}

function k2_route_is_current(string $name): bool
{
	return k2_current_page_path() === (K2_ROUTES[$name] ?? '');
}
