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
	'amiga-player-opponents-country-h2h' => 'amiga/player/opponents/country/h2h.php',
	'amiga-player-opponents-country-wdl' => 'amiga/player/opponents/country/wdl.php',
	'amiga-player-opponents-country-goals' => 'amiga/player/opponents/country/goals.php',
	'amiga-player-opponents-country-dds' => 'amiga/player/opponents/country/dds.php',
	'amiga-player-chronologies-opponents-made-it' => 'amiga/player/chronologies/opponents/made-it.php',
	'amiga-player-chronologies-opponents-graphs' => 'amiga/player/chronologies/opponents/graphs.php',
	'amiga-player-chronologies-victims-made-it' => 'amiga/player/chronologies/victims/made-it.php',
	'amiga-player-chronologies-victims-graphs' => 'amiga/player/chronologies/victims/graphs.php',
	'amiga-player-chronologies-dd-victims-made-it' => 'amiga/player/chronologies/dd_victims/made-it.php',
	'amiga-player-chronologies-dd-victims-graphs' => 'amiga/player/chronologies/dd_victims/graphs.php',
	'amiga-player-chronologies-cs-victims-made-it' => 'amiga/player/chronologies/cs_victims/made-it.php',
	'amiga-player-chronologies-cs-victims-graphs' => 'amiga/player/chronologies/cs_victims/graphs.php',
	'amiga-player-chronologies-mgc-victims-made-it' => 'amiga/player/chronologies/mgc_victims/made-it.php',
	'amiga-player-chronologies-mgc-victims-graphs' => 'amiga/player/chronologies/mgc_victims/graphs.php',
	'amiga-player-chronologies-bl-victims-made-it' => 'amiga/player/chronologies/bl_victims/made-it.php',
	'amiga-player-chronologies-bl-victims-graphs' => 'amiga/player/chronologies/bl_victims/graphs.php',
	'amiga-player-chronologies-culprits-made-it' => 'amiga/player/chronologies/culprits/made-it.php',
	'amiga-player-chronologies-culprits-graphs' => 'amiga/player/chronologies/culprits/graphs.php',
	'amiga-player-chronologies-dd-culprits-made-it' => 'amiga/player/chronologies/dd_culprits/made-it.php',
	'amiga-player-chronologies-dd-culprits-graphs' => 'amiga/player/chronologies/dd_culprits/graphs.php',
	'amiga-player-chronologies-cs-culprits-made-it' => 'amiga/player/chronologies/cs_culprits/made-it.php',
	'amiga-player-chronologies-cs-culprits-graphs' => 'amiga/player/chronologies/cs_culprits/graphs.php',
	'amiga-player-chronologies-mgs-culprits-made-it' => 'amiga/player/chronologies/mgs_culprits/made-it.php',
	'amiga-player-chronologies-mgs-culprits-graphs' => 'amiga/player/chronologies/mgs_culprits/graphs.php',
	'amiga-player-chronologies-bw-culprits-made-it' => 'amiga/player/chronologies/bw_culprits/made-it.php',
	'amiga-player-chronologies-bw-culprits-graphs' => 'amiga/player/chronologies/bw_culprits/graphs.php',
	'amiga-player-chronologies-host-countries-made-it' => 'amiga/player/chronologies/host_countries/made-it.php',
	'amiga-player-chronologies-host-countries-graphs' => 'amiga/player/chronologies/host_countries/graphs.php',
	'amiga-player-chronologies-countries-faced-made-it' => 'amiga/player/chronologies/countries_faced/made-it.php',
	'amiga-player-chronologies-countries-faced-graphs' => 'amiga/player/chronologies/countries_faced/graphs.php',
	'amiga-player-chronologies-countries-beaten-made-it' => 'amiga/player/chronologies/countries_beaten/made-it.php',
	'amiga-player-chronologies-countries-beaten-graphs' => 'amiga/player/chronologies/countries_beaten/graphs.php',
	'amiga-player-chronologies-countries-beaten-by-made-it' => 'amiga/player/chronologies/countries_beaten_by/made-it.php',
	'amiga-player-chronologies-countries-beaten-by-graphs' => 'amiga/player/chronologies/countries_beaten_by/graphs.php',
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
	'amiga-world-cups-countries-participation' => 'amiga/world-cups/countries/participation.php',
	'amiga-world-cups-countries-goals' => 'amiga/world-cups/countries/goals.php',
	'amiga-world-cups-countries-dds' => 'amiga/world-cups/countries/dds.php',
	'amiga-world-cups-countries-opponents' => 'amiga/world-cups/countries/opponents.php',
	'amiga-countries' => 'amiga/countries.php',
	'amiga-country-roster' => 'amiga/country/roster.php',
	'amiga-country-rivals' => 'amiga/country/rivals.php',
	'amiga-country-rivals-h2h' => 'amiga/country/rivals/h2h.php',
	'amiga-country-rivals-wdl' => 'amiga/country/rivals/wdl.php',
	'amiga-country-rivals-goals' => 'amiga/country/rivals/goals.php',
	'amiga-country-rivals-dds' => 'amiga/country/rivals/dds.php',
	'amiga-countries-roster' => 'amiga/countries/roster.php',
	'amiga-activity' => 'amiga/activity/growth.php',
	'amiga-activity-growth' => 'amiga/activity/growth.php',
	'amiga-activity-people' => 'amiga/activity/people.php',
	'amiga-activity-geography' => 'amiga/activity/geography/hosts.php',
	'amiga-activity-geography-hosts' => 'amiga/activity/geography/hosts.php',
	'amiga-activity-geography-nations' => 'amiga/activity/geography/nations.php',
	'amiga-activity-world-cups' => 'amiga/activity/world-cups.php',
	'amiga-activity-texture' => 'amiga/activity/texture.php',
	'amiga-activity-shape' => 'amiga/activity/shape.php',
	// Deprecated route keys — canonical home is World Cups hub → Player stats (Jun 2026).
	'amiga-lb-world-cups' => 'amiga/world-cups/players/honours.php',
	'amiga-lb-world-cups-honours' => 'amiga/world-cups/players/honours.php',
	'amiga-lb-world-cups-results' => 'amiga/world-cups/players/results.php',
	'amiga-lb-world-cups-goals' => 'amiga/world-cups/players/goals.php',
	'amiga-lb-world-cups-dds' => 'amiga/world-cups/players/dds.php',
	'amiga-lb-world-cups-opponents' => 'amiga/world-cups/players/opponents.php',
	'amiga-lb-performance-rating' => 'amiga/leaderboards/performance-rating/best.php',
	'amiga-lb-performance-rating-best' => 'amiga/leaderboards/performance-rating/best.php',
	'amiga-lb-performance-rating-top' => 'amiga/leaderboards/performance-rating/top.php',
	'amiga-lb-performance-rating-perfect' => 'amiga/leaderboards/performance-rating/perfect.php',
	'amiga-tournaments' => 'amiga/tournaments.php',
	'amiga-games' => 'amiga/games/recent.php',
	'amiga-games-recent' => 'amiga/games/recent.php',
	'amiga-games-highlights' => 'amiga/games/highlights.php',
	'amiga-games-all' => 'amiga/games/all.php',
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

function k2_amiga_game_page_anchor_hash(int $gameId = 0): string
{
	if ($gameId > 0) {
		require_once __DIR__ . '/amiga_tournament_videos_lib.php';
		$videos = amiga_videos_for_game_id($gameId);
		if ($videos !== []) {
			return '#' . amiga_game_videos_scroll_target_id(count($videos));
		}
	}

	return '#k2-game';
}

function k2_amiga_game_page_url(int $gameId): string
{
	return k2_amiga_route('amiga-game', ['id' => $gameId]) . k2_amiga_game_page_anchor_hash($gameId);
}

/** 302 redirect preserving the request query string (legacy Amiga player URLs). */
function k2_amiga_legacy_redirect(string $canonicalPath): void
{
	$query = $_SERVER['QUERY_STRING'] ?? '';
	$target = $canonicalPath . ($query !== '' ? '?' . $query : '');
	header('Location: ' . $target, true, 302);
	exit;
}
