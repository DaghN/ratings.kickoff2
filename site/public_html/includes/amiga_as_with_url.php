<?php
/**
 * Amiga time travel — `as_with=` URL parse and propagation (TT ribbon).
 *
 * @see docs/with-player-stepper-policy.md
 */
declare(strict_types=1);

/** Parse `as_with` from request; 0 when absent or invalid. */
function amiga_as_with_from_request(): int
{
    if (!isset($_GET['as_with'])) {
        return 0;
    }
    $id = filter_var($_GET['as_with'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        return 0;
    }

    return (int) $id;
}

/**
 * @param array<string, scalar|null> $query
 * @return array<string, scalar|null>
 */
function amiga_as_with_append_to_query(array $query): array
{
    $playerId = amiga_as_with_from_request();
    if ($playerId > 0) {
        $query['as_with'] = $playerId;
    }

    return $query;
}