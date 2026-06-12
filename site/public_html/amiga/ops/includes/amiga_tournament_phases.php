<?php
/**
 * Phase label → standings scope (parity with scripts/amiga/tournament_phases.py).
 */
declare(strict_types=1);

const AMIGA_SCOPE_TYPE_LEAGUE = 'league';
const AMIGA_SCOPE_TYPE_KNOCKOUT = 'knockout';

/** @var list<string> */
const AMIGA_KNOCKOUT_LABELS = [
    'quarter finals',
    'semi finals',
    'final',
    '3rd place final',
    '5th place final',
    '7th place final',
    '9th place final',
    '11th place final',
    '13th place final',
    '15th place final',
];

function amiga_ops_normalize_whitespace(string $label): string
{
    return (string) preg_replace('/\s+/', ' ', trim($label));
}

function amiga_ops_is_knockout_phase(?string $phase): bool
{
    if ($phase === null || trim($phase) === '') {
        return false;
    }
    $label = amiga_ops_normalize_whitespace($phase);
    if (in_array(strtolower($label), AMIGA_KNOCKOUT_LABELS, true)) {
        return true;
    }
    if (preg_match('/^Places\s+\d+(?:-\d+)?$/i', $label) === 1) {
        return true;
    }
    if (preg_match('/^\d+(?:st|nd|rd|th)\s+Place\s+Final$/i', $label) === 1) {
        return true;
    }

    return false;
}

function amiga_ops_knockout_pair_scope_key(string $phase, int $playerAId, int $playerBId): string
{
    $phase = amiga_ops_normalize_whitespace($phase);
    $lo = min($playerAId, $playerBId);
    $hi = max($playerAId, $playerBId);

    return $phase . '|' . $lo . '-' . $hi;
}

function amiga_ops_canonical_group_key(?string $prefix, string $group): string
{
    $group = strtoupper(str_replace(' ', '', $group));
    if ($prefix !== null && $prefix !== '') {
        $prefixNorm = amiga_ops_normalize_whitespace($prefix);

        return $prefixNorm . ' - Group ' . $group;
    }

    return 'Group ' . $group;
}

/**
 * @return array{scope_type: string, scope_key: string}
 */
function amiga_ops_parse_phase(?string $phase): array
{
    if ($phase === null || trim($phase) === '') {
        return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => ''];
    }

    $label = amiga_ops_normalize_whitespace($phase);

    if (amiga_ops_is_knockout_phase($label)) {
        return ['scope_type' => AMIGA_SCOPE_TYPE_KNOCKOUT, 'scope_key' => $label];
    }

    if (preg_match(
        '/^KOA\s+Cup\s*-\s*(?P<round>Round\s+\d+)\s*-\s*Group\s+(?P<group>[A-Z](?:\/[A-Z])?)$/i',
        $label,
        $mKoa
    ) === 1) {
        $prefix = 'KOA Cup - ' . amiga_ops_normalize_whitespace($mKoa['round']);

        return [
            'scope_type' => AMIGA_SCOPE_TYPE_LEAGUE,
            'scope_key' => amiga_ops_canonical_group_key($prefix, $mKoa['group']),
        ];
    }

    if (preg_match(
        '/^(?:(?P<prefix>Round\s+\d+|Silver\s+Cup|Bronze\s+Cup|KOA\s+Cup)\s*[-]?\s*)?Group\s+(?P<group>[A-Z](?:\/[A-Z])?)$/i',
        $label,
        $m
    ) === 1) {
        return [
            'scope_type' => AMIGA_SCOPE_TYPE_LEAGUE,
            'scope_key' => amiga_ops_canonical_group_key($m['prefix'] ?? null, $m['group']),
        ];
    }

    if (preg_match(
        '/^(?P<prefix>Round\s+\d+|Silver\s+Cup|Bronze\s+Cup|KOA\s+Cup)\s*-\s*Group\s+(?P<group>[A-Z](?:\/[A-Z])?)$/i',
        $label,
        $m2
    ) === 1) {
        return [
            'scope_type' => AMIGA_SCOPE_TYPE_LEAGUE,
            'scope_key' => amiga_ops_canonical_group_key($m2['prefix'], $m2['group']),
        ];
    }

    return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => $label];
}

/**
 * @param array{scope_type: string, scope_key: string} $scope
 */
function amiga_ops_is_league_scope(array $scope): bool
{
    return $scope['scope_type'] === AMIGA_SCOPE_TYPE_LEAGUE;
}
