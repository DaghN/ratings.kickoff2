<?php
/**
 * 1-second shared cache bucket for Status room pulse signals.
 */
declare(strict_types=1);

function k2_status_pulse_cache_key(string $name): string
{
    return 'status_room_pulse:' . $name . ':v1:' . (string) (int) floor(time());
}

/** @return array<string, mixed> */
function &k2_status_pulse_request_cache(): array
{
    static $requestCache = [];

    return $requestCache;
}

/** @template T */
function k2_status_pulse_cache_remember(string $name, callable $builder): mixed
{
    $key = k2_status_pulse_cache_key($name);

    if (function_exists('apcu_fetch')) {
        $success = false;
        $cached = apcu_fetch($key, $success);
        if ($success) {
            return $cached;
        }
        $value = $builder();
        apcu_store($key, $value, 2);

        return $value;
    }

    $requestCache = &k2_status_pulse_request_cache();
    if (array_key_exists($key, $requestCache)) {
        return $requestCache[$key];
    }

    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'k2_status_pulse_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['exp'], $decoded['payload']) && (int) $decoded['exp'] >= time()) {
                $requestCache[$key] = $decoded['payload'];

                return $decoded['payload'];
            }
        }
    }

    $value = $builder();
    $requestCache[$key] = $value;
    @file_put_contents($file, json_encode([
        'exp' => time() + 1,
        'payload' => $value,
    ], JSON_UNESCAPED_UNICODE));

    return $value;
}

/** Drop cached pulse signals so the next Status poll sees fresh lobby/live rows. */
function k2_status_pulse_cache_invalidate(string $name = 'signals'): void
{
    $prefix = 'status_room_pulse:' . $name . ':v1:';
    for ($t = time() - 1; $t <= time() + 1; $t++) {
        $key = $prefix . (string) (int) floor($t);
        if (function_exists('apcu_delete')) {
            @apcu_delete($key);
        }
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'k2_status_pulse_cache';
        $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    $requestCache = &k2_status_pulse_request_cache();
    foreach (array_keys($requestCache) as $key) {
        if (str_starts_with($key, $prefix)) {
            unset($requestCache[$key]);
        }
    }
}
