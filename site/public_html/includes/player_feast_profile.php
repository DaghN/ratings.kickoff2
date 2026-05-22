<?php
/**
 * Query wrapper for player_feast_load_pm. When $GLOBALS['k2_player_feast_profile'] is an array,
 * each query is timed and logged (opt-in debugging; no overhead when unset).
 */

/**
 * @return mysqli_result|bool
 */
function k2_player_feast_query(mysqli $con, string $label, string $sql)
{
    $profile = null;
    if (isset($GLOBALS['k2_player_feast_profile']) && is_array($GLOBALS['k2_player_feast_profile'])) {
        $profile = &$GLOBALS['k2_player_feast_profile'];
    }

    $start = $profile !== null ? microtime(true) : 0.0;
    $result = mysqli_query($con, $sql);
    if ($profile !== null) {
        $ms = (microtime(true) - $start) * 1000;
        $profile['queries'][] = [
            'label' => $label,
            'ms' => round($ms, 2),
            'ok' => $result !== false,
            'error' => $result === false ? mysqli_error($con) : null,
        ];
        $profile['total_query_ms'] = ($profile['total_query_ms'] ?? 0) + $ms;
    }

    return $result;
}

function k2_player_feast_profile_mark(string $label, float $ms): void
{
    if (!isset($GLOBALS['k2_player_feast_profile']) || !is_array($GLOBALS['k2_player_feast_profile'])) {
        return;
    }
    $GLOBALS['k2_player_feast_profile']['marks'][] = [
        'label' => $label,
        'ms' => round($ms, 2),
    ];
}
