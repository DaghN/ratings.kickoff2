<?php
/**
 * KOA player naming + live create + orphan cleanup (Amiga organizer).
 *
 * @see docs/amiga-player-create-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_country_registry.php';

const K2_AMIGA_PLAYER_SOURCE_IMPORT = 'import';
const K2_AMIGA_PLAYER_SOURCE_LIVE_OPS = 'live_ops';

function k2_amiga_normalize_display_name(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

    return rtrim($s, '.');
}

function k2_amiga_identity_key(string $raw): string
{
    return mb_strtolower(k2_amiga_normalize_display_name($raw), 'UTF-8');
}

/**
 * @return array{0:string,1:string}|null
 */
function k2_amiga_split_full_name(string $raw): ?array
{
    $normalized = k2_amiga_normalize_display_name($raw);
    if ($normalized === '') {
        return null;
    }
    $tokens = preg_split('/\s+/u', $normalized) ?: [];
    if (count($tokens) < 2) {
        return null;
    }

    return [$tokens[0], $tokens[count($tokens) - 1]];
}

/**
 * @return list<string>
 */
function k2_amiga_koa_abbreviation_candidates(string $firstName, string $surname): array
{
    $firstName = trim($firstName);
    $surname = trim($surname);
    if ($firstName === '' || $surname === '') {
        return [];
    }
    $len = mb_strlen($surname, 'UTF-8');
    $out = [];
    for ($i = 1; $i <= $len; $i++) {
        $out[] = $firstName . ' ' . mb_substr($surname, 0, $i, 'UTF-8');
    }

    return $out;
}

/**
 * @return array<string, true>
 */
function k2_amiga_load_taken_identity_keys(mysqli $con): array
{
    $taken = [];
    $res = mysqli_query($con, 'SELECT name FROM amiga_players');
    if ($res === false) {
        throw new RuntimeException('load player names: ' . mysqli_error($con));
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $taken[k2_amiga_identity_key((string) $row['name'])] = true;
    }
    mysqli_free_result($res);

    return $taken;
}

/**
 * @return array{available:bool,suggested_name:?string,normalized_input:string,reason:?string}
 */
function k2_amiga_suggest_koa_display_name(mysqli $con, string $fullName): array
{
    $normalized = k2_amiga_normalize_display_name($fullName);
    if ($normalized === '') {
        return [
            'available' => false,
            'suggested_name' => null,
            'normalized_input' => $normalized,
            'reason' => 'empty name',
        ];
    }

    $taken = k2_amiga_load_taken_identity_keys($con);
    $tokens = preg_split('/\s+/u', $normalized) ?: [];
    if (count($tokens) === 2 && mb_strlen($tokens[1], 'UTF-8') <= 3) {
        $key = k2_amiga_identity_key($normalized);
        if (!isset($taken[$key])) {
            return [
                'available' => true,
                'suggested_name' => $normalized,
                'normalized_input' => $normalized,
                'reason' => null,
            ];
        }

        return [
            'available' => false,
            'suggested_name' => null,
            'normalized_input' => $normalized,
            'reason' => 'canonical-style name already taken: ' . $normalized,
        ];
    }

    $parts = k2_amiga_split_full_name($normalized);
    if ($parts === null) {
        return [
            'available' => false,
            'suggested_name' => null,
            'normalized_input' => $normalized,
            'reason' => 'need at least first name and surname to suggest a KOA abbreviation',
        ];
    }

    [$first, $surname] = $parts;
    foreach (k2_amiga_koa_abbreviation_candidates($first, $surname) as $candidate) {
        if (!isset($taken[k2_amiga_identity_key($candidate)])) {
            return [
                'available' => true,
                'suggested_name' => $candidate,
                'normalized_input' => $normalized,
                'reason' => null,
            ];
        }
    }

    return [
        'available' => false,
        'suggested_name' => null,
        'normalized_input' => $normalized,
        'reason' => 'all KOA abbreviation candidates for this name are already taken',
    ];
}

function k2_amiga_player_count_games(mysqli $con, int $playerId): int
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_games WHERE player_a_id = ? OR player_b_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare game count: ' . $con->error);
    }
    $stmt->bind_param('ii', $playerId, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute game count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

function k2_amiga_player_count_entrant_links_excluding(
    mysqli $con,
    int $playerId,
    ?int $excludingTournamentId
): int {
    if ($excludingTournamentId === null) {
        $stmt = $con->prepare('SELECT COUNT(*) AS n FROM tournament_entrants WHERE player_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant count: ' . $con->error);
        }
        $stmt->bind_param('i', $playerId);
    } else {
        $stmt = $con->prepare(
            'SELECT COUNT(*) AS n FROM tournament_entrants WHERE player_id = ? AND tournament_id <> ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare entrant count excl: ' . $con->error);
        }
        $stmt->bind_param('ii', $playerId, $excludingTournamentId);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entrant count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

function k2_amiga_player_orphan_deletable(
    mysqli $con,
    int $playerId,
    ?int $excludingTournamentId = null
): bool {
    $stmt = $con->prepare(
        'SELECT player_source FROM amiga_players WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player source: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute player source: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return false;
    }
    if ((string) ($row['player_source'] ?? K2_AMIGA_PLAYER_SOURCE_IMPORT) !== K2_AMIGA_PLAYER_SOURCE_LIVE_OPS) {
        return false;
    }
    if (k2_amiga_player_count_games($con, $playerId) > 0) {
        return false;
    }
    if (k2_amiga_player_count_entrant_links_excluding($con, $playerId, $excludingTournamentId) > 0) {
        return false;
    }

    return true;
}

function k2_amiga_player_try_delete_orphan(
    mysqli $con,
    int $playerId,
    ?int $excludingTournamentId = null
): bool {
    if (!k2_amiga_player_orphan_deletable($con, $playerId, $excludingTournamentId)) {
        return false;
    }
    $stmt = $con->prepare(
        'DELETE FROM amiga_players WHERE id = ? AND player_source = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare orphan delete: ' . $con->error);
    }
    $source = K2_AMIGA_PLAYER_SOURCE_LIVE_OPS;
    $stmt->bind_param('is', $playerId, $source);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute orphan delete: ' . $stmt->error);
    }
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    return $deleted;
}

/**
 * @return array{player_id:int,name:string}
 */
function k2_amiga_player_create_live(mysqli $con, string $fullName, string $countryOfficial): array
{
    if (!k2_amiga_country_validate_token($countryOfficial)) {
        throw new RuntimeException('Choose a valid country from the list.');
    }
    $suggestion = k2_amiga_suggest_koa_display_name($con, $fullName);
    if (!$suggestion['available'] || $suggestion['suggested_name'] === null) {
        $reason = $suggestion['reason'] ?? 'Could not suggest a KOA display name.';
        throw new RuntimeException($reason);
    }
    $name = $suggestion['suggested_name'];
    $country = trim($countryOfficial);
    $source = K2_AMIGA_PLAYER_SOURCE_LIVE_OPS;
    $stmt = $con->prepare(
        'INSERT INTO amiga_players (name, country, display, player_source) VALUES (?, ?, 1, ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare player insert: ' . $con->error);
    }
    $stmt->bind_param('sss', $name, $country, $source);
    if (!$stmt->execute()) {
        if ($con->errno === 1062) {
            throw new RuntimeException('That display name was just taken — try again or adjust the full name.');
        }
        throw new RuntimeException('execute player insert: ' . $stmt->error);
    }
    $playerId = (int) $stmt->insert_id;
    $stmt->close();

    return ['player_id' => $playerId, 'name' => $name];
}
