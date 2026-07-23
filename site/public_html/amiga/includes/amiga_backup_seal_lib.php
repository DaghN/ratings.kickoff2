<?php
/**
 * Amiga staging backup seals (L5) — Apply-import family packs under amiga/_backups/.
 *
 * BA1–BA6: full manifest+parts after tip actions; rolling retention; reserve not PHP-deletable.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_staging_export_lib.php';

const AMIGA_BACKUP_SEAL_BUILD = 'l5-export-json-2026-07-23';
const AMIGA_BACKUP_ROLLING_KEEP = 8;
const AMIGA_BACKUP_RESERVE_EVERY = 5;
const AMIGA_BACKUP_GAMES_CHUNK = 5000;

/**
 * Part filename slug from registry table name (strip leading amiga_).
 */
function amiga_backup_seal_table_part_slug(string $table): string
{
    if (str_starts_with($table, 'amiga_')) {
        return substr($table, strlen('amiga_'));
    }

    return $table;
}

function amiga_backup_seals_root(): string
{
    return dirname(__DIR__) . '/_backups';
}

function amiga_backup_import_dir(): string
{
    return dirname(__DIR__) . '/_import';
}

function amiga_backup_restore_marker_path(): string
{
    return amiga_backup_import_dir() . '/.restore_from_seal.json';
}

/**
 * @return array<string, mixed>|null
 */
