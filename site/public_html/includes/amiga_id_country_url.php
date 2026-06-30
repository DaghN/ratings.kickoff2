<?php
/**
 * Tournament entity stepper — `id_country=` URL parse and propagation.
 *
 * @see docs/with-player-stepper-policy.md §5.7
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';

/** Parse `id_country` from request; '' when absent. */
function amiga_id_country_from_request(): string
{
    if (!isset($_GET['id_country'])) {
        return '';
    }
    $country = trim((string) $_GET['id_country']);
    if ($country === '') {
        return '';
    }

    return $country;
}

function amiga_id_country_append_to_path(string $url): string
{
    if (!amiga_tournament_page_request_path($url)) {
        return $url;
    }
    $country = amiga_id_country_from_request();
    if ($country === '') {
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

    $query['id_country'] = $country;

    return $pathPart . '?' . http_build_query($query) . $hash;
}