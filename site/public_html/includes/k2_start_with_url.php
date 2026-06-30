<?php
/**
 * Online league period stepper — `start_with=` URL parse and propagation.
 *
 * @see docs/with-player-stepper-policy.md
 */
declare(strict_types=1);

/** Parse `start_with` from request; 0 when absent or invalid. */
function k2_start_with_from_request(): int
{
    if (!isset($_GET['start_with'])) {
        return 0;
    }
    $id = filter_var($_GET['start_with'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        return 0;
    }

    return (int) $id;
}

/**
 * @param array<string, scalar|null> $query
 * @return array<string, scalar|null>
 */
function k2_start_with_append_to_query(array $query): array
{
    $playerId = k2_start_with_from_request();
    if ($playerId > 0) {
        $query['start_with'] = $playerId;
    }

    return $query;
}