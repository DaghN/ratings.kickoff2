<?php
/**
 * Amiga staging backup seals — Backup now + Restore + Case A/B admin delete (L5 slices 1–4).
 *
 * Open:  /amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot
 * Gate:  admin password only.
 *
 * Restore = copy seal into amiga/_import/ then Apply import (full replace / BA4).
 * Case A = delete-unfinalized-tournament (never-official generated kitchen). No auto-seal (not tip-changing).
 * Case B = delete-last-finalized-tournament (tip) + project-present-at prior + auto-seal (AD6).
 * Password file: amiga/_ops/amiga_ops_password.local.php ($admin_password).
 */
declare(strict_types=1);

const AMIGA_BACKUP_PAGE_BUILD = 'l5-s4-2026-07-22';

require_once __DIR__ . '/includes/amiga_ops_password_lib.php';
require_once __DIR__ . '/includes/amiga_backup_seal_lib.php';
require_once __DIR__ . '/ops/modules/delete_unfinalized_tournament.php';
require_once __DIR__ . '/ops/modules/delete_last_finalized_tournament.php';

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
    if ($action === 'case_a_delete') {
        $tid = (string) ($_POST['tournament_id'] ?? '');
        if ($tid !== '') {
            $hidden['tournament_id'] = $tid;
        }
        if (isset($_POST['confirm_case_a']) && (string) $_POST['confirm_case_a'] === '1') {
            $hidden['confirm_case_a'] = '1';
        }
    }
    if ($action === 'case_b_delete') {
        $tid = (string) ($_POST['tournament_id'] ?? '');
        if ($tid !== '') {
            $hidden['tournament_id'] = $tid;
        }
        if (isset($_POST['confirm_case_b']) && (string) $_POST['confirm_case_b'] === '1') {
            $hidden['confirm_case_b'] = '1';
        }
    }
    amiga_ops_render_password_form(
        $self,
        'Amiga backup seals — admin password',
        'Admin password required (backup / restore / Case A/B delete).',
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
$caseACandidates = [];
$caseBContext = ['tip' => null, 'prior' => null];

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
} elseif ($action === 'case_a_delete') {
    $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
    $confirmed = isset($_POST['confirm_case_a']) && (string) $_POST['confirm_case_a'] === '1';
    if ($tournamentId <= 0) {
        $flashErr = 'Case A delete requires a tournament id.';
    } elseif (!$confirmed) {
        $flashErr = 'Case A delete requires the confirm checkbox (admin-only hard delete of ground).';
    } else {
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
                $deleted = amiga_delete_unfinalized_tournament($con, $tournamentId, false);
                if (!$deleted['ok']) {
                    $flashErr = 'Case A delete refused: ' . (string) $deleted['error'];
                    mysqli_close($con);
                } else {
                    mysqli_close($con);
                    // Case A is not tip-changing — no auto-seal (BA2/AD6 apply to Finish + Case B/C tip deletes).
                    $flashOk = 'Case A deleted tournament #' . (int) $deleted['tournament_id']
                        . ' (' . (string) $deleted['name'] . ')'
                        . ' (lifecycle was ' . (string) $deleted['lifecycle_status'] . '; '
                        . (int) $deleted['games_deleted'] . ' game(s) removed'
                        . '; orphan live players: ' . count($deleted['orphan_players_deleted'])
                        . '). Tip unchanged — no backup seal (use Backup now if you want one).';
                }
            }
        }
    }
} elseif ($action === 'case_b_delete') {
    $tournamentId = max(0, (int) ($_POST['tournament_id'] ?? 0));
    $confirmed = isset($_POST['confirm_case_b']) && (string) $_POST['confirm_case_b'] === '1';
    if ($tournamentId <= 0) {
        $flashErr = 'Case B delete requires a tournament id (must be the current tip).';
    } elseif (!$confirmed) {
        $flashErr = 'Case B delete requires the confirm checkbox (tip delete + present re-project + seal).';
    } else {
        set_time_limit(900);
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
                $deleted = amiga_delete_last_finalized_tournament($con, $tournamentId, false);
                if (!$deleted['ok']) {
                    $flashErr = 'Case B delete refused: ' . (string) $deleted['error'];
                    mysqli_close($con);
                } else {
                    $seal = amiga_backup_seal_write_from_config($con, [
                        'reason' => 'case_b_delete',
                        'reserve' => false,
                    ]);
                    mysqli_close($con);
                    if (!$seal['ok']) {
                        $flashErr = 'Case B deleted tip #' . (int) $deleted['tournament_id']
                            . ' and re-projected present at #' . (int) $deleted['prior_tournament_id']
                            . ', but backup seal FAILED: ' . (string) $seal['error']
                            . ' — tip-changing success without seal is a loud failure (AD6). Run Backup now.';
                    } else {
                        $flashOk = 'Case B deleted tip #' . (int) $deleted['tournament_id']
                            . ' (' . (string) $deleted['name'] . '); new tip #'
                            . (int) $deleted['prior_tournament_id'] . ' (' . (string) $deleted['prior_name'] . '); '
                            . (int) $deleted['games_deleted'] . ' game(s) removed; present re-projected; '
                            . 'seal ' . (string) $seal['seal_id']
                            . ' (' . (int) $seal['parts'] . ' parts, '
                            . round(((int) $seal['bytes']) / 1048576, 1) . ' MB).';
                    }
                }
            }
        }
    }
}

