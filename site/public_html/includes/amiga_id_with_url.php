<?php
/**
 * Tournament entity stepper — `id_with=` URL parse and propagation.
 *
 * @see docs/with-player-stepper-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';

/** Parse `id_with` from request; 0 when absent or invalid. */
function amiga_id_with_from_request(): int
{
    if (!isset($_GET['id_with'])) {
        return 0;
    }
    $id = filter_var($_GET['id_with'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        return 0;
    }

    return (int) $id;
}

function amiga_id_with_append_to_path(string $url): string
{
    if (!amiga_tournament_page_request_path($url)) {
        return $url;
    }
    $playerId = amiga_id_with_from_request();
    if ($playerId < 1) {
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

    $query['id_with'] = $playerId;

    return $pathPart . '?' . http_build_query($query) . $hash;
}