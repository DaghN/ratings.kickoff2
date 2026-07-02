<?php
/**
 * Tournament entity stepper — `id_wc=` URL parse and propagation.
 *
 * @see docs/with-player-stepper-policy.md §5.7
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_id_with_url.php';
require_once __DIR__ . '/amiga_id_country_url.php';

/** Parse `id_wc` from request; '' when absent or not world-cup. */
function amiga_id_wc_from_request(): string
{
    if (!isset($_GET['id_wc'])) {
        return '';
    }
    $wc = trim((string) $_GET['id_wc']);
    if ($wc === 'world-cup') {
        return 'world-cup';
    }

    return '';
}

function amiga_id_wc_append_to_path(string $url): string
{
    if (!amiga_tournament_page_request_path($url)) {
        return $url;
    }
    if (amiga_id_wc_from_request() !== 'world-cup') {
        return $url;
    }

    $hash = '';
    $hashPos = strpos($url, '#');
    if ($hashPos !== false) {
        $hash = substr($url, $hashPos);
        $url = substr($url, 0, $hashPos);
    }

    $pathPart = $url;
    /** @var array<string, scalar|null> $query */
    $query = [];
    $qPos = strpos($url, '?');
    if ($qPos !== false) {
        $pathPart = substr($url, 0, $qPos);
        parse_str(substr($url, $qPos + 1), $query);
    }

    $query['id_wc'] = 'world-cup';

    return $pathPart . '?' . http_build_query($query) . $hash;
}

/**
 * Build query for tournament step filter navigation (listbox submit, WC pill toggle).
 *
 * @return array<string, scalar>
 */
function amiga_tournament_step_filter_query_params(int $tournamentId, bool $wcOnly): array
{
    /** @var array<string, scalar> $params */
    $params = ['id' => $tournamentId];
    foreach ($_GET as $name => $value) {
        if (!is_string($name) || $name === '' || is_array($value)) {
            continue;
        }
        if (in_array($name, ['id', 'id_with', 'id_country', 'id_wc'], true)) {
            continue;
        }
        $params[$name] = $value;
    }

    $playerId = amiga_id_with_from_request();
    if ($playerId > 0) {
        $params['id_with'] = $playerId;
    }
    $country = amiga_id_country_from_request();
    if ($country !== '') {
        $params['id_country'] = $country;
    }
    if ($wcOnly) {
        $params['id_wc'] = 'world-cup';
    }

    return $params;
}

function amiga_id_wc_toggle_href(string $requestPath, int $tournamentId, bool $wcOnly): string
{
    $params = amiga_tournament_step_filter_query_params($tournamentId, $wcOnly);
    $hash = '';
    $hashPos = strpos($requestPath, '#');
    $path = $requestPath;
    if ($hashPos !== false) {
        $hash = substr($requestPath, $hashPos);
        $path = substr($requestPath, 0, $hashPos);
    }

    return $path . '?' . http_build_query($params) . $hash;
}