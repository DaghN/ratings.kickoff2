<?php
/**
 * Derive participation placement (overall_position) from standings scopes.
 *
 * Parity with scripts/amiga/participation_placement.py — games define roster;
 * placement is a derived view by event shape.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';

function amiga_participation_knockout_scope_label(string $scopeKey): string
{
    $parts = explode('|', $scopeKey, 2);

    return trim($parts[0] ?? '');
}

/**
 * @return array<string, int>
 */
function amiga_participation_knockout_round_depths(): array
{
    return [
        'round of 64' => 10,
        'round of 32' => 20,
        'round of 16' => 30,
        'quarter final' => 40,
        'quarter finals' => 40,
        'semi final' => 50,
        'semi finals' => 50,
        '3rd place final' => 55,
        'final' => 60,
    ];
}

function amiga_participation_normalize_knockout_label(string $label): string
{
    $text = strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? ''));
    if (preg_match('/^(?:quarter|semi)\s+final$/i', $text)) {
        return $text . 's';
    }

    return $text;
}

function amiga_participation_knockout_round_depth(string $label): int
{
    $norm = amiga_participation_normalize_knockout_label($label);
    $depths = amiga_participation_knockout_round_depths();
    if (isset($depths[$norm])) {
        return $depths[$norm];
    }
    if (str_starts_with($norm, 'places ')) {
        return 5;
    }
    if (preg_match('/^\d+(?:st|nd|rd|th)\s+place\s+final$/', $norm)) {
        return 55;
    }

    return 0;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_derive_wc_group_positions(array $standingRows): array
{
    /** @var array<int, array{0: string, 1: int}> $byPlayer */
    $byPlayer = [];
    foreach ($standingRows as $row) {
        if ((string) ($row['scope_type'] ?? '') !== 'group') {
            continue;
        }
        $playerId = (int) $row['player_id'];
        $scopeKey = (string) ($row['scope_key'] ?? '');
        $position = (int) $row['position'];
        if (!isset($byPlayer[$playerId]) || $scopeKey < $byPlayer[$playerId][0]) {
            $byPlayer[$playerId] = [$scopeKey, $position];
        }
    }
    $out = [];
    foreach ($byPlayer as $playerId => [$scopeKey, $position]) {
        $out[(int) $playerId] = (int) $position;
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_overall_positions(array $standingRows): array
{
    $out = [];
    foreach ($standingRows as $row) {
        if ((string) ($row['scope_type'] ?? '') === 'overall' && (string) ($row['scope_key'] ?? '') === '') {
            $out[(int) $row['player_id']] = (int) $row['position'];
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_compute_knockout_event_finish(array $standingRows): array
{
    $koRows = [];
    foreach ($standingRows as $row) {
        $scopeType = (string) ($row['scope_type'] ?? '');
        if ($scopeType === 'knockout' || $scopeType === 'placement') {
            $koRows[] = $row;
        }
    }
    if ($koRows === []) {
        return [];
    }

    $positions = [];
    $allPlayers = [];
    foreach ($koRows as $row) {
        $allPlayers[(int) $row['player_id']] = true;
    }

    $assignFinalPodium = static function (string $targetLabel, int $winnerRank, int $loserRank) use ($koRows, &$positions): void {
        foreach ($koRows as $row) {
            if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
                continue;
            }
            $label = amiga_participation_normalize_knockout_label(
                amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''))
            );
            if ($label !== $targetLabel) {
                continue;
            }
            $playerId = (int) $row['player_id'];
            $pos = (int) $row['position'];
            if ($pos === 1 && !isset($positions[$playerId])) {
                $positions[$playerId] = $winnerRank;
            } elseif ($pos === 2 && !isset($positions[$playerId])) {
                $positions[$playerId] = $loserRank;
            }
        }
    };

    $assignFinalPodium('final', 1, 2);
    $assignFinalPodium('3rd place final', 3, 4);

    $unassigned = array_values(array_diff(array_keys($allPlayers), array_keys($positions)));
    sort($unassigned, SORT_NUMERIC);
    if ($unassigned === []) {
        return $positions;
    }

    $depthRows = [];
    foreach ($unassigned as $playerId) {
        $deepestDepth = -1;
        $deepestPos = 99;
        foreach ($koRows as $row) {
            if ((int) $row['player_id'] !== $playerId) {
                continue;
            }
            $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
            $depth = amiga_participation_knockout_round_depth($label);
            $pos = (int) $row['position'];
            if ($depth > $deepestDepth || ($depth === $deepestDepth && $pos < $deepestPos)) {
                $deepestDepth = $depth;
                $deepestPos = $pos;
            }
        }
        $depthRows[] = [$playerId, $deepestDepth, $deepestPos, $playerId];
    }

    usort(
        $depthRows,
        static fn (array $a, array $b): int => $a[1] !== $b[1]
            ? $b[1] <=> $a[1]
            : ($a[2] <=> $b[2] ?: $a[3] <=> $b[3])
    );

    $nextRank = $positions === [] ? 1 : max($positions) + 1;
    if ($nextRank < 3) {
        $nextRank = 3;
    }
    foreach ($depthRows as [$playerId]) {
        $positions[(int) $playerId] = $nextRank;
        $nextRank++;
    }

    return $positions;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @param list<int>|null $playerIds
 * @return array<int, int>
 */
function amiga_participation_derive_positions(
    array $standingRows,
    string $tournamentName,
    ?array $playerIds = null
): array {
    if (amiga_tournament_is_world_cup(['name' => $tournamentName])) {
        $base = amiga_participation_derive_wc_group_positions($standingRows);
    } else {
        $overall = amiga_participation_overall_positions($standingRows);
        $base = $overall !== [] ? $overall : amiga_participation_compute_knockout_event_finish($standingRows);
    }

    if ($playerIds === null) {
        return $base;
    }

    $out = [];
    foreach ($playerIds as $playerId) {
        $playerId = (int) $playerId;
        $out[$playerId] = (int) ($base[$playerId] ?? 0);
    }

    return $out;
}

function amiga_participation_is_winner(string $tournamentName, int $overallPosition, string $wcMedal = 'none'): bool
{
    if (amiga_tournament_is_world_cup(['name' => $tournamentName])) {
        return $wcMedal === 'gold';
    }

    return $overallPosition === 1;
}