$seals = amiga_backup_seal_list();
$marker = amiga_backup_restore_marker_read();
$importApplyUrl = '/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot';

// Candidate lists for Case A/B UI (best-effort; page still works if DB down).
try {
    include __DIR__ . '/../../config/ko2amiga_config.php';
    $dbNameList = (string) ($database ?? '');
    if ($dbNameList === 'ko2amiga_db' || $dbNameList === 'ko2amiga_work') {
        mysqli_report(MYSQLI_REPORT_OFF);
        $conList = @new mysqli($dbhost, $username, $password, $database, $dbportnum);
        if (!$conList->connect_errno) {
            $conList->set_charset('utf8mb4');
            $caseACandidates = amiga_case_a_list_candidates($conList, 40);
            $caseBContext = amiga_case_b_tip_context($conList);
            mysqli_close($conList);
        }
    }
} catch (Throwable $e) {
    $caseACandidates = [];
    $caseBContext = ['tip' => null, 'prior' => null];
}

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
    . 'replaces <code>ko2amiga_db</code> (same engine as push import — BA4). Reserve seals cannot be erased via PHP (BA6). '
    . 'Case A = unfinalized kitchen; Case B = latest finalized tip + present re-project + seal (AD1/AD3/AD6). '
    . 'Organizer Abandon/void stays on fixtures Advanced (AD2).</p>';

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

echo '<h2>Case A — delete unfinalized kitchen</h2>';
echo '<p class="muted">Admin-only. Deletes L3+L4 ground for a <strong>never-official</strong> generated tournament '
    . '(no <code>rating_finalized</code>, no L5 timeline). No present re-project. '
    . '<strong>No auto-seal</strong> — tip unchanged (BA2/AD6 are for Finish and tip deletes). '
    . 'Finalized tip → Case B below. Organizers use Abandon/void on fixtures Advanced, not tip-delete.</p>';
echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" '
    . 'onsubmit="return confirm(\'Permanently delete this unfinalized kitchen (ground + games)? Tip is unchanged; no backup seal will be written.\');">';
echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="action" value="case_a_delete">';
echo '<p><label>Tournament id <input type="number" name="tournament_id" min="1" required style="width:8rem"></label></p>';
echo '<p><label><input type="checkbox" name="confirm_case_a" value="1" required> I understand this hard-deletes ground and cannot be undone except by restoring a prior seal</label></p>';
echo '<p><button class="btn btn-danger" type="submit">Delete Case A…</button></p>';
echo '</form>';

