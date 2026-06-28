<?php
/**
 * Canonical Amiga realm page paths (site-root absolute).
 *
 * @see docs/url-routes.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_url.php';

/** @var array<string, string> */
const K2_AMIGA_ROUTES = [
	'amiga-game' => 'amiga/game.php',
	'amiga-tournament-event-stats' => 'amiga/tournament/event-stats.php',
	'amiga-tournament-standings' => 'amiga/tournament/standings.php',
	'amiga-tournament-stages' => 'amiga/tournament/stages.php',
	'amiga-tournament-games' => 'amiga/tournament/games.php',
	'amiga-tournament-videos' => 'amiga/tournament/videos/games.php',
	'amiga-tournament-videos-games' => 'amiga/tournament/videos/games.php',
	'amiga-tournament-videos-atmosphere' => 'amiga/tournament/videos/atmosphere.php',
	'amiga-video-orphans' => 'amiga/videos/orphans.php',
	'amiga-player-profile' => 'amiga/player/profile.php',
	'amiga-player-games' => 'amiga/player/games.php',
	'amiga-player-tournaments' => 'amiga/player/tournaments.php',
	'amiga-player-videos' => 'amiga/player/videos.php',
	'amiga-player-opponents-h2h' => 'amiga/player/opponents/h2h.php',
	'amiga-player-opponents-wdl' => 'amiga/player/opponents/wdl.php',
	'amiga-player-opponents-goals' => 'amiga/player/opponents/goals.php',
	'amiga-player-opponents-dds' => 'amiga/player/opponents/dds.php',
	'amiga-world-cups' => 'amiga/world-cups/chronology.php',
	'amiga-world-cups-chronology' => 'amiga/world-cups/chronology.php',
	'amiga-world-cups-stats' => 'amiga/world-cups/stats/participation.php',
	'amiga-world-cups-stats-goals' => 'amiga/world-cups/stats/goals.php',
	'amiga-world-cups-stats-dds' => 'amiga/world-cups/stats/dds.php',
	'amiga-world-cups-stats-participation' => 'amiga/world-cups/stats/participation.php',
	'amiga-world-cups-stats-geography' => 'amiga/world-cups/stats/geography.php',
	'amiga-world-cups-stats-podium' => 'amiga/world-cups/stats/podium.php',
	'amiga-world-cups-players' => 'amiga/world-cups/players/honours.php',
	'amiga-world-cups-players-honours' => 'amiga/world-cups/players/honours.php',
	'amiga-world-cups-players-results' => 'amiga/world-cups/players/results.php',
	'amiga-world-cups-players-goals' => 'amiga/world-cups/players/goals.php',
	'amiga-world-cups-players-dds' => 'amiga/world-cups/players/dds.php',
	'amiga-world-cups-players-opponents' => 'amiga/world-cups/players/opponents.php',
	'amiga-world-cups-countries' => 'amiga/world-cups/countries/honours.php',
	'amiga-world-cups-countries-honours' => 'amiga/world-cups/countries/honours.php',
	'amiga-world-cups-countries-results' => 'amiga/world-cups/countries/results.php',
	'amiga-world-cups-countries-goals' => 'amiga/world-cups/countries/goals.php',
	'amiga-world-cups-countries-dds' => 'amiga/world-cups/countries/dds.php',
	'amiga-world-cups-countries-opponents' => 'amiga/world-cups/countries/opponents.php',
	'amiga-countries' => 'amiga/countries/index.php',
	'amiga-country-roster' => 'amiga/country/roster.php',
	'amiga-country-rivals' => 'amiga/country/rivals.php',
	'amiga-countries-roster' => 'amiga/countries/roster.php',
	'amiga-lb-world-cups' => 'amiga/leaderboards/world-cups/honours.php',
	'amiga-lb-world-cups-honours' => 'amiga/leaderboards/world-cups/honours.php',
	'amiga-lb-world-cups-results' => 'amiga/leaderboards/world-cups/results.php',
	'amiga-lb-world-cups-goals' => 'amiga/leaderboards/world-cups/goals.php',
	'amiga-lb-world-cups-dds' => 'amiga/leaderboards/world-cups/dds.php',
	'amiga-lb-world-cups-opponents' => 'amiga/leaderboards/world-cups/opponents.php',
];

function k2_amiga_route(string $name, array $params = []): string
{
	$relative = K2_AMIGA_ROUTES[$name] ?? ltrim($name, '/');
	$url = '/' . $relative;
	if ($params === []) {
		return amiga_url_with_context($url);
	}

	return amiga_url_with_context($url . '?' . http_build_query($params));
}

/** 302 redirect preserving the request query string (legacy Amiga player URLs). */
function k2_amiga_legacy_redirect(string $canonicalPath): void
{
	$query = $_SERVER['QUERY_STRING'] ?? '';
	$target = $canonicalPath . ($query !== '' ? '?' . $query : '');
	header('Location: ' . $target, true, 302);
	exit;
}
