<?php
/**
 * ONE-TIME / THROWAWAY — show where PHP finds DB config (for WinSCP vs Apache layout).
 *
 * Copy to public_html/, open:
 *   …/throwaway_server_paths_probe.php?once=server-paths-probe-one-shot
 * Delete from server after copying the output.
 */
header('Content-Type: text/html; charset=utf-8');

$key = 'server-paths-probe-one-shot';
if (!isset($_GET['once']) || $_GET['once'] !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$configRel = '/../config/ko2unitydb_config.php';
$configPath = $docRoot !== '' ? $docRoot . $configRel : '';
$configReal = ($configPath !== '' && is_file($configPath)) ? realpath($configPath) : false;

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Server paths probe</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem}pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto}</style></head><body>';
echo '<h1>Server paths probe (throwaway)</h1>';
echo '<p><strong>Delete this file after use.</strong></p>';
echo '<h2>Apache / PHP view</h2><pre>';
echo 'DOCUMENT_ROOT = ' . htmlspecialchars($docRoot, ENT_QUOTES, 'UTF-8') . "\n";
echo 'Expected config (relative) = ' . htmlspecialchars($configRel, ENT_QUOTES, 'UTF-8') . "\n";
echo 'Resolved config path = ' . htmlspecialchars($configPath, ENT_QUOTES, 'UTF-8') . "\n";
echo 'realpath(config) = ' . htmlspecialchars($configReal !== false ? $configReal : '(not found)', ENT_QUOTES, 'UTF-8') . "\n";
echo 'config readable = ' . ($configReal !== false && is_readable($configReal) ? 'yes' : 'no') . "\n";
echo 'dirname(config) = ' . htmlspecialchars($configReal !== false ? dirname($configReal) : '(n/a)', ENT_QUOTES, 'UTF-8') . "\n";
echo 'dirname(DOCUMENT_ROOT) = ' . htmlspecialchars($docRoot !== '' ? dirname($docRoot) : '(n/a)', ENT_QUOTES, 'UTF-8') . "\n";
echo '</pre>';

if ($configReal !== false && is_readable($configReal)) {
    include $configPath;
    echo '<h2>DB (from config)</h2><pre>';
    echo 'database = ' . htmlspecialchars($database ?? '(unset)', ENT_QUOTES, 'UTF-8') . "\n";
    echo '</pre>';
}

echo '<h2>What this means for WinSCP</h2><ul>';
echo '<li>PHP can read <code>config/</code> even if your SFTP login cannot list it (chroot/jail).</li>';
echo '<li>Python replay should use the same path; if you cannot upload beside <code>public_html/</code>, upload <code>scripts/ladder/</code> under <code>public_html/scripts/</code> (see <code>docs/STAGING_REPLAY.md</code>).</li>';
echo '</ul></body></html>';
