<?php
declare(strict_types=1);
/**
 * Online Games Highlights parity — board row IDs + sort order vs wide-scan baseline.
 * Usage: php scripts/oneoff/online_games_highlights_parity_probe.php
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_highlights_helpers.php';
include __DIR__ . '/../../site/config/ko2unitydb_config.php';

function k2_games_highlights_old_fetch(mysqli $con, string $board, int $limit = K2_GAMES_HIGHLIGHTS_LIMIT): array
{
	$board = k2_games_highlights_valid_board($board);
	$limit = max(1, min(200, $limit));

	$select = 'SELECT `id`, `Date`, `idA`, `NameA`, `idB`, `NameB`, `GoalsA`, `GoalsB`, '
		. '`GoalDifference`, `SumOfGoals`, `ActualScore`, `RatingA`, `RatingB`, `RatingDifference`, '
		. '`ExpectedScoreA`, `ExpectedScoreB`, `AdjustmentA`, `AdjustmentB` '
		. 'FROM `ratedresults`';

	switch ($board) {
		case 'biggest_draws':
			$sql = $select . ' WHERE ABS(`ActualScore` - 0.5) < 0.001'
				. ' ORDER BY `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'top_score':
			$sql = $select . ' ORDER BY GREATEST(`GoalsA`, `GoalsB`) DESC, `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'biggest_wins':
			$sql = $select . ' WHERE ABS(`ActualScore` - 0.5) >= 0.001'
				. ' ORDER BY `GoalDifference` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'most_goals':
		default:
			$sql = $select . ' ORDER BY `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
	}

	$result = mysqli_query($con, $sql);
	if ($result === false) {
		return [];
	}
	$rows = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$rows[] = $row;
	}
	mysqli_free_result($result);
	if ($rows !== []) {
		$nameMap = k2_player_display_names_for_rated_rows($con, $rows);
		$rows = k2_rated_games_apply_display_names($rows, $nameMap);
	}
	return $rows;
}

function row_ids(array $rows): array
{
	return array_map(static fn (array $r): int => (int) $r['id'], $rows);
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
	fwrite(STDERR, "connect fail: {$con->connect_error}\n");
	exit(1);
}
$con->set_charset('utf8mb4');

$boards = array_keys(K2_GAMES_HIGHLIGHT_BOARDS);
$allOk = true;
foreach ($boards as $board) {
	$old = k2_games_highlights_old_fetch($con, $board);
	$new = k2_games_highlights_fetch($con, $board);
	$idsMatch = row_ids($old) === row_ids($new);
	$jsonMatch = json_encode($old) === json_encode($new);
	$ok = $idsMatch && $jsonMatch;
	$allOk = $allOk && $ok;
	echo $board . ': ' . ($ok ? 'OK' : 'DIFF');
	if (!$idsMatch) {
		echo ' (id order mismatch)';
	} elseif (!$jsonMatch) {
		echo ' (row payload mismatch)';
	}
	echo "\n";
}

$con->close();
echo $allOk ? "ALL OK\n" : "FAIL\n";
exit($allOk ? 0 : 1);