function amiga_backup_restore_marker_read(): ?array
{
    $path = amiga_backup_restore_marker_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = (string) file_get_contents($path);
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

/**
 * Resolve and validate a seal pack directory for direct BA4 restore (no _import copy).
 *
 * @return array{ok:bool,error:string,seal_id:string,pack_dir:string,parts:list<string>}
 */
function amiga_backup_seal_validate_for_restore(string $sealId): array
{
    $fail = static function (string $msg): array {
        return [
            'ok' => false,
            'error' => $msg,
            'seal_id' => '',
            'pack_dir' => '',
            'parts' => [],
        ];
    };

    $sealId = basename($sealId);
    if (!str_starts_with($sealId, 'seal-')) {
        return $fail('Invalid seal id.');
    }
    $sealDir = amiga_backup_seals_root() . DIRECTORY_SEPARATOR . $sealId;
    if (!is_dir($sealDir)) {
        return $fail('Seal not found: ' . $sealId);
    }
    require_once __DIR__ . '/amiga_staging_import_lib.php';
    $parts = k2_amiga_import_manifest_parts_from_dir($sealDir);
    if ($parts === [] || ($parts[0] === 'ko2amiga_db.sql' && !is_file($sealDir . DIRECTORY_SEPARATOR . 'ko2amiga_manifest.json'))) {
        return $fail('Seal missing usable ko2amiga_manifest.json parts list.');
    }
    foreach ($parts as $base) {
        if (!is_file($sealDir . DIRECTORY_SEPARATOR . $base)) {
            return $fail('Seal part missing on disk: ' . $base);
        }
    }

    return [
        'ok' => true,
        'error' => '',
        'seal_id' => $sealId,
        'pack_dir' => $sealDir,
        'parts' => $parts,
    ];
}

/**
 * Copy a seal pack into amiga/_import/ (optional push-tray path).
 * Prefer {@see amiga_backup_seal_validate_for_restore()} + apply-from-seal-dir for BA4 restore —
 * that does not overwrite a pending push payload.
 *
 * @return array{ok:bool,error:string,seal_id:?string,parts:int,import_dir:?string,marker:?string}
 */
function amiga_backup_seal_stage_for_import(string $sealId): array
{
    $fail = static function (string $msg): array {
        return [
            'ok' => false,
            'error' => $msg,
            'seal_id' => null,
            'parts' => 0,
            'import_dir' => null,
            'marker' => null,
        ];
    };

    $sealId = basename($sealId);
    if (!str_starts_with($sealId, 'seal-')) {
        return $fail('Invalid seal id.');
    }
    $sealDir = amiga_backup_seals_root() . DIRECTORY_SEPARATOR . $sealId;
    if (!is_dir($sealDir)) {
        return $fail('Seal not found: ' . $sealId);
    }

    $manifestPath = $sealDir . DIRECTORY_SEPARATOR . 'ko2amiga_manifest.json';
    if (!is_file($manifestPath)) {
        return $fail('Seal missing ko2amiga_manifest.json.');
    }
    $raw = (string) file_get_contents($manifestPath);
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $manifest = json_decode($raw, true);
    if (!is_array($manifest) || empty($manifest['parts']) || !is_array($manifest['parts'])) {
        return $fail('Seal manifest has no parts list.');
    }

    $parts = [];
    foreach ($manifest['parts'] as $part) {
        if (!is_string($part) || $part === '') {
            return $fail('Invalid part name in seal manifest.');
        }
        $base = basename($part);
        if ($base !== $part || !str_starts_with($base, 'ko2amiga_') || !str_ends_with($base, '.sql')) {
            return $fail('Refusing unsafe part name: ' . $part);
        }
        $src = $sealDir . DIRECTORY_SEPARATOR . $base;
        if (!is_file($src)) {
            return $fail('Seal part missing on disk: ' . $base);
        }
        $parts[] = $base;
    }
    if ($parts === []) {
        return $fail('Seal manifest parts list is empty.');
    }

    $importDir = amiga_backup_import_dir();
    if (!is_dir($importDir) && !mkdir($importDir, 0755, true) && !is_dir($importDir)) {
        return $fail('Could not create _import directory.');
    }

    // Copy parts first (temp names), then flip manifest — avoids half-applied pack if copy fails mid-way.
    $copied = [];
    foreach ($parts as $base) {
        $src = $sealDir . DIRECTORY_SEPARATOR . $base;
        $tmp = $importDir . DIRECTORY_SEPARATOR . $base . '.restore-tmp';
        $dst = $importDir . DIRECTORY_SEPARATOR . $base;
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        if (!copy($src, $tmp)) {
            foreach ($copied as $tmpPath) {
                @unlink($tmpPath);
            }
            return $fail('Copy failed for part: ' . $base);
        }
        $copied[] = $tmp;
        if (is_file($dst) && !@unlink($dst)) {
            foreach ($copied as $tmpPath) {
                @unlink($tmpPath);
            }
            return $fail('Could not replace existing import part: ' . $base);
        }
        if (!@rename($tmp, $dst)) {
            foreach ($copied as $tmpPath) {
                @unlink($tmpPath);
            }
            return $fail('Could not place import part: ' . $base);
        }
    }

    $manifestOut = [
        'generated' => (string) ($manifest['generated'] ?? (gmdate('Y-m-d H:i:s') . ' UTC')),
        'source_database' => (string) ($manifest['source_database'] ?? ''),
        'staging_database' => 'ko2amiga_db',
        'parts' => $parts,
        'restored_from_seal' => $sealId,
        'seal_build' => (string) ($manifest['seal_build'] ?? ''),
    ];
    $manifestJson = json_encode($manifestOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($manifestJson === false
        || file_put_contents($importDir . DIRECTORY_SEPARATOR . 'ko2amiga_manifest.json', $manifestJson . "\n") === false
    ) {
        return $fail('Could not write _import/ko2amiga_manifest.json.');
    }

    // Remove stale ko2amiga_*.sql not in this seal (keep optional full dump).
    $keep = array_fill_keys($parts, true);
    $keep['ko2amiga_db.sql'] = true;
    $entries = scandir($importDir);
    if (is_array($entries)) {
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (!str_starts_with($name, 'ko2amiga_') || !str_ends_with($name, '.sql')) {
                continue;
            }
            if (isset($keep[$name])) {
                continue;
            }
            @unlink($importDir . DIRECTORY_SEPARATOR . $name);
        }
    }

    $marker = [
        'seal_id' => $sealId,
        'staged_at' => gmdate('Y-m-d H:i:s') . ' UTC',
        'parts' => count($parts),
        'import_dir' => '_import',
        'next' => 'Open Apply import to replace ko2amiga_db from this staged pack (full replace / BA4).',
    ];
    $markerJson = json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $markerPath = amiga_backup_restore_marker_path();
    if ($markerJson === false || file_put_contents($markerPath, $markerJson . "\n") === false) {
        return $fail('Parts staged but could not write restore marker.');
    }

    return [
        'ok' => true,
        'error' => '',
        'seal_id' => $sealId,
        'parts' => count($parts),
        'import_dir' => $importDir,
        'marker' => $markerPath,
    ];
}

/**
 * @return array{ok:bool,error:string}
 */
function amiga_backup_seals_ensure_root(): array
{
    $root = amiga_backup_seals_root();
    if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
        return ['ok' => false, 'error' => 'Could not create _backups directory.'];
    }
    $ht = $root . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "# Block web access to backup seals (BA6 / filesystem outer key).\nRequire all denied\n");
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * @return list<array{id:string,path:string,meta:array<string,mixed>}>
 */
