<?php
declare(strict_types=1);

$repo = dirname(__DIR__, 2);
include $repo . '/site/config/ko2unitydb_config.php';
require $repo . '/site/public_html/includes/player_goals_distribution.php';

$playerId = (int) ($argv[1] ?? 291);
$opponentId = (int) ($argv[2] ?? 260);
$levelCount = 8;
$mixMin = 30;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$payload = player_h2h_scoreline_heatmap_payload($con, $playerId, $opponentId);

$max = 0;
foreach ($payload['cells'] as $cell) {
    if ($cell['games'] > $max) {
        $max = $cell['games'];
    }
}

$levelFn = static function (int $count, int $max, int $levels): int {
    if ($count <= 0) {
        return 0;
    }
    if ($max <= 1) {
        return $levels;
    }
    $level = (int) ceil(($count / $max) * $levels);

    return max(1, min($levels, $level));
};

$mixFn = static function (int $level, int $levels, int $min): int {
    if ($level >= $levels) {
        return 100;
    }

    return (int) round($min + (($level - 1) / ($levels - 1)) * (100 - $min));
};

$nameStmt = $con->prepare('SELECT ID, Name FROM playertable WHERE ID IN (?, ?)');
$nameStmt->bind_param('ii', $playerId, $opponentId);
$nameStmt->execute();
$nameRes = $nameStmt->get_result();
$names = [];
while ($row = $nameRes->fetch_assoc()) {
    $names[(int) $row['ID']] = $row['Name'];
}
$nameStmt->close();
mysqli_close($con);

echo ($names[$playerId] ?? (string) $playerId) . ' vs ' . ($names[$opponentId] ?? (string) $opponentId) . PHP_EOL;
echo 'Total games: ' . array_sum(array_column($payload['cells'], 'games')) . PHP_EOL;
echo 'Peak count (busiest scoreline): ' . $max . PHP_EOL;
echo PHP_EOL;
echo "Level | Games count | Mix %" . PHP_EOL;

for ($level = 1; $level <= $levelCount; $level++) {
    $lo = $level === 1 ? 1 : (int) floor((($level - 1) / $levelCount) * $max) + 1;
    $hi = $level === $levelCount ? $max : (int) floor(($level / $levelCount) * $max);
    echo $level . ' | ' . $lo . '-' . $hi . ' | ' . $mixFn($level, $levelCount, $mixMin) . '%' . PHP_EOL;
}

echo PHP_EOL . 'Actual scorelines per level (hero GF-rival GA):' . PHP_EOL;
$byLevel = array_fill(1, $levelCount, []);
foreach ($payload['cells'] as $cell) {
    $level = $levelFn((int) $cell['games'], $max, $levelCount);
    $byLevel[$level][] = sprintf(
        '%d-%d (%d)',
        $cell['goals_for'],
        $cell['goals_against'],
        $cell['games']
    );
}

for ($level = $levelCount; $level >= 1; $level--) {
    if ($byLevel[$level] === []) {
        continue;
    }
    echo 'L' . $level . ' (' . $mixFn($level, $levelCount, $mixMin) . '%): '
        . implode(', ', $byLevel[$level]) . PHP_EOL;
}
