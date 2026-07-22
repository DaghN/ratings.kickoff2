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

const AMIGA_BACKUP_PAGE_BUILD = 'l5-s4j-2026-07-22';

require_once __DIR__ . '/includes/amiga_ops_password_lib.php';
require_once __DIR__ . '/includes/amiga_backup_seal_lib.php';
require_once __DIR__ . '/includes/amiga_staging_import_lib.php';
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
    if ($action === 'restore_stage' || $action === 'try_delete' || $action === 'restore_apply') {
        $sid = (string) ($_POST['seal_id'] ?? '');
        if ($sid !== '') {
            $hidden['seal_id'] = $sid;
        }
        if ($action === 'restore_apply') {
            $hidden['confirm_restore'] = '1';
            $part = max(1, (int) ($_POST['part'] ?? 1));
            $hidden['part'] = (string) $part;
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
    if ($action === 'case_b_seal') {
        // no extra fields
    }
    if ($action === 'project_present_tip') {
        $phase = (string) ($_POST['project_phase'] ?? 'player_current');
        if ($phase !== '') {
            $hidden['project_phase'] = $phase;
        }
    }
    if ($action === 'diagnose_project_present') {
        $dphase = (string) ($_POST['diagnose_phase'] ?? 'counts');
        if ($dphase !== '') {
            $hidden['diagnose_phase'] = $dphase;
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
/** @var array{deleted_id:int,deleted_name:string,prior_id:int,prior_name:string,games:int}|null */
$caseBNeedSeal = null;
/** @var array{cutoff_id:int,cutoff_name:string,phase:string,next_phase:string,note:string}|null */
$projectNeedNextPhase = null;
/** @var array{phase:string,next_phase:string,note:string}|null */
$diagnoseNeedNextPhase = null;

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
} elseif ($action === 'restore_apply') {
    // BA4 direct: apply seal pack from _backups/<seal>/ into live DB — does not touch _import.
    $sealId = (string) ($_POST['seal_id'] ?? '');
    $part = max(1, (int) ($_POST['part'] ?? 1));
    $confirmed = isset($_POST['confirm_restore']) && (string) $_POST['confirm_restore'] === '1';
    $selfPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '/amiga/run_backup_ko2amiga.php');

    @ignore_user_abort(true);
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    header('Content-Type: text/html; charset=utf-8');
    k2_amiga_import_begin_stream();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Restore seal into DB</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:56rem;margin:1rem;line-height:1.5}';
    echo 'pre{background:#1a1a1a;color:#e8e8e8;padding:.75rem;overflow:auto;font-size:13px}';
    echo '.ok{color:#0a0}.err{color:#a00}.muted{color:#666}';
    echo 'a.btn,button.btn{display:inline-block;margin:4px 4px 4px 0;padding:6px 12px;background:#444;color:#fff;';
    echo 'text-decoration:none;border:0;border-radius:4px;cursor:pointer;font:inherit}</style></head><body>';
    echo '<h1>Restore seal into database</h1>';
    echo '<p class="muted">Build ' . htmlspecialchars(AMIGA_BACKUP_PAGE_BUILD, ENT_QUOTES, 'UTF-8')
        . ' · Applies pack from <code>_backups/</code> (BA4 full replace). '
        . '<strong>Does not</strong> overwrite <code>amiga/_import/</code> (push tray stays intact).</p>';

    if (!$confirmed) {
        echo '<p class="err">Restore requires the confirm checkbox.</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars($selfPath . '?once=' . rawurlencode($key), ENT_QUOTES, 'UTF-8')
            . '">Back to seals</a></p></body></html>';
        exit;
    }

    $validated = amiga_backup_seal_validate_for_restore($sealId);
    if (!$validated['ok']) {
        echo '<p class="err">' . htmlspecialchars($validated['error'], ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars($selfPath . '?once=' . rawurlencode($key), ENT_QUOTES, 'UTF-8')
            . '">Back to seals</a></p></body></html>';
        exit;
    }
    $sealId = (string) $validated['seal_id'];
    $packDir = (string) $validated['pack_dir'];
    $partsTotal = count($validated['parts']);

    include __DIR__ . '/../../config/ko2amiga_config.php';
    $dbName = (string) ($database ?? '');
    if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
        echo '<p class="err">config database must be ko2amiga_db or ko2amiga_work; got '
            . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        exit;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        echo '<p class="err">connect failed: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8')
            . '</p></body></html>';
        exit;
    }
    $con->set_charset('utf8mb4');

    echo '<p><strong>Seal:</strong> <code>' . htmlspecialchars($sealId, ENT_QUOTES, 'UTF-8')
        . '</code> · part <strong>' . $part . '</strong> / ' . $partsTotal
        . ' · DB <code>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<pre>';
    k2_amiga_import_flush();

    $one = k2_amiga_import_apply_one_part($con, $packDir, $part, true);
    if (!$one['ok']) {
        echo '</pre>';
        echo '<p class="err">Restore failed: ' . htmlspecialchars($one['error'], ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars($selfPath . '?once=' . rawurlencode($key), ENT_QUOTES, 'UTF-8')
            . '">Back to seals</a></p></body></html>';
        mysqli_close($con);
        exit;
    }

    echo 'status: OK' . "\n";
    echo 'file: ' . $one['file'] . "\n";
    echo 'statements: ' . (int) $one['statements'] . "\n";
    echo 'elapsed: ' . (float) $one['elapsed'] . " s\n";
    echo '</pre>';

    $after = k2_amiga_import_counts($con);
    echo '<p class="muted">Row counts now: players='
        . ($after['missing'] ? '—' : (string) $after['players'])
        . ', games=' . ($after['missing'] ? '—' : (string) $after['games']) . '</p>';

    if (!$one['done'] && $one['next_part'] !== null) {
        $next = (int) $one['next_part'];
        echo '<p class="ok">Part ' . $part . ' finished. Continuing to part ' . $next . '…</p>';
        echo '<form id="k2-restore-next" method="post" action="'
            . htmlspecialchars($selfPath, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="action" value="restore_apply">';
        echo '<input type="hidden" name="seal_id" value="' . htmlspecialchars($sealId, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="part" value="' . $next . '">';
        echo '<input type="hidden" name="confirm_restore" value="1">';
        echo '<p><button class="btn" type="submit">Continue part ' . $next . '…</button></p>';
        echo '</form>';
        echo '<script>setTimeout(function(){var f=document.getElementById("k2-restore-next");if(f){f.submit();}},400);</script>';
    } else {
        $tipName = '';
        $tipId = 0;
        $tipRes = $con->query(
            'SELECT id, name FROM tournaments WHERE COALESCE(rating_finalized,0)=1 '
            . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
        );
        if ($tipRes) {
            $tipRow = $tipRes->fetch_assoc();
            $tipRes->free();
            if (is_array($tipRow)) {
                $tipId = (int) $tipRow['id'];
                $tipName = (string) $tipRow['name'];
            }
        }
        echo '<p class="ok"><strong>Restore complete.</strong> Live DB replaced from seal '
            . '<code>' . htmlspecialchars($sealId, ENT_QUOTES, 'UTF-8') . '</code>'
            . ($tipId > 0
                ? ('. Tip now #' . $tipId . ' (' . htmlspecialchars($tipName, ENT_QUOTES, 'UTF-8') . ').')
                : '.')
            . '</p>';
        echo '<p><a class="btn" href="' . htmlspecialchars($selfPath . '?once=' . rawurlencode($key), ENT_QUOTES, 'UTF-8')
            . '">Back to backup seals</a> · '
            . '<a class="btn" href="/amiga/rating.php">Amiga rating</a></p>';
    }

    mysqli_close($con);
    echo '</body></html>';
    exit;
} elseif ($action === 'restore_stage') {
    $sealId = (string) ($_POST['seal_id'] ?? '');
    $staged = amiga_backup_seal_stage_for_import($sealId);
    if ($staged['ok']) {
        $stagedSealId = (string) $staged['seal_id'];
        $flashOk = 'Copied seal ' . $stagedSealId . ' into amiga/_import/ ('
            . (int) $staged['parts'] . ' parts) for the push/import tray. '
            . 'Prefer “Restore into DB now” to replace the live DB without touching _import.';
    } else {
        $flashErr = 'Copy into _import failed: ' . (string) $staged['error'];
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
        $flashErr = 'Case B delete requires the confirm checkbox (tip delete + present re-project).';
    } else {
        @ignore_user_abort(true);
        @set_time_limit(900);
        @ini_set('memory_limit', '512M');
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
                try {
                    // Phase 1 only: delete + project-present-at. Seal is a separate request (gateway ~30s).
                    $deleted = amiga_delete_last_finalized_tournament($con, $tournamentId, false);
                    if (!$deleted['ok']) {
                        $flashErr = 'Case B delete refused: ' . (string) $deleted['error'];
                    } else {
                        $caseBNeedSeal = [
                            'deleted_id' => (int) $deleted['tournament_id'],
                            'deleted_name' => (string) $deleted['name'],
                            'prior_id' => (int) $deleted['prior_tournament_id'],
                            'prior_name' => (string) $deleted['prior_name'],
                            'games' => (int) $deleted['games_deleted'],
                        ];
                        $flashOk = 'Case B phase 1 OK: deleted tip #' . $caseBNeedSeal['deleted_id']
                            . ' (' . $caseBNeedSeal['deleted_name'] . '); present re-projected at #'
                            . $caseBNeedSeal['prior_id'] . ' (' . $caseBNeedSeal['prior_name'] . '); '
                            . $caseBNeedSeal['games'] . ' game(s) removed. '
                            . 'Seal is next (separate request — avoids proxy timeout).';
                    }
                } catch (Throwable $e) {
                    $flashErr = 'Case B phase 1 failed: ' . $e->getMessage();
                }
                mysqli_close($con);
            }
        }
    }
} elseif ($action === 'case_b_seal') {
    @ignore_user_abort(true);
    @set_time_limit(900);
    @ini_set('memory_limit', '512M');
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
            try {
                $seal = amiga_backup_seal_write_from_config($con, [
                    'reason' => 'case_b_delete',
                    'reserve' => false,
                ]);
                if (!$seal['ok']) {
                    $flashErr = 'Case B seal FAILED: ' . (string) $seal['error']
                        . ' — tip may already be deleted; run Backup now (AD6).';
                } else {
                    $flashOk = 'Case B seal OK: ' . (string) $seal['seal_id']
                        . ' (' . (int) $seal['parts'] . ' parts, '
                        . round(((int) $seal['bytes']) / 1048576, 1) . ' MB, '
                        . (string) $seal['elapsed'] . 's).';
                }
            } catch (Throwable $e) {
                $flashErr = 'Case B seal exception: ' . $e->getMessage()
                    . ' — tip may already be deleted; run Backup now (AD6).';
            }
            mysqli_close($con);
        }
    }
} elseif ($action === 'project_present_tip') {
    // Repair-only: rebuild present at current finalized tip (no seal, no delete).
    // Phased (player_current → matchups → rest) — full run exceeds staging gateway ~30s.
    @ignore_user_abort(true);
    @set_time_limit(900);
    @ini_set('memory_limit', '512M');
    include __DIR__ . '/../../config/ko2amiga_config.php';
    $dbName = (string) ($database ?? '');
    $phase = (string) ($_POST['project_phase'] ?? 'player_current');
    if (!in_array($phase, ['player_current', 'matchups', 'rest'], true)) {
        $phase = 'player_current';
    }
    if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
        $flashErr = 'config database must be ko2amiga_db (staging) or ko2amiga_work (local); got ' . $dbName . '.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
        if ($con->connect_errno) {
            $flashErr = 'connect failed: ' . $con->connect_error;
        } else {
            $con->set_charset('utf8mb4');
            try {
                require_once __DIR__ . '/ops/modules/project_present_at.php';
                $tip = amiga_case_b_find_tip($con);
                if ($tip === null) {
                    $flashErr = 'No finalized tip to project-present-at.';
                } else {
                    $proj = amiga_ops_project_present_at_phase($con, (int) $tip['id'], $phase);
                    $cutoffId = (int) $proj['cutoff_tournament_id'];
                    $cutoffName = (string) $proj['cutoff_name'];
                    $detail = '';
                    if ($phase === 'player_current') {
                        $detail = 'current=' . (int) $proj['player_current']
                            . ' (elo + inverse pointer overlay)';
                    } elseif ($phase === 'matchups') {
                        $detail = 'matchups=' . (int) $proj['matchup_summary'];
                    } else {
                        $detail = 'slices/generalstats/community/wc_hof';
                    }
                    $next = $proj['next_phase'] ?? null;
                    if (is_string($next) && $next !== '') {
                        $projectNeedNextPhase = [
                            'cutoff_id' => $cutoffId,
                            'cutoff_name' => $cutoffName,
                            'phase' => $phase,
                            'next_phase' => $next,
                            'note' => $detail,
                        ];
                        $flashOk = 'Re-project phase ' . $phase . ' OK at tip #' . $cutoffId
                            . ' (' . $cutoffName . '): ' . $detail
                            . '. Continuing with phase ' . $next . '…';
                    } else {
                        $flashOk = 'Re-projected present at tip #' . $cutoffId
                            . ' (' . $cutoffName . '): all phases done (' . $detail . '). No seal written.';
                    }
                }
            } catch (Throwable $e) {
                $flashErr = 'project-present-at failed (phase ' . $phase . '): ' . $e->getMessage();
            }
            mysqli_close($con);
        }
    }
} elseif ($action === 'diagnose_project_present') {
    // Read-only phased diagnose (counts → time_no_exists → time_exists). No DELETE/INSERT.
    @ignore_user_abort(true);
    @set_time_limit(60);
    @ini_set('memory_limit', '256M');
    include __DIR__ . '/../../config/ko2amiga_config.php';
    $dbName = (string) ($database ?? '');
    $dphase = (string) ($_POST['diagnose_phase'] ?? 'counts');
    if (!in_array($dphase, ['counts', 'time_no_exists', 'time_exists'], true)) {
        $dphase = 'counts';
    }
    if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
        $flashErr = 'config database must be ko2amiga_db (staging) or ko2amiga_work (local); got ' . $dbName . '.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
        if ($con->connect_errno) {
            $flashErr = 'connect failed: ' . $con->connect_error;
        } else {
            $con->set_charset('utf8mb4');
            try {
                require_once __DIR__ . '/ops/modules/project_present_at.php';
                if (!function_exists('amiga_ops_diagnose_project_present')) {
                    throw new RuntimeException(
                        'amiga_ops_diagnose_project_present missing — sync '
                        . 'amiga/ops/modules/project_present_at.php (Build l5-s4h)'
                    );
                }
                $diag = amiga_ops_diagnose_project_present($con, $dphase, 8000);
                $parts = [
                    'Diagnose phase=' . (string) $diag['phase']
                        . ' tip #' . (int) $diag['tip_id']
                        . ' (' . (string) $diag['tip_name'] . ')',
                ];
                foreach ($diag['counts'] as $k => $v) {
                    $parts[] = $k . '=' . (int) $v;
                }
                foreach ($diag['timings_ms'] as $k => $v) {
                    if ($v !== null) {
                        $parts[] = $k . '=' . (int) $v . 'ms';
                    }
                }
                foreach ($diag['notes'] as $note) {
                    $parts[] = '· ' . $note;
                }
                $flashOk = implode(' | ', $parts);
                $next = $diag['next_phase'] ?? null;
                if (is_string($next) && $next !== '') {
                    $diagnoseNeedNextPhase = [
                        'phase' => (string) $diag['phase'],
                        'next_phase' => $next,
                        'note' => $flashOk,
                    ];
                }
            } catch (Throwable $e) {
                $flashErr = 'diagnose_project_present failed (phase ' . $dphase . '): '
                    . $e->getMessage();
            }
            mysqli_close($con);
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
    . 'Restore = apply a seal pack straight into the live DB from <code>_backups/</code> (BA4; does not touch the push tray). '
    . 'Organizer Abandon/void stays on fixtures Advanced (AD2).</p>';

if ($flashOk !== '') {
    echo '<p class="ok">' . htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') . '</p>';
}
if ($flashErr !== '') {
    echo '<p class="err">' . htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8') . '</p>';
}

if ($caseBNeedSeal !== null) {
    echo '<div class="box">';
    echo '<p><strong>Case B — write seal now (phase 2)</strong></p>';
    echo '<p class="muted">Tip already deleted + present re-projected. This request only writes the backup pack (AD6). '
        . 'If the browser times out again, reload this page and click <strong>Backup now</strong>.</p>';
    echo '<form id="k2-case-b-seal" method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="action" value="case_b_seal">';
    echo '<p><button class="btn" type="submit">Write Case B seal…</button></p>';
    echo '</form>';
    echo '<script>setTimeout(function(){var f=document.getElementById("k2-case-b-seal");if(f){f.submit();}},400);</script>';
    echo '</div>';
}

if ($projectNeedNextPhase !== null) {
    $nextPhase = (string) $projectNeedNextPhase['next_phase'];
    echo '<div class="box">';
    echo '<p><strong>Re-project — continue phase '
        . htmlspecialchars($nextPhase, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p class="muted">Split across requests to stay under the staging gateway (~30s). '
        . 'Last: <code>' . htmlspecialchars((string) $projectNeedNextPhase['phase'], ENT_QUOTES, 'UTF-8')
        . '</code> — ' . htmlspecialchars((string) $projectNeedNextPhase['note'], ENT_QUOTES, 'UTF-8')
        . '. Tip #' . (int) $projectNeedNextPhase['cutoff_id'] . ' ('
        . htmlspecialchars((string) $projectNeedNextPhase['cutoff_name'], ENT_QUOTES, 'UTF-8')
        . '). If this page hangs, reload and click <strong>Re-project</strong> again '
        . '(phases are idempotent).</p>';
    echo '<form id="k2-project-next" method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="action" value="project_present_tip">';
    echo '<input type="hidden" name="project_phase" value="'
        . htmlspecialchars($nextPhase, ENT_QUOTES, 'UTF-8') . '">';
    echo '<p><button class="btn" type="submit">Continue phase '
        . htmlspecialchars($nextPhase, ENT_QUOTES, 'UTF-8') . '…</button></p>';
    echo '</form>';
    echo '<script>setTimeout(function(){var f=document.getElementById("k2-project-next");if(f){f.submit();}},400);</script>';
    echo '</div>';
}

if ($diagnoseNeedNextPhase !== null) {
    $nextD = (string) $diagnoseNeedNextPhase['next_phase'];
    echo '<div class="box">';
    echo '<p><strong>Diagnose — continue '
        . htmlspecialchars($nextD, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p class="muted">Optional timing probe (read-only). Auto-continues; stop by not clicking if you only wanted counts.</p>';
    echo '<form id="k2-diagnose-next" method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="action" value="diagnose_project_present">';
    echo '<input type="hidden" name="diagnose_phase" value="'
        . htmlspecialchars($nextD, ENT_QUOTES, 'UTF-8') . '">';
    echo '<p><button class="btn" type="submit">Continue diagnose '
        . htmlspecialchars($nextD, ENT_QUOTES, 'UTF-8') . '…</button></p>';
    echo '</form>';
    // Do NOT auto-submit timing phases — counts alone is the safe signal; user opts in.
    echo '</div>';
}

if ($stagedSealId !== '') {
    echo '<div class="box">';
    echo '<p><strong>Copied into push tray (<code>_import/</code>):</strong> <code>'
        . htmlspecialchars($stagedSealId, ENT_QUOTES, 'UTF-8') . '</code></p>';
    echo '<p class="muted">This does <strong>not</strong> change the live DB by itself. '
        . 'Use <strong>Restore into DB now</strong> on a seal row for a direct BA4 replace, '
        . 'or Apply import if you intentionally staged a push/import pack.</p>';
    echo '<form method="post" action="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="ko2amiga-import-one-shot">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="part" value="1">';
    echo '<button class="btn" type="submit">Apply import from _import (part 1…)</button></form>';
    echo '</div>';
} elseif (is_array($marker) && !empty($marker['seal_id'])) {
    echo '<div class="box">';
    echo '<p><strong>Push tray still has restore marker:</strong> <code>'
        . htmlspecialchars((string) $marker['seal_id'], ENT_QUOTES, 'UTF-8') . '</code>';
    if (!empty($marker['staged_at'])) {
        echo ' · ' . htmlspecialchars((string) $marker['staged_at'], ENT_QUOTES, 'UTF-8');
    }
    echo '</p>';
    echo '<p class="muted">Leftover from “Copy into _import”. Prefer direct <strong>Restore into DB now</strong> on a seal. '
        . 'Apply import still works if you meant to use the tray.</p>';
    echo '<form method="post" action="' . htmlspecialchars($importApplyUrl, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="once" value="ko2amiga-import-one-shot">';
    echo '<input type="hidden" name="apply" value="1">';
    echo '<input type="hidden" name="part" value="1">';
    echo '<button class="btn" type="submit">Apply import from _import</button></form>';
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

echo '<div class="box">';
echo '<p><strong>Repair present (no delete, no seal)</strong></p>';
echo '<p class="muted">Rebuilds present tables at the current finalized tip via <code>project-present-at</code> '
    . '(3 auto-chained phases — gateway-safe). Use after a botched Case B when tip ground is OK but profiles/LB look wrong.</p>';
echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" '
    . 'onsubmit="return confirm(\'Re-project present at current tip? No backup seal will be written.\');">';
echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="action" value="project_present_tip">';
echo '<input type="hidden" name="project_phase" value="player_current">';
echo '<p><button class="btn" type="submit">Re-project present at tip…</button></p>';
echo '</form>';
echo '<p class="muted">Before another fix: <strong>Diagnose (counts)</strong> is read-only and should return in ~1s. '
    . 'Optional follow-up buttons time the matchup SELECT shapes (may still be slow on staging).</p>';
echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
echo '<input type="hidden" name="action" value="diagnose_project_present">';
echo '<input type="hidden" name="diagnose_phase" value="counts">';
echo '<p><button class="btn" type="submit">Diagnose present (counts only)…</button></p>';
echo '</form></div>';

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
        // Case A = never-official kitchen → organizer Play (not catalog / public live).
        $openHref = '/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot&view=play&tournament_id='
            . (int) $cand['id'];
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
    . 're-projects present at the prior tip, then (separate request) writes a backup seal (AD6). '
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
        . 'onsubmit="return confirm(\'Case B phase 1: permanently delete tip #' . (int) $caseBTip['id']
        . ' and re-project present at prior tip? Seal runs as a second request afterward.\');">';
    echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="action" value="case_b_delete">';
    echo '<input type="hidden" name="tournament_id" value="' . (int) $caseBTip['id'] . '">';
    echo '<p><label><input type="checkbox" name="confirm_case_b" value="1" required> I understand this deletes the official tip, '
        . 're-projects present, then seals in a follow-up request; undo = restore a prior seal</label></p>';
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
            . 'onsubmit="return confirm(\'FULL REPLACE of the live Amiga DB from this seal? '
            . 'This applies the pack from _backups/ and does not touch the _import push tray.\');">';
        echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="action" value="restore_apply">';
        echo '<input type="hidden" name="seal_id" value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="part" value="1">';
        echo '<input type="hidden" name="confirm_restore" value="1">';
        echo '<button class="btn btn-danger" type="submit">Restore into DB now…</button></form> ';
        echo '<form method="post" action="' . htmlspecialchars($self, ENT_QUOTES, 'UTF-8') . '" style="display:inline" '
            . 'onsubmit="return confirm(\'Copy this seal into amiga/_import/ only (overwrites push tray)? '
            . 'Live DB is unchanged until you Apply import.\');">';
        echo '<input type="hidden" name="once" value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="action" value="restore_stage">';
        echo '<input type="hidden" name="seal_id" value="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">';
        echo '<button type="submit" title="Advanced: copy into push tray">Copy → _import</button></form> ';
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