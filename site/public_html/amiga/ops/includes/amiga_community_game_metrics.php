<?php
/**
 * Shared game-level metric helpers (mirrors scripts/amiga/community_game_metrics.py).
 */
declare(strict_types=1);

function amiga_community_year_key(?string $eventDate): ?string
{
    if ($eventDate === null || $eventDate === '') {
        return null;
    }

    return substr((string) $eventDate, 0, 4);
}

/**
 * @return array{0: int, 1: int}
 */
function amiga_community_canonical_pair(int $playerAId, int $playerBId): array
{
    return $playerAId <= $playerBId ? [$playerAId, $playerBId] : [$playerBId, $playerAId];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function amiga_community_rated_game_metrics(array $row): array
{
    $goalsA = (int) $row['goals_a'];
    $goalsB = (int) $row['goals_b'];
    $sumGoals = (int) ($row['sum_of_goals'] ?? ($goalsA + $goalsB));
    $actual = (float) ($row['actual_score'] ?? 0);
    $isDraw = abs($actual - 0.5) < 1e-9;
    $ddSlots = (int) ($row['dd_player_a'] ?? 0) + (int) ($row['dd_player_b'] ?? 0);
    $csSlots = (int) ($row['cs_player_a'] ?? 0) + (int) ($row['cs_player_b'] ?? 0);
    $margin = abs($goalsA - $goalsB);
    $phaseRaw = $row['phase'] ?? null;
    $phase = ($phaseRaw !== null && trim((string) $phaseRaw) !== '') ? trim((string) $phaseRaw) : null;

    return [
        'player_a_id' => (int) $row['player_a_id'],
        'player_b_id' => (int) $row['player_b_id'],
        'goals_a' => $goalsA,
        'goals_b' => $goalsB,
        'sum_of_goals' => $sumGoals,
        'is_draw' => $isDraw,
        'dd_slots' => $ddSlots,
        'cs_slots' => $csSlots,
        'is_high_scoring' => $sumGoals >= 10,
        'is_low_scoring' => $sumGoals <= 3,
        'is_blowout' => !$isDraw && $margin >= 5,
        'margin' => $margin,
        'phase' => $phase,
        'game_id' => isset($row['game_id']) ? (int) $row['game_id'] : null,
    ];
}

function amiga_community_is_knockout_phase(?string $phase): bool
{
    if ($phase === null || $phase === '') {
        return false;
    }
    $text = strtolower($phase);

    return str_contains($text, 'knockout')
        || in_array($text, ['final', 'semi final', 'semi finals', 'quarter final', 'quarter finals'], true);
}

function amiga_community_rate(int $numerator, int $denominator): ?float
{
    if ($denominator <= 0) {
        return null;
    }

    return round($numerator / $denominator, 8);
}
