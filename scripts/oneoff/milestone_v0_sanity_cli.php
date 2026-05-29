<?php
/** CLI helper for milestone_v0_sanity_check.py — JSON on stdout. */
declare(strict_types=1);

$docRoot = dirname(__DIR__, 2) . '/site/public_html';
chdir($docRoot);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;

require_once $docRoot . '/includes/k2_safety.php';
require_once $docRoot . '/includes/player_milestones_helpers.php';
include $docRoot . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'dd_count':
        echo json_encode(['count' => count(k2_milestone_dd_merchant_achievers($con))]);
        break;
    case 'player_counts':
        $pid = (int) ($argv[2] ?? 0);
        echo json_encode(k2_milestone_player_counts($con, $pid));
        break;
    case 'garden_unlocked':
        $pid = (int) ($argv[2] ?? 0);
        $g = k2_milestone_garden_by_tier($con, $pid);
        $u = 0;
        foreach ($g as $tier) {
            foreach ($tier as $card) {
                if (!empty($card['unlocked'])) {
                    $u++;
                }
            }
        }
        echo json_encode(['unlocked' => $u, 'cards' => 110]);
        break;
    case 'meta_top5':
        $rows = k2_milestone_meta_leaderboard_rows($con);
        $out = [];
        foreach (array_slice($rows, 0, 5) as $r) {
            $out[] = [
                'player_id' => (int) $r['player_id'],
                'total' => (int) $r['total'],
                'aspirational' => (int) $r['aspirational'],
                'dedicated' => (int) $r['dedicated'],
                'accomplished' => (int) $r['accomplished'],
                'legendary' => (int) $r['legendary'],
            ];
        }
        echo json_encode($out);
        break;
    default:
        fwrite(STDERR, "unknown cmd\n");
        exit(1);
}

mysqli_close($con);
