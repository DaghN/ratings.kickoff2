<?php
/**
 * Amiga staging backup seals — Backup now + Restore (stage → Apply import) (L5 slices 1–2).
 *
 * Open:  /amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot
 * Gate:  admin password only.
 *
 * Restore = copy seal into amiga/_import/ then Apply import (full replace / BA4).
 * Password file: amiga/_ops/amiga_ops_password.local.php ($admin_password).
 */
declare(strict_types=1);

const AMIGA_BACKUP_PAGE_BUILD = 'l5-s2-2026-07-22';

require_once __DIR__ . '/includes/amiga_ops_password_lib.php';
require_once __DIR__ . '/includes/amiga_backup_seal_lib.php';

$key = 'ko2amiga-backup-one-shot';
$onceValue = (string) ($_POST['once'] ?? $_GET['once'] ?? '');
if ($onceValue !== $key) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found.';
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$wantReserve = isset($_POST['reserve']) && (string) $_POST['reserve'] === '1';

$gate = amiga_ops_gate('admin');
if (!$gate['ok']) {
    $self = (string) ($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_backup_ko2amiga.php');
    $hidden = ['once' => $key];
    if ($action !== '') {
        $hidden['action'] = $action;
    }
    if ($wantReserve) {
        $hidden['reserve'] = '1';
    }
    if ($action === 'restore_stage' || $action === 'try_delete') {
        $sid = (string) ($_POST['seal_id'] ?? '');
        if ($sid !== '') {
            $hidden['seal_id'] = $sid;
        }
    }
    amiga_ops_render_password_form(
        $self,
        'Amiga backup seals — admin password',
        'Admin password required (backup / restore / list seals).',
        $hidden,
        $gate['provided'],
        'Admin password'
    );
    exit;
}

$self = (string) ($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_backup_ko2amiga.php');
$flashOk = '';
$flashErr = '';
$stagedSealId = '';

if ($action === 'backup_now') {
    set_time_limit(600);
    ini_set('memory_limit', '512M');
    include __DIR__ . '/../../config/ko2amiga_config.php';
    $dbName = (string) ($database ?? '');
    if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
        $flashErr = 'config database must be ko2amiga_db (staging) or ko2amiga_work (local); got ' . $dbName . '.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
        if ($con->connect_errno) {
            $flashErr = 'connect failed: ' . $con->connect_error;
        } else {
            $con->set_charset('utf8mb4');
            $seal = amiga_backup_seal_write_from_config($con, [
                'reason' => 'manual',
                'reserve' => $wantReserve,
            ]);
            mysqli_close($con);
            if ($seal['ok']) {
                $flashOk = 'Backup seal written: ' . (string) $seal['seal_id']
                    . ' (' . (int) $seal['parts'] . ' parts, '
                    . round(((int) $seal['bytes']) / 1048576, 1) . ' MB, '
                    . (string) $seal['elapsed'] . 's'
                    . ($seal['reserve'] ? ', RESERVE' : '')
                    . ').';
            } else {
                $flashErr = 'Backup seal failed: ' . (string) $seal['error'];
            }
        }
    }
} elseif ($action === 'restore_stage') {
    $sealId = (string) ($_POST['seal_id'] ?? '');
    $staged = amiga_backup_seal_stage_for_import($sealId);
    if ($staged['ok']) {
        $stagedSealId = (string) $staged['seal_id'];
        $flashOk = 'Staged seal ' . $stagedSealId . ' into amiga/_import/ ('
            . (int) $staged['parts'] . ' parts). Apply import replaces the live Amiga DB (full replace).';
    } else {
        $flashErr = 'Restore stage failed: ' . (string) $staged['error'];
    }
} elseif ($action === 'try_delete') {
    $sealId = (string) ($_POST['seal_id'] ?? '');
    $del = amiga_backup_seal_try_delete($sealId);
    if ($del['ok']) {
        $flashOk = 'Deleted non-reserve seal: ' . basename($sealId);
    } else {
        $flashErr = $del['error'];
    }
}

$seals = amiga_backup_seal_list();
$marker = amiga_backup_restore_marker_read();
$importApplyUrl = '/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Amiga backup seals</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
echo 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:.35rem .5rem;text-align:left;font-size:.9rem}';
echo '.ok{color:#0a0}.err{color:#a00}.muted{color:#666;font-size:.9rem}';
echo '.box{border:1px solid #ccc;padding:.75rem 1rem;margin:1rem 0;background:#f8f8f8}';
echo 'a.btn,button.btn{display:inline-block;margin:4px 4px 4px 0;padding:6px 12px;background:#444;color:#fff;text-decoration:none;border:0;border-radius:4px;cursor:pointer;font:inherit}';
echo 'a.btn-danger,button.btn-danger{background:#b71c1c}</style></head><body>';
echo '<h1>Amiga backup seals</h1>';
echo '<p class="muted">Build ' . htmlspecialchars(AMIGA_BACKUP_PAGE_BUILD, ENT_QUOTES, 'UTF-8')
    . ' · Packs under <code>amiga/_backups/</code>. Restore stages a seal into <code>_import/</code>, then Apply import '
    . 'replaces <code>ko2amiga_db</code> (same engine as push import — BA4). Reserve seals cannot be erased via PHP (BA6).</p>';

