<?php
/**
 * Amiga country registry — JSON loader + lookup helpers.
 *
 * @see docs/amiga-country-registry-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/** Sitewide shorthand display off until explicitly enabled (CR18). */
const K2_AMIGA_COUNTRY_SHORTHAND_DISPLAY = false;

function k2_amiga_country_registry_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $candidates = [
        __DIR__ . '/../data/amiga/country_registry.json',
        __DIR__ . '/../../../data/amiga/country_registry.json',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;

            return $path;
        }
    }
    $path = $candidates[0];

    return $path;
}

/**
 * @return array<string, mixed>
 */
function k2_amiga_country_registry(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $path = k2_amiga_country_registry_path();
    if (!is_file($path)) {
        throw new RuntimeException('Country registry missing: ' . $path);
    }
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Country registry unreadable: ' . $path);
    }
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['countries']) || !is_array($data['countries'])) {
        throw new RuntimeException('Country registry invalid JSON: ' . $path);
    }
    $cache = $data;

    return $cache;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_amiga_country_registry_rows(): array
{
    $rows = k2_amiga_country_registry()['countries'];

    return array_values(array_filter($rows, 'is_array'));
}

/**
 * @return array<string, array<string, mixed>>
 */
function k2_amiga_country_registry_by_official(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    foreach (k2_amiga_country_registry_rows() as $row) {
        $name = trim((string) ($row['official_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $map[$name] = $row;
    }

    return $map;
}

/**
 * @return array<string, string> alias -> official_name
 */
function k2_amiga_country_registry_alias_map(): array
{
    static $aliases = null;
    if ($aliases !== null) {
        return $aliases;
    }
    $aliases = [];
    foreach (k2_amiga_country_registry_rows() as $row) {
        $official = trim((string) ($row['official_name'] ?? ''));
        if ($official === '') {
            continue;
        }
        foreach ($row['legacy_aliases'] ?? [] as $alias) {
            $key = trim((string) $alias);
            if ($key !== '') {
                $aliases[$key] = $official;
            }
        }
    }

    return $aliases;
}

/**
 * @return array<string, mixed>|null
 */
function k2_amiga_country_resolve(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }
    $byOfficial = k2_amiga_country_registry_by_official();
    if (isset($byOfficial[$token])) {
        return $byOfficial[$token];
    }
    $aliases = k2_amiga_country_registry_alias_map();
    if (isset($aliases[$token])) {
        return $byOfficial[$aliases[$token]] ?? null;
    }

    return null;
}

function k2_amiga_country_display_name(string $token, ?bool $useShorthand = null): string
{
    $token = trim($token);
    if ($token === '') {
        return '';
    }
    $row = k2_amiga_country_resolve($token);
    if ($row === null) {
        return $token;
    }
    $official = trim((string) ($row['official_name'] ?? $token));
    $useShort = $useShorthand ?? K2_AMIGA_COUNTRY_SHORTHAND_DISPLAY;
    if ($useShort) {
        $short = trim((string) ($row['site_shorthand'] ?? ''));
        if ($short !== '') {
            return $short;
        }
    }

    return $official;
}

function k2_amiga_country_validate_token(string $token): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }
    $row = k2_amiga_country_registry_by_official()[$token] ?? null;
    if (!is_array($row)) {
        return false;
    }

    return !empty($row['choosable']);
}

function k2_amiga_country_validate_official(string $token): bool
{
    $token = trim($token);
    if ($token === '') {
        return false;
    }

    return isset(k2_amiga_country_registry_by_official()[$token]);
}

/**
 * @return list<array<string, mixed>>
 */
function k2_amiga_country_choosable_rows(): array
{
    $rows = [];
    foreach (k2_amiga_country_registry_rows() as $row) {
        if (!empty($row['choosable'])) {
            $rows[] = $row;
        }
    }
    usort($rows, static fn (array $a, array $b): int => strcasecmp(
        (string) ($a['official_name'] ?? ''),
        (string) ($b['official_name'] ?? '')
    ));

    return $rows;
}

/**
 * @return list<string>
 */
function k2_amiga_country_used_tokens(mysqli $con): array
{
    $sql = <<<'SQL'
SELECT token FROM (
    SELECT TRIM(country) AS token FROM amiga_players WHERE TRIM(country) <> ''
    UNION
    SELECT TRIM(country) AS token FROM tournaments WHERE TRIM(country) <> ''
) used
ORDER BY token ASC
SQL;
    $result = $con->query($sql);
    if ($result === false) {
        throw new RuntimeException('k2_amiga_country_used_tokens: ' . $con->error);
    }
    $tokens = [];
    while ($row = $result->fetch_assoc()) {
        $tokens[] = (string) ($row['token'] ?? '');
    }
    $result->free();

    return $tokens;
}

/**
 * @return array<string, string> official_name -> flag_code for JS boot maps
 */
function k2_amiga_country_flag_code_map(): array
{
    $out = [];
    foreach (k2_amiga_country_registry_rows() as $row) {
        $name = trim((string) ($row['official_name'] ?? ''));
        $code = trim((string) ($row['flag_code'] ?? ''));
        if ($name !== '' && $code !== '') {
            $out[$name] = $code;
        }
    }
    foreach (k2_amiga_country_registry_alias_map() as $alias => $official) {
        if (isset($out[$official])) {
            $out[$alias] = $out[$official];
        }
    }

    return $out;
}