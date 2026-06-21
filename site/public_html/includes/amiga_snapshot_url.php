<?php
/**
 * Amiga time travel — append active `as=` to internal URLs.
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/k2_table_helpers.php';

/**
 * @param array<string, scalar|null> $extraQuery
 */
function amiga_url_with_context(string $path, array $extraQuery = [], ?AmigaSnapshotContext $ctx = null): string
{
    $pathPart = $path;
    /** @var array<string, scalar|null> $query */
    $query = [];

    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $pathPart = substr($path, 0, $qPos);
        /** @var array<string, scalar|null> $existing */
        $existing = [];
        parse_str(substr($path, $qPos + 1), $existing);
        $query = $existing;
    }

    foreach ($extraQuery as $name => $value) {
        if ($value === null) {
            unset($query[$name]);
            continue;
        }
        $query[$name] = $value;
    }

    $ctx ??= amiga_snapshot_context_peek();
    $asParam = null;
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $asParam = $ctx->asParam();
    } else {
        $asParam = amiga_snapshot_propagate_as_param();
    }
    if ($asParam !== null) {
        $query['as'] = $asParam;
        unset($query['wing'], $query['at']);
    }

    if ($query === []) {
        return $pathPart;
    }

    return $pathPart . '?' . http_build_query($query);
}

/** Current request path for time-travel link building (honours $k2AmigaSnapshotChromePath). */
function amiga_snapshot_request_path(): string
{
    global $k2AmigaSnapshotChromePath;
    if (isset($k2AmigaSnapshotChromePath) && is_string($k2AmigaSnapshotChromePath) && $k2AmigaSnapshotChromePath !== '') {
        return $k2AmigaSnapshotChromePath;
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/amiga/leaderboards/rating.php';
}

/** Whether `as=` (or resolvable legacy wing/at) is active on this request. */
function amiga_snapshot_time_travel_active_from_request(): bool
{
    if (isset($_GET['as'])) {
        $as = trim((string) $_GET['as']);

        return $as !== '' && amiga_snapshot_parse_as_param($as) !== null;
    }

    if (isset($_GET['wing'], $_GET['at'])) {
        return trim((string) $_GET['at']) !== '';
    }

    $ctx = amiga_snapshot_context_peek();

    return $ctx instanceof AmigaSnapshotContext && $ctx->isActive();
}

/** Exit time travel — same path without `as`, `wing`, or `at`. */
function amiga_url_present(string $path, array $extraQuery = []): string
{
    $pathPart = $path;
    /** @var array<string, scalar|null> $query */
    $query = [];

    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $pathPart = substr($path, 0, $qPos);
        /** @var array<string, scalar|null> $existing */
        $existing = [];
        parse_str(substr($path, $qPos + 1), $existing);
        $query = $existing;
    }

    foreach ($extraQuery as $name => $value) {
        if ($value === null) {
            unset($query[$name]);
            continue;
        }
        $query[$name] = $value;
    }

    unset($query['as'], $query['wing'], $query['at']);

    $query = k2_table_merge_sort_query_for_path($pathPart, $query);

    if ($query === []) {
        return $pathPart;
    }

    return $pathPart . '?' . http_build_query($query);
}

/**
 * Navigate to the same page at a snapshot (`as=wing:key`).
 *
 * @param array<string, scalar|null> $extraQuery
 */
function amiga_url_with_as_param(string $path, string $asParam, array $extraQuery = []): string
{
    $pathPart = $path;
    /** @var array<string, scalar|null> $query */
    $query = [];

    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $pathPart = substr($path, 0, $qPos);
        /** @var array<string, scalar|null> $existing */
        $existing = [];
        parse_str(substr($path, $qPos + 1), $existing);
        $query = $existing;
    }

    foreach ($extraQuery as $name => $value) {
        if ($value === null) {
            unset($query[$name]);
            continue;
        }
        $query[$name] = $value;
    }

    $query['as'] = $asParam;
    unset($query['wing'], $query['at']);

    $query = k2_table_merge_sort_query_for_path($pathPart, $query);

    return $pathPart . '?' . http_build_query($query);
}

/**
 * Build a time-travel URL for a wing catalog step (chevrons / picker / wing tabs).
 *
 * @param array<string, scalar|null> $extraQuery
 */
function amiga_url_with_as(string $path, string $wing, string $key, array $extraQuery = []): string
{
    return amiga_url_with_as_param($path, amiga_snapshot_format_as_param($wing, $key), $extraQuery);
}
