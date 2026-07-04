<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_matchup_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_player_opponents_load.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function legacy_matchup_at_event_directed_from_sql(string $alias = 'm'): string
{
    return "FROM (\n"
        . "    SELECT x.* FROM (\n"
        . "        SELECT m.*,\n"
        . "            ROW_NUMBER() OVER (\n"
        . "                PARTITION BY m.player_id, m.opponent_id\n"
        . "                ORDER BY m.event_date DESC, m.event_chrono DESC, m.as_of_tournament_id DESC\n"
        . "            ) AS rn\n"
        . "        FROM amiga_player_matchup_at_event m\n"
        . "        WHERE m.player_id = ? AND m.opponent_id = ?\n"
        . "          AND (m.event_date, m.event_chrono, m.as_of_tournament_id) <= (?, ?, ?)\n"
        . "    ) x\n"
        . "    WHERE x.rn = 1\n"
        . ") {$alias}";
}

function legacy_directed_row(mysqli $con, int $playerId, int $opponentId, AmigaSnapshotContext $ctx): ?array
{
    if ($playerId < 1 || $opponentId < 1) { return null; }
    $select = 'SELECT ' . implode(', ', amiga_matchup_opponents_select_columns(true));
    $cutoff = $ctx->cutoff();
    if ($cutoff === null) { return null; }
    $sql = $select
        . ' ' . legacy_matchup_at_event_directed_from_sql('m')
        . ' LEFT JOIN amiga_players p ON p.id = m.opponent_id'
        . ' ' . amiga_matchup_opponents_rating_at_cutoff_join_sql()
        . ' LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) { return null; }
    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('iisdisdi', $playerId, $opponentId, $eventDate, $chrono, $tournamentId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) { $res->free(); }
    $stmt->close();
    return $row ?: null;
}

function row_signature(?array $row): string
{
    if ($row === null) {
        return '';
    }
    ksort($row);
    $parts = [];
    foreach ($row as $key => $value) {
        $parts[] = $key . '=' . (string) $value;
    }
    return implode('|', $parts);
}

$playerId = 382;
$opponentId = 398;
$cutoffs = [
    'present' => '',
    'event:22' => 'event:22',
    'event:589' => 'event:589',
    'month:2014-07' => 'month:2014-07',
    'month:2025-09' => 'month:2025-09',
    'year:2024' => 'year:2024',
    'year:2001' => 'year:2001',
];
$fail = 0;

foreach ($cutoffs as $label => $as) {
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    $rows = amiga_player_opponents_matchup_rows($con, $playerId, $ctx);
    $fromRows = amiga_player_opponents_matchup_row_from_rows($rows, $opponentId);
    $directed = amiga_player_matchup_directed_opponent_row($con, $playerId, $opponentId, $ctx);
    $normFromRows = $fromRows;
    $normDirected = $directed !== null ? amiga_player_opponents_normalize_matchup_row($directed) : null;

    if (row_signature($normFromRows) !== row_signature($normDirected)) {
        echo "FAIL bucket vs directed player=$playerId vs $opponentId @ $label\n";
        $fail++;
    }

    if ($ctx->isActive()) {
        $legacy = legacy_directed_row($con, $playerId, $opponentId, $ctx);
        $legacyNorm = $legacy !== null ? amiga_player_opponents_normalize_matchup_row($legacy) : null;
        if (row_signature($legacyNorm) !== row_signature($normDirected)) {
            echo "FAIL directed narrow vs legacy player=$playerId vs $opponentId @ $label\n";
            $fail++;
        }
    }

    $cachedSig = array_map('row_signature', $rows);
    sort($cachedSig);
    $fresh = [];
    foreach (amiga_player_matchup_opponent_rows($con, $playerId, $ctx) as $raw) {
        $fresh[] = row_signature(amiga_player_opponents_normalize_matchup_row($raw));
    }
    sort($fresh);
    if ($cachedSig !== $fresh) {
        echo "FAIL matchup rows player=$playerId @ $label\n";
        $fail++;
    }
}

$con->close();
if ($fail > 0) {
    echo "PARITY FAIL ($fail checks)\n";
    exit(1);
}
echo "PARITY OK\n";