if ($caseACandidates === []) {
    echo '<p class="muted">No unfinalized generated kitchens currently listed (or DB unreachable).</p>';
} else {
    echo '<table><thead><tr><th>Id</th><th>Name</th><th>Lifecycle</th><th>Date</th><th>Games</th><th>Entrants</th><th></th></tr></thead><tbody>';
    foreach ($caseACandidates as $cand) {
        $openHref = '/amiga/tournament/event-stats.php?id=' . (int) $cand['id'];
        echo '<tr>';
        echo '<td><code>' . (int) $cand['id'] . '</code></td>';
        echo '<td>' . htmlspecialchars((string) $cand['name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $cand['lifecycle_status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($cand['event_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . (int) $cand['games'] . '</td>';
        echo '<td>' . (int) $cand['entrants'] . '</td>';
        echo '<td><a href="' . htmlspecialchars($openHref, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Open</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

echo '<h2>Case B — delete latest finalized tip</h2>';
echo '<p class="muted">Admin-only. Deletes the <strong>chrono-last</strong> <code>rating_finalized</code> tip, clears its derived rows, '
    . 're-projects present at the prior tip, then writes a backup seal (AD6). '
    . 'Not for mid-history deletes (Case C later). Inspect via Open before deleting.</p>';

$caseBTip = is_array($caseBContext['tip'] ?? null) ? $caseBContext['tip'] : null;
$caseBPrior = is_array($caseBContext['prior'] ?? null) ? $caseBContext['prior'] : null;
if ($caseBTip === null) {
    echo '<p class="muted">No finalized tip found (or DB unreachable).</p>';
} else {
    $tipOpen = '/amiga/tournament/event-stats.php?id=' . (int) $caseBTip['id'];
    echo '<table><thead><tr><th></th><th>Id</th><th>Name</th><th>Date</th><th>Games</th><th>Entrants</th><th></th></tr></thead><tbody>';
    echo '<tr><td><strong>Tip</strong></td>';
    echo '<td><code>' . (int) $caseBTip['id'] . '</code></td>';
    echo '<td>' . htmlspecialchars((string) $caseBTip['name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) ($caseBTip['event_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . (int) $caseBTip['games'] . '</td>';
    echo '<td>' . (int) $caseBTip['entrants'] . '</td>';
    echo '<td><a href="' . htmlspecialchars($tipOpen, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Open</a></td></tr>';
    if ($caseBPrior !== null) {
        $priorOpen = '/amiga/tournament/event-stats.php?id=' . (int) $caseBPrior['id'];
        echo '<tr><td>Prior N</td>';
        echo '<td><code>' . (int) $caseBPrior['id'] . '</code></td>';
        echo '<td>' . htmlspecialchars((string) $caseBPrior['name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($caseBPrior['event_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td colspan="2" class="muted">present re-project target</td>';
        echo '<td><a href="' . htmlspecialchars($priorOpen, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Open</a></td></tr>';
    } else {
        echo '<tr><td colspan="7" class="err">No prior tip — Case B will refuse (cannot empty the realm).</td></tr>';
    }
    echo '</tbody></table>';

    echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" '
        . 'onsubmit="return confirm(\'Case B: permanently delete tip #' . (int) $caseBTip['id']
        . ' and re-project present at prior tip? A backup seal will be written after success.\');">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="action" value="case_b_delete">';
    echo '<input type="hidden" name="tournament_id" value="' . (int) $caseBTip['id'] . '">';
    echo '<p><label><input type="checkbox" name="confirm_case_b" value="1" required> I understand this deletes the official tip, '
        . 're-projects present, seals the new tip, and undo = restore a prior seal</label></p>';
    echo '<p><button class="btn btn-danger" type="submit">Delete Case B tip…</button></p>';
    echo '</form>';
}

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