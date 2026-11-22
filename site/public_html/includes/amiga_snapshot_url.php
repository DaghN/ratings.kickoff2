<?php
/**
 * Amiga time travel — append active `as=` to internal URLs.
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_as_with_url.php';
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';

/** Active cutoff for links and forms: loaded context first, else the request. */
function amiga_snapshot_carry_as_param(?AmigaSnapshotContext $ctx = null): ?string
{
    $ctx ??= amiga_snapshot_context_peek();
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        return $ctx->asParam();
    }

    return amiga_snapshot_propagate_as_param();
}

/**
 * Hidden `as` / `as_with` inputs for GET filter forms. Empty string in present day.
 *
 * A GET submit replaces the query string of `action`, so amiga_url_with_context() in `action` is not enough.
 */
function amiga_form_context_inputs(?AmigaSnapshotContext $ctx = null): string
{
    /** @var array<string, scalar|null> $query */
    $query = [];
    $asParam = amiga_snapshot_carry_as_param($ctx);
    if ($asParam !== null) {
        $query['as'] = $asParam;
    }

    $html = '';
    foreach (amiga_as_with_append_to_query($query) as $name => $value) {
        $html .= '<input type="hidden" name="' . k2_h($name) . '" value="' . k2_h((string) $value) . '" />';
    }

    return $html;
}

/**
 * @param array<string, scalar|null> $extraQuery
 */
function amiga_url_with_context(string $path, array $extraQuery = [], ?AmigaSnapshotContext $ctx = null): string
{
    $hash = '';
    $hashPos = strpos($path, '#');
    if ($hashPos !== false) {
        $hash = substr($path, $hashPos);
        $path = substr($path, 0, $hashPos);
    }

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

    $asParam = amiga_snapshot_carry_as_param($ctx);
    if ($asParam !== null) {
        $query['as'] = $asParam;
        unset($query['wing'], $query['at']);
    }

    $query = amiga_as_with_append_to_query($query);

    if ($query === []) {
        return $pathPart . $hash;
    }

    return $pathPart . '?' . http_build_query($query) . $hash;
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

/**
 * When building a URL for the current page path, carry stable query params (`id`, filters, …).
 * Time-travel keys are always stripped; table sort is merged when paths match.
 *
 * @param array<string, scalar|null> $query
 * @return array<string, scalar|null>
 */
function amiga_snapshot_merge_request_query_for_path(string $targetPath, array $query): array
{
    $targetPathOnly = k2_table_path_only($targetPath);
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (is_string($currentPath) && $currentPath !== '' && $targetPathOnly === $currentPath) {
        /** @var array<string, scalar|null> $carry */
        $carry = [];
        foreach ($_GET as $name => $value) {
            if (!is_string($name) || $name === '' || is_array($value)) {
                continue;
            }
            if (in_array($name, AMIGA_SNAPSHOT_PRESENT_STRIP_QUERY_KEYS, true)) {
                continue;
            }
            $carry[$name] = $value;
        }
        $query = array_merge($carry, $query);
    }

    unset($query['as'], $query['wing'], $query['at']);

    return k2_table_merge_sort_query_for_path($targetPath, $query);
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

/** Params stripped when exiting time travel to present on the same path. */
const AMIGA_SNAPSHOT_PRESENT_STRIP_QUERY_KEYS = [
    'as',
    'wing',
    'at',
    'as_with',
    'id_with',
    'id_country',
    'start_with',
    'k2_tt_entry',
];

/**
 * @param array<string, scalar|null> $query
 * @return array<string, scalar|null>
 */
function amiga_snapshot_strip_present_query_keys(array $query): array
{
    foreach (AMIGA_SNAPSHOT_PRESENT_STRIP_QUERY_KEYS as $key) {
        unset($query[$key]);
    }

    return $query;
}

/** Exit time travel — same path without snapshot or with-player lens params. */
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

    unset($query['as'], $query['wing'], $query['at'], $query['as_with']);

    $query = amiga_snapshot_merge_request_query_for_path($pathPart, $query);
    $query = amiga_snapshot_strip_present_query_keys($query);

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

    $query = amiga_snapshot_merge_request_query_for_path($pathPart, $query);

    $query['as'] = $asParam;
    unset($query['wing'], $query['at']);
    $query = amiga_as_with_append_to_query($query);

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

/**
 * Ribbon navigation href for wing chevrons, picker, and wing tabs.
 */
function amiga_snapshot_chrome_nav_href(string $path, string $asParam, string $wing): string
{
    return amiga_url_with_as_param($path, $asParam);
}
