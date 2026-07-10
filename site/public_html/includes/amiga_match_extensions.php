<?php
declare(strict_types=1);

/**
 * Structured L3 match extensions (SC-11) — ET / penalties; extra = witness text.
 */

require_once __DIR__ . '/amiga_tournament_lib.php';

/**
 * @return array{goals_et_a: ?int, goals_et_b: ?int, pens_a: ?int, pens_b: ?int}|null
 */
function amiga_extract_structured_from_extra(?string $extra): ?array
{
    if ($extra === null) {
        return null;
    }
    $text = trim($extra);
    if ($text === '') {
        return null;
    }

    $pensA = null;
    $pensB = null;
    $goalsEtA = null;
    $goalsEtB = null;

    $penPatterns = [
        '/\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)\s*(?:p\.?k\.?|pen)/i',
        '/\((\d+)\s*-\s*(\d+)\)\s*(\d+)\s*-\s*(\d+)(?:pen)?/i',
        '/\((\d+)\s*-\s*(\d+)\s*pen\.?\)/i',
        '/(\d+)\s*-\s*(\d+)\s*pen/i',
    ];
    foreach ($penPatterns as $pat) {
        if (preg_match($pat, $text, $m) !== 1) {
            continue;
        }
        if (count($m) >= 5) {
            $pensA = (int) $m[3];
            $pensB = (int) $m[4];
        } else {
            $pensA = (int) $m[1];
            $pensB = (int) $m[2];
        }
        break;
    }

    if ($pensA === null) {
        $etPatterns = [
            '/\((\d+)\s*-\s*(\d+)\)\s*(?:a\.)?e\.?\s*t\.?/i',
            '/(\d+)\s*-\s*(\d+)\s*(?:a\.)?e\.?\s*t\.?/i',
        ];
        foreach ($etPatterns as $pat) {
            if (preg_match($pat, $text, $m) !== 1) {
                continue;
            }
            $goalsEtA = (int) $m[1];
            $goalsEtB = (int) $m[2];
            break;
        }
    }

    if ($pensA === null && $goalsEtA === null) {
        return null;
    }

    return [
        'goals_et_a' => $goalsEtA,
        'goals_et_b' => $goalsEtB,
        'pens_a' => $pensA,
        'pens_b' => $pensB,
    ];
}

/**
 * @param array<string, mixed> $game
 * @return array{goals_et_a: ?int, goals_et_b: ?int, pens_a: ?int, pens_b: ?int}
 */
function amiga_game_extension_fields(array $game): array
{
    $read = static function (string $key) use ($game): ?int {
        if (!array_key_exists($key, $game) || $game[$key] === null) {
            return null;
        }
        $val = (int) $game[$key];

        return $val >= 0 ? $val : null;
    };

    return [
        'goals_et_a' => $read('goals_et_a'),
        'goals_et_b' => $read('goals_et_b'),
        'pens_a' => $read('pens_a'),
        'pens_b' => $read('pens_b'),
    ];
}

function amiga_winner_from_extension_pair(
    int $scoreA,
    int $scoreB,
    int $playerAId,
    int $playerBId
): ?int {
    if ($scoreA > $scoreB) {
        return $playerAId;
    }
    if ($scoreB > $scoreA) {
        return $playerBId;
    }

    return null;
}

/**
 * @param array<string, mixed> $game
 */
function amiga_resolve_game_extension_winner(
    array $game,
    string $step,
    int $playerAId,
    int $playerBId
): ?int {
    $ext = amiga_game_extension_fields($game);

    if ($step === 'penalty_shootout') {
        if ($ext['pens_a'] !== null && $ext['pens_b'] !== null) {
            return amiga_winner_from_extension_pair($ext['pens_a'], $ext['pens_b'], $playerAId, $playerBId);
        }
    } elseif ($step === 'extra_time') {
        if ($ext['goals_et_a'] !== null && $ext['goals_et_b'] !== null) {
            return amiga_winner_from_extension_pair(
                $ext['goals_et_a'],
                $ext['goals_et_b'],
                $playerAId,
                $playerBId
            );
        }
    } elseif ($step === 'golden_goal') {
        if ($ext['goals_et_a'] !== null && $ext['goals_et_b'] !== null) {
            $won = amiga_winner_from_extension_pair(
                $ext['goals_et_a'],
                $ext['goals_et_b'],
                $playerAId,
                $playerBId
            );
            if ($won !== null) {
                return $won;
            }
        }
    }

    $extra = isset($game['extra']) ? (string) $game['extra'] : '';
    if (trim($extra) === '') {
        return null;
    }

    return amiga_parse_standings_winner(
        (int) $game['goals_a'],
        (int) $game['goals_b'],
        $extra,
        $playerAId,
        $playerBId
    );
}