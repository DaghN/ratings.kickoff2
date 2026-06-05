<?php
/**
 * Amiga DB loader — credentials in ko2amiga_config.local.php (same folder, not in git).
 */
declare(strict_types=1);

$candidates = [
    __DIR__ . '/ko2amiga_config.local.php',
    dirname(__DIR__, 2) . '/config/ko2amiga_config.local.php',
];

$configFile = null;
foreach ($candidates as $path) {
    if (is_file($path)) {
        $configFile = $path;
        break;
    }
}

if ($configFile === null) {
    $hint = 'Copy ko2amiga_config.local.php.example → ko2amiga_config.local.php in this folder.';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Missing Amiga DB config.\n{$hint}\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Amiga database config missing.\n{$hint}\n";
    exit;
}

require $configFile;