function amiga_backup_seal_list(): array
{
    $root = amiga_backup_seals_root();
    if (!is_dir($root)) {
        return [];
    }
    $out = [];
    $entries = scandir($root);
    if ($entries === false) {
        return [];
    }
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..' || !str_starts_with($name, 'seal-')) {
            continue;
        }
        $path = $root . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) {
            continue;
        }
        $meta = amiga_backup_seal_read_meta($path);
        $out[] = [
            'id' => $name,
            'path' => $path,
            'meta' => $meta,
        ];
    }
    usort($out, static function (array $a, array $b): int {
        $ta = (string) ($a['meta']['created'] ?? '');
        $tb = (string) ($b['meta']['created'] ?? '');
        if ($ta !== $tb) {
            return strcmp($ta, $tb);
        }
        return strcmp($a['id'], $b['id']);
    });
    return $out;
}

/**
 * @return array<string, mixed>
 */
function amiga_backup_seal_read_meta(string $sealDir): array
{
    $path = $sealDir . DIRECTORY_SEPARATOR . 'seal.json';
    if (!is_file($path)) {
        return [
            'id' => basename($sealDir),
            'reserve' => false,
            'created' => '',
            'reason' => 'unknown',
        ];
    }
    $raw = (string) file_get_contents($path);
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : ['id' => basename($sealDir), 'reserve' => false];
}

/**
 * Refuse PHP delete of reserve seals (BA6). Rolling cleaner only removes non-reserve.
 *
 * @return array{ok:bool,error:string}
 */
function amiga_backup_seal_try_delete(string $sealId): array
{
    $sealId = basename($sealId);
    if (!str_starts_with($sealId, 'seal-')) {
        return ['ok' => false, 'error' => 'Invalid seal id.'];
    }
    $path = amiga_backup_seals_root() . DIRECTORY_SEPARATOR . $sealId;
    if (!is_dir($path)) {
        return ['ok' => false, 'error' => 'Seal not found.'];
    }
    $meta = amiga_backup_seal_read_meta($path);
    if (!empty($meta['reserve'])) {
        return ['ok' => false, 'error' => 'Reserve seals cannot be deleted via PHP (BA6). Use WinSCP/filesystem.'];
    }
    return amiga_backup_seal_rm_tree($path);
}

/**
 * @return array{ok:bool,error:string}
 */
