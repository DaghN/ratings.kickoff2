<?php
/**
 * Derive participation placement from standings scopes.
 *
 * ``amiga_participation_derive_event_finish_position`` → ``event_finish_position``.
 * Parity with scripts/amiga/participation_placement.py.
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

function amiga_participation_is_main_final_label(string $label): bool
{
    return amiga_participation_normalize_knockout_label($label) === 'final';
}

/**
 * @return array{0: int, 1: int}|null
 */
function amiga_participation_placement_final_winner_loser_ranks(string $label): ?array
{
    $norm = amiga_participation_normalize_knockout_label($label);
    if (!preg_match('/^(\d+)(?:st|nd|rd|th)\s+place\s+final$/', $norm, $matches)) {
        return null;
    }

    $base = (int) $matches[1];

    return [$base, $base + 1];
}

function amiga_participation_is_third_place_final_label(string $label): bool
{
    $ranks = amiga_participation_placement_final_winner_loser_ranks($label);

    return $ranks === [3, 4];
}

function amiga_participation_is_semi_final_label(string $label): bool
{
    $norm = amiga_participation_normalize_knockout_label($label);

    return $norm === 'semi final' || $norm === 'semi finals';
}

function amiga_participation_is_subsidiary_cup_knockout_label(string $label): bool
{
    $norm = amiga_participation_normalize_knockout_label($label);

    return preg_match('/^(?:silver|bronze|koa)\s+cup/', $norm) === 1;
}

function amiga_participation_is_main_bracket_knockout_label(string $label): bool
{
    if (amiga_participation_is_subsidiary_cup_knockout_label($label)) {
        return false;
    }
    if (amiga_participation_knockout_round_depth($label) > 0) {
        return true;
    }

    return amiga_participation_placement_final_winner_loser_ranks($label) !== null;
}

/**
 * @param list<array<string, mixed>> $koRows
 */
function amiga_participation_has_third_place_final_scope(array $koRows): bool
{
    foreach ($koRows as $row) {
        if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
            continue;
        }
        $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
        if (amiga_participation_is_third_place_final_label($label)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return list<array<string, mixed>>
 */
function amiga_participation_knockout_or_placement_rows(array $standingRows): array
{
    $out = [];
    foreach ($standingRows as $row) {
        $scopeType = (string) ($row['scope_type'] ?? '');
        if ($scopeType === 'knockout' || $scopeType === 'placement') {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $koRows
 * @return array{0: int, 1: int}
 */
function amiga_participation_deepest_knockout_rank_key(array $koRows, int $playerId): array
{
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

    return [$deepestDepth, $deepestPos];
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_compute_tier_a_knockout_finish(array $standingRows): array
{
    $koRows = amiga_participation_knockout_or_placement_rows($standingRows);
    if ($koRows === []) {
        return [];
    }

    $positions = [];
    $allPlayers = [];
    foreach ($koRows as $row) {
        $allPlayers[(int) $row['player_id']] = true;
    }

    $assignKnockoutPodium = static function (callable $labelPredicate, int $winnerRank, int $loserRank) use ($koRows, &$positions): void {
        foreach ($koRows as $row) {
            if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
                continue;
            }
            $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
            if (!$labelPredicate($label)) {
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

    $assignKnockoutPodium(
        static fn (string $label): bool => amiga_participation_is_main_final_label($label),
        1,
        2
    );

    foreach ($koRows as $row) {
        if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
            continue;
        }
        $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
        $ranks = amiga_participation_placement_final_winner_loser_ranks($label);
        if ($ranks === null) {
            continue;
        }
        [$winnerRank, $loserRank] = $ranks;
        $playerId = (int) $row['player_id'];
        $pos = (int) $row['position'];
        if ($pos === 1 && !isset($positions[$playerId])) {
            $positions[$playerId] = $winnerRank;
        } elseif ($pos === 2 && !isset($positions[$playerId])) {
            $positions[$playerId] = $loserRank;
        }
    }

    if (
        !amiga_participation_has_third_place_final_scope($koRows)
        && in_array(1, $positions, true)
        && in_array(2, $positions, true)
    ) {
        foreach ($koRows as $row) {
            if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
                continue;
            }
            $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
            if (!amiga_participation_is_semi_final_label($label)) {
                continue;
            }
            $playerId = (int) $row['player_id'];
            if ((int) $row['position'] === 2 && !isset($positions[$playerId])) {
                $positions[$playerId] = 3;
            }
        }
    }

    $unassigned = array_values(array_diff(array_keys($allPlayers), array_keys($positions)));
    sort($unassigned, SORT_NUMERIC);
    if ($unassigned === []) {
        return $positions;
    }

    $depthRows = [];
    foreach ($unassigned as $playerId) {
        [$depth, $pos] = amiga_participation_deepest_knockout_rank_key($koRows, (int) $playerId);
        $depthRows[] = [(int) $playerId, $depth, $pos, (int) $playerId];
    }
    usort(
        $depthRows,
        static fn (array $a, array $b): int => $a[1] !== $b[1]
            ? $b[1] <=> $a[1]
            : ($a[2] <=> $b[2] ?: $a[3] <=> $b[3])
    );

    $nextRank = max(5, ($positions === [] ? 0 : max($positions)) + 1);
    foreach ($depthRows as [$playerId]) {
        $positions[(int) $playerId] = $nextRank;
        $nextRank++;
    }

    return $positions;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_compute_tier_b_league_cup_finish(array $standingRows): array
{
    $primaryLeague = amiga_participation_resolve_primary_league_standings($standingRows);
    $koRows = amiga_participation_knockout_or_placement_rows($standingRows);
    $hasCupKnockout = false;
    foreach ($koRows as $row) {
        if ((string) ($row['scope_type'] ?? '') === 'knockout') {
            $hasCupKnockout = true;
            break;
        }
    }
    if ($primaryLeague === [] || !$hasCupKnockout) {
        return [];
    }

    $cupFinish = amiga_participation_compute_tier_a_knockout_finish($standingRows);

    return $cupFinish + $primaryLeague;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @param list<int>|null $playerIds
 * @return array<int, int|null>
 */
/**
 * @param array<int, int> $overrides Tier E rows keyed by player_id
 */
function amiga_participation_apply_finish_overrides(array $finish, array $overrides): array
{
    if ($overrides === []) {
        return $finish;
    }
    foreach ($overrides as $playerId => $position) {
        $finish[(int) $playerId] = (int) $position;
    }

    return $finish;
}

/**
 * @param list<int>|null $playerIds
 * @param array<int, int> $overrides Tier E from amiga_tournament_finish_override
 * @return array<int, int|null>
 */
function amiga_participation_derive_event_finish_position(
    array $standingRows,
    string $tournamentName,
    bool $hasLeague = false,
    bool $hasCup = false,
    ?array $playerIds = null,
    array $overrides = []
): array {
    $primaryLeague = amiga_participation_resolve_primary_league_standings($standingRows);
    if (amiga_tournament_is_world_cup(['name' => $tournamentName])) {
        $finish = [];
    } elseif ($hasLeague && $hasCup && $primaryLeague !== []) {
        $finish = amiga_participation_compute_tier_b_league_cup_finish($standingRows);
    } elseif ($primaryLeague !== []) {
        $finish = $primaryLeague;
    } else {
        $finish = amiga_participation_compute_tier_a_knockout_finish($standingRows);
    }

    $finish = amiga_participation_apply_finish_overrides($finish, $overrides);

    if ($playerIds === null) {
        return $finish;
    }

    $out = [];
    foreach ($playerIds as $playerId) {
        $playerId = (int) $playerId;
        if (isset($overrides[$playerId])) {
            $out[$playerId] = (int) $overrides[$playerId];
        } else {
            $out[$playerId] = $finish[$playerId] ?? null;
        }
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $standingRows
 */
function amiga_participation_derive_best_knockout_phase(array $standingRows, int $playerId): ?string
{
    $bestLabel = null;
    $bestDepth = -1;
    $bestPos = 99;

    foreach ($standingRows as $row) {
        if ((string) ($row['scope_type'] ?? '') !== 'knockout') {
            continue;
        }
        if ((int) $row['player_id'] !== $playerId) {
            continue;
        }
        $label = amiga_participation_knockout_scope_label((string) ($row['scope_key'] ?? ''));
        if (!amiga_participation_is_main_bracket_knockout_label($label)) {
            continue;
        }
        $depth = amiga_participation_knockout_round_depth($label);
        $pos = (int) $row['position'];
        if ($depth > $bestDepth || ($depth === $bestDepth && $pos < $bestPos)) {
            $bestDepth = $depth;
            $bestPos = $pos;
            $bestLabel = $label;
        }
    }

    return $bestLabel;
}

/**
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_resolve_primary_league_standings(array $standingRows): array
{
    $leagueRows = [];
    foreach ($standingRows as $row) {
        if ((string) ($row['scope_type'] ?? '') === 'league') {
            $leagueRows[] = $row;
        }
    }
    if ($leagueRows === []) {
        return [];
    }

    $emptyKeyRows = [];
    foreach ($leagueRows as $row) {
        if ((string) ($row['scope_key'] ?? '') === '') {
            $emptyKeyRows[] = $row;
        }
    }
    if ($emptyKeyRows !== []) {
        $out = [];
        foreach ($emptyKeyRows as $row) {
            $out[(int) $row['player_id']] = (int) $row['position'];
        }

        return $out;
    }

    /** @var array<string, list<array<string, mixed>>> $byKey */
    $byKey = [];
    foreach ($leagueRows as $row) {
        $scopeKey = (string) ($row['scope_key'] ?? '');
        if ($scopeKey === '') {
            continue;
        }
        $byKey[$scopeKey][] = $row;
    }
    if ($byKey === []) {
        return [];
    }

    if (count($byKey) === 1) {
        $onlyKey = array_key_first($byKey);
        $out = [];
        foreach ($byKey[$onlyKey] as $row) {
            $out[(int) $row['player_id']] = (int) $row['position'];
        }

        return $out;
    }

    $chosenKey = null;
    $chosenCount = -1;
    foreach ($byKey as $scopeKey => $rows) {
        $count = count($rows);
        if (
            $chosenKey === null
            || $count > $chosenCount
            || ($count === $chosenCount && $scopeKey < $chosenKey)
        ) {
            $chosenKey = $scopeKey;
            $chosenCount = $count;
        }
    }

    $out = [];
    foreach ($byKey[$chosenKey] as $row) {
        $out[(int) $row['player_id']] = (int) $row['position'];
    }

    return $out;
}

/**
 * @deprecated Use amiga_participation_resolve_primary_league_standings()
 * @param list<array<string, mixed>> $standingRows
 * @return array<int, int>
 */
function amiga_participation_overall_positions(array $standingRows): array
{
    return amiga_participation_resolve_primary_league_standings($standingRows);
}

function amiga_participation_is_winner(
    string $tournamentName,
    ?int $eventFinishPosition = null,
    string $wcMedal = 'none'
): bool {
    if (amiga_tournament_is_world_cup(['name' => $tournamentName])) {
        return $wcMedal === 'gold';
    }

    return $eventFinishPosition === 1;
}
