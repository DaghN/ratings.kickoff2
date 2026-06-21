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
	'amiga-player-profile' => 'amiga/player/profile.php',
	'amiga-player-games' => 'amiga/player/games.php',
	'amiga-player-tournaments' => 'amiga/player/tournaments.php',
	'amiga-player-opponents-h2h' => 'amiga/player/opponents/h2h.php',
	'amiga-player-opponents-wdl' => 'amiga/player/opponents/wdl.php',
	'amiga-player-opponents-goals' => 'amiga/player/opponents/goals.php',
	'amiga-player-opponents-dds' => 'amiga/player/opponents/dds.php',
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