function amiga_backup_seal_rm_tree(string $dir): array
{
    if (!is_dir($dir)) {
        return ['ok' => true, 'error' => ''];
    }
    $items = scandir($dir);
    if ($items === false) {
        return ['ok' => false, 'error' => 'Could not list seal directory.'];
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            $sub = amiga_backup_seal_rm_tree($full);
            if (!$sub['ok']) {
                return $sub;
            }
            continue;
        }
        if (!@unlink($full)) {
            return ['ok' => false, 'error' => 'Could not delete file: ' . $item];
        }
    }
    if (!@rmdir($dir)) {
        return ['ok' => false, 'error' => 'Could not remove seal directory.'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Keep newest AMIGA_BACKUP_ROLLING_KEEP non-reserve seals; never delete reserves.
 *
 * @return array{ok:bool,error:string,removed:list<string>,kept_rolling:int,reserves:int}
 */
function amiga_backup_seal_apply_retention(?int $rollingKeep = null): array
{
    $keep = $rollingKeep ?? AMIGA_BACKUP_ROLLING_KEEP;
    if ($keep < 1) {
        $keep = 1;
    }
    $seals = amiga_backup_seal_list();
    $reserves = 0;
    $rolling = [];
    foreach ($seals as $seal) {
        if (!empty($seal['meta']['reserve'])) {
            $reserves++;
            continue;
        }
        $rolling[] = $seal;
    }
    $removed = [];
    $overflow = count($rolling) - $keep;
    if ($overflow > 0) {
        for ($i = 0; $i < $overflow; $i++) {
            $victim = $rolling[$i];
            $del = amiga_backup_seal_try_delete($victim['id']);
            if (!$del['ok']) {
                return [
                    'ok' => false,
                    'error' => $del['error'],
                    'removed' => $removed,
                    'kept_rolling' => count($rolling) - count($removed),
                    'reserves' => $reserves,
                ];
            }
            $removed[] = $victim['id'];
        }
    }
    return [
        'ok' => true,
        'error' => '',
        'removed' => $removed,
        'kept_rolling' => count($rolling) - count($removed),
        'reserves' => $reserves,
    ];
}

/**
 * @param array{host:string,port:int,user:string,pass:string,database:string} $db
 * @param array{reason?:string,tournament_id?:int|null,reserve?:bool,label?:string} $opts
 * @return array{
 *   ok:bool,
 *   error:string,
 *   seal_id:?string,
 *   path:?string,
 *   reserve:bool,
 *   bytes:int,
 *   parts:int,
 *   elapsed:float,
 *   method:string,
 *   retention:array<string,mixed>
 * }
 */
function amiga_backup_seal_write(mysqli $con, array $db, array $opts = []): array
{
    $started = microtime(true);
    $fail = static function (string $msg) use ($started): array {
        return [
            'ok' => false,
            'error' => $msg,
            'seal_id' => null,
            'path' => null,
            'reserve' => false,
            'bytes' => 0,
            'parts' => 0,
            'elapsed' => round(microtime(true) - $started, 2),
            'method' => '',
            'retention' => [],
        ];
    };

    $ensure = amiga_backup_seals_ensure_root();
    if (!$ensure['ok']) {
        return $fail($ensure['error']);
    }

    $reason = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($opts['reason'] ?? 'manual')) ?? 'manual';
    $reason = trim($reason, '-') ?: 'manual';
    $tournamentId = isset($opts['tournament_id']) ? (int) $opts['tournament_id'] : 0;
    $forceReserve = !empty($opts['reserve']);
    $label = isset($opts['label']) ? (string) $opts['label'] : '';

    $stamp = gmdate('Ymd-His') . 'Z';
    $sealId = 'seal-' . $stamp . '-' . $reason;
    if ($tournamentId > 0) {
        $sealId .= '-' . $tournamentId;
    }
    if ($label !== '') {
        $safeLabel = preg_replace('/[^a-z0-9_-]+/i', '-', $label) ?? '';
        $safeLabel = trim($safeLabel, '-');
        if ($safeLabel !== '') {
            $sealId .= '-' . $safeLabel;
        }
    }

    $existingCount = count(amiga_backup_seal_list());
    $autoReserve = (($existingCount + 1) % AMIGA_BACKUP_RESERVE_EVERY) === 0;
    $reserve = $forceReserve || $autoReserve;

    $root = amiga_backup_seals_root();
    $sealDir = $root . DIRECTORY_SEPARATOR . $sealId;
    if (is_dir($sealDir) || is_file($sealDir)) {
        return $fail('Seal directory already exists: ' . $sealId);
    }
    if (!mkdir($sealDir, 0755, true) && !is_dir($sealDir)) {
        return $fail('Could not create seal directory.');
    }

    try {
        $tables = k2_amiga_export_table_list();
    } catch (Throwable $e) {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail($e->getMessage());
    }

    $host = (string) $db['host'];
    $port = (int) $db['port'];
    $user = (string) $db['user'];
    $pass = (string) $db['pass'];
    $database = (string) $db['database'];

    $parts = [];
    $methods = [];
    $totalBytes = 0;

    $addPart = static function (
        string $fileName,
        array $flags,
        array $partTables,
        bool $schemaOnly = false,
        ?string $whereSql = null
    ) use (
        $con,
        $host,
        $port,
        $user,
        $pass,
        $database,
        $sealDir,
        &$parts,
        &$methods,
        &$totalBytes,
        $fail
    ): ?array {
        $outPath = $sealDir . DIRECTORY_SEPARATOR . $fileName;
        $run = k2_amiga_export_write_part(
            $con,
            $host,
            $port,
            $user,
            $pass,
            $database,
            $outPath,
            $flags,
            $partTables,
            $schemaOnly,
            $whereSql
        );
        if (!$run['ok']) {
            return $fail('Part ' . $fileName . ': ' . $run['error']);
        }
        $parts[] = $fileName;
        $methods[$run['method']] = true;
        $totalBytes += (int) $run['bytes'];
        return null;
    };

    $err = $addPart('ko2amiga_01_schema.sql', ['--no-data'], $tables, true, null);
    if ($err !== null) {
        amiga_backup_seal_rm_tree($sealDir);
        return $err;
    }

    // Data parts follow staging_export_tables.json order (same as Export-Ko2AmigaStaging.ps1).
    // Only special case: chunk amiga_games + amiga_game_ratings. No second hardcoded table list.
    $gameIdx = array_search('amiga_games', $tables, true);
    if ($gameIdx === false) {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail('Export registry missing amiga_games.');
    }
    if (!isset($tables[$gameIdx + 1]) || $tables[$gameIdx + 1] !== 'amiga_game_ratings') {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail('Export registry: amiga_game_ratings must immediately follow amiga_games.');
    }
    $earlyTables = array_slice($tables, 0, (int) $gameIdx);
    $tailTables = array_slice($tables, (int) $gameIdx + 2);
    $dumped = [];

    $idx = 2;
    foreach ($earlyTables as $table) {
        $slug = amiga_backup_seal_table_part_slug($table);
        if ($table === 'tournament_stage_scoring_steps') {
            $fileName = 'ko2amiga_07a_stage_scoring_steps.sql';
        } else {
            $fileName = sprintf('ko2amiga_%02d_%s.sql', $idx, $slug);
            $idx++;
        }
        $err = $addPart($fileName, ['--no-create-info'], [$table], false, null);
        if ($err !== null) {
            amiga_backup_seal_rm_tree($sealDir);
            return $err;
        }
        $dumped[$table] = true;
    }

    $maxId = 0;
    $maxRes = mysqli_query($con, 'SELECT COALESCE(MAX(id), 0) AS m FROM amiga_games');
    if ($maxRes) {
        $row = mysqli_fetch_assoc($maxRes);
        mysqli_free_result($maxRes);
        $maxId = (int) ($row['m'] ?? 0);
    }

    if ($idx < 10) {
        $idx = 10;
    }
    $chunk = AMIGA_BACKUP_GAMES_CHUNK;
    if ($maxId <= 0) {
        $gamesPart = sprintf('ko2amiga_%02d_games_empty.sql', $idx);
        $err = $addPart($gamesPart, ['--no-create-info'], ['amiga_games'], false, null);
        if ($err !== null) {
            amiga_backup_seal_rm_tree($sealDir);
            return $err;
        }
        $idx++;
        $ratingsPart = sprintf('ko2amiga_%02d_ratings_empty.sql', $idx);
        $err = $addPart($ratingsPart, ['--no-create-info'], ['amiga_game_ratings'], false, null);
        if ($err !== null) {
            amiga_backup_seal_rm_tree($sealDir);
            return $err;
        }
        $idx++;
    } else {
        for ($start = 1; $start <= $maxId; $start += $chunk) {
            $end = min($start + $chunk - 1, $maxId);
            $gameWhere = 'id >= ' . $start . ' AND id <= ' . $end;
            $gamesPart = sprintf('ko2amiga_%02d_games_%d_%d.sql', $idx, $start, $end);
            $err = $addPart($gamesPart, ['--no-create-info', '--where=' . $gameWhere], ['amiga_games'], false, $gameWhere);
            if ($err !== null) {
                amiga_backup_seal_rm_tree($sealDir);
                return $err;
            }
            $idx++;

            $ratingWhere = 'game_id >= ' . $start . ' AND game_id <= ' . $end;
            $ratingsPart = sprintf('ko2amiga_%02d_ratings_%d_%d.sql', $idx, $start, $end);
            $err = $addPart($ratingsPart, ['--no-create-info', '--where=' . $ratingWhere], ['amiga_game_ratings'], false, $ratingWhere);
            if ($err !== null) {
                amiga_backup_seal_rm_tree($sealDir);
                return $err;
            }
            $idx++;
        }
    }
    $dumped['amiga_games'] = true;
    $dumped['amiga_game_ratings'] = true;

    foreach ($tailTables as $table) {
        $slug = amiga_backup_seal_table_part_slug($table);
        $fileName = sprintf('ko2amiga_%02d_%s.sql', $idx, $slug);
        $err = $addPart($fileName, ['--no-create-info'], [$table], false, null);
        if ($err !== null) {
            amiga_backup_seal_rm_tree($sealDir);
            return $err;
        }
        $dumped[$table] = true;
        $idx++;
    }

    $missing = [];
    foreach ($tables as $table) {
        if (!isset($dumped[$table])) {
            $missing[] = $table;
        }
    }
    if ($missing !== []) {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail('Seal data parts missing registry table(s): ' . implode(', ', $missing));
    }

    $manifest = [
        'generated' => gmdate('Y-m-d H:i:s') . ' UTC',
        'source_database' => $database,
        'staging_database' => 'ko2amiga_db',
        'parts' => $parts,
        'seal_id' => $sealId,
        'seal_build' => AMIGA_BACKUP_SEAL_BUILD,
    ];
    $manifestPath = $sealDir . DIRECTORY_SEPARATOR . 'ko2amiga_manifest.json';
    $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($manifestJson === false || file_put_contents($manifestPath, $manifestJson . "\n") === false) {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail('Could not write ko2amiga_manifest.json.');
    }

    $method = implode('+', array_keys($methods));
    if ($method === '') {
        $method = 'unknown';
    }

    $meta = [
        'id' => $sealId,
        'created' => gmdate('Y-m-d H:i:s') . ' UTC',
        'reason' => $reason,
        'tournament_id' => $tournamentId > 0 ? $tournamentId : null,
        'reserve' => $reserve,
        'source_database' => $database,
        'parts' => $parts,
        'part_count' => count($parts),
        'bytes' => $totalBytes,
        'method' => $method,
        'elapsed' => round(microtime(true) - $started, 2),
        'seal_build' => AMIGA_BACKUP_SEAL_BUILD,
        'rolling_keep' => AMIGA_BACKUP_ROLLING_KEEP,
        'reserve_every' => AMIGA_BACKUP_RESERVE_EVERY,
    ];
    $metaPath = $sealDir . DIRECTORY_SEPARATOR . 'seal.json';
    $metaJson = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($metaJson === false || file_put_contents($metaPath, $metaJson . "\n") === false) {
        amiga_backup_seal_rm_tree($sealDir);
        return $fail('Could not write seal.json.');
    }

    $retention = amiga_backup_seal_apply_retention();
    if (!$retention['ok']) {
        return [
            'ok' => false,
            'error' => 'Seal written but retention failed: ' . $retention['error'],
            'seal_id' => $sealId,
            'path' => $sealDir,
            'reserve' => $reserve,
            'bytes' => $totalBytes,
            'parts' => count($parts),
            'elapsed' => round(microtime(true) - $started, 2),
            'method' => $method,
            'retention' => $retention,
        ];
    }

    return [
        'ok' => true,
        'error' => '',
        'seal_id' => $sealId,
        'path' => $sealDir,
        'reserve' => $reserve,
        'bytes' => $totalBytes,
        'parts' => count($parts),
        'elapsed' => round(microtime(true) - $started, 2),
        'method' => $method,
        'retention' => $retention,
    ];
}

/**
 * Helper for fixtures / admin pages that already loaded ko2amiga_config globals.
 *
 * @param array{reason?:string,tournament_id?:int|null,reserve?:bool,label?:string} $opts
 * @return array<string, mixed>
 */
function amiga_backup_seal_write_from_config(mysqli $con, array $opts = []): array
{
    global $dbhost, $dbportnum, $username, $password, $database;
    return amiga_backup_seal_write($con, [
        'host' => (string) ($dbhost ?? '127.0.0.1'),
        'port' => (int) ($dbportnum ?? 3306),
        'user' => (string) ($username ?? ''),
        'pass' => (string) ($password ?? ''),
        'database' => (string) ($database ?? ''),
    ], $opts);
}