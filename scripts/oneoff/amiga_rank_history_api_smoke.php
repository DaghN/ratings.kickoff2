<?php
$_GET['id'] = '237';
$_GET['realm'] = 'amiga';
$_GET['as'] = 'year:2003';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['DOCUMENT_ROOT'] = 'C:/Users/daghn/Desktop/Online and Amiga 500 ELO/site/public_html';
chdir($_SERVER['DOCUMENT_ROOT'] . '/api');
ob_start();
include 'player_rank_history.php';
$out = ob_get_clean();
$j = json_decode($out, true);
echo 'points=' . count($j['points'] ?? []) . ' last=' . (end($j['points'])['eloRank'] ?? 'none') . ' cutoff=' . json_encode($j['meta']['cutoffActive'] ?? null) . PHP_EOL;