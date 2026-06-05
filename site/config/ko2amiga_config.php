<?php
/**
 * Amiga realm DB config loader (committed router).
 *
 * Credentials: ko2amiga_config.local.php (gitignored).
 * Used by site/public_html/amiga/*.php — not the default online site.
 */
declare(strict_types=1);

$configFile = __DIR__ . '/ko2amiga_config.local.php';

if (!is_file($configFile)) {
    $hint = 'Copy ko2amiga_config.local.php.example → ko2amiga_config.local.php';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Missing DB config: {$configFile}\n{$hint}\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Amiga database config missing.\n{$hint}\n";
    exit;
}

require $configFile;