if ($flashOk !== '') {
    echo '<p class="ok">' . htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($flashErr !== '') {
    echo '<p class="err">' . htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') . '</p>';
}

if ($stagedSealId !== '') {
    echo '<div class="box">';
    echo '<p><strong>Next:</strong> Apply the staged pack to replace the database.</p>';
    echo '<form method="post" action="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="ko2amiga-import-one-shot">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="part" value="1">';
    echo '<button class="btn btn-danger" type="submit">Apply import now (part 1…)</button></form>';
    echo '<p class="muted">Or preview first: <a href="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">Import preview</a></p>';
    echo '</div>';
} elseif (is_array($marker) && !empty($marker['seal_id'])) {
    echo '<div class="box">';
    echo '<p><strong>Staged for restore:</strong> <code>'
        . htmlspecialchars((string) $marker['seal_id'], ENT_QUOTES, 'UTF-8') . '</code>';
    if (!empty($marker['staged_at'])) {
        echo ' · ' . htmlspecialchars((string) $marker['staged_at'], ENT_QUOTES, 'UTF-8');
    }
    echo '</p>';
    echo '<form method="post" action="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="ko2amiga-import-one-shot">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="part" value="1">';
    echo '<button class="btn btn-danger" type="submit">Apply import now</button></form>';
    echo '</div>';
}

echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="action" value="backup_now">';
echo '<p><label><input type="checkbox" name="reserve" value="1"> Mark as <strong>reserve</strong> (not swept by rolling cleaner)</label></p>';
echo '<p><button class="btn" type="submit">Backup now</button></p>';
echo '</form>';

echo '<p class="muted">Rolling keep = ' . (int) AMIGA_BACKUP_ROLLING_KEEP
    . ' non-reserve · auto-reserve every ' . (int) AMIGA_BACKUP_RESERVE_EVERY . 'th seal.</p>';

echo '<h2>Seals on disk</h2>';
if ($seals === []) {
    echo '<p>No seals yet.</p>';
} else {
    echo '<table><thead><tr><th>Id</th><th>Created</th><th>Reason</th><th>Parts</th><th>MB</th><th>Reserve</th><th></th></tr></thead><tbody>';
    foreach (array_reverse($seals) as $seal) {
        $meta = $seal['meta'];
        $id = (string) $seal['id'];
        $isReserve = !empty($meta['reserve']);
        $parts = (int) ($meta['part_count'] ?? (is_array($meta['parts'] ?? null) ? count($meta['parts']) : 0));
        $mb = isset($meta['bytes']) ? round(((int) $meta['bytes']) / 1048576, 1) : 0;
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '</code></td>';
        echo '<td>' . htmlspecialchars((string) ($meta['created'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($meta['reason'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $parts . '</td>';
        echo '<td>' . $mb . '</td>';
        echo '<td>' . ($isReserve ? 'yes' : 'no') . '</td>';
        echo '<td style="white-space:nowrap">';
        echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" style="display:inline" '
            . 'onsubmit="return confirm(\'Stage this seal into amiga/_import/? This overwrites the current import pack. You must then Apply import to replace the DB.\');">';
        echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="action" value="restore_stage">';
        echo '<input type="hidden" name="seal_id" value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button class="btn" type="submit">Restore…</button></form> ';
        if ($isReserve) {
            echo '<span class="muted">PHP delete refused</span>';
        } else {
            echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" style="display:inline" onsubmit="return confirm(\'Delete non-reserve seal?\');">';
            echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
            echo '<input type="hidden" name="action" value="try_delete">';
            echo '<input type="hidden" name="seal_id" value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
            echo '<button type="submit">Delete</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}

echo '<p class="muted"><a href="/amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot">Export (pull)</a> · ';
echo '<a href="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">Import (Apply)</a></p>';
echo '</body></html>';