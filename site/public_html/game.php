<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="js/elolist.js"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}

$stmt = mysqli_prepare($con, 'SELECT * FROM ratedresults WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
mysqli_free_result($result);
mysqli_stmt_close($stmt);
mysqli_close($con);

/**
 * @param array<string, mixed> $game
 */
function k2_game_rating_adjustment_html(array $game): string
{
    $actual = (float) ($game['ActualScore'] ?? -1);
    $adjA = (float) ($game['AdjustmentA'] ?? 0);
    $adjB = (float) ($game['AdjustmentB'] ?? 0);
    $idA = (int) ($game['idA'] ?? 0);
    $idB = (int) ($game['idB'] ?? 0);
    $nameA = (string) ($game['NameA'] ?? '');
    $nameB = (string) ($game['NameB'] ?? '');

    if (abs($actual - 1.0) < 0.001) {
        $adj = $adjA;
        $pid = $idA;
        $pname = $nameA;
    } elseif (abs($actual) < 0.001) {
        $adj = $adjB;
        $pid = $idB;
        $pname = $nameB;
    } else {
        if ($adjA >= $adjB) {
            $adj = $adjA;
            $pid = $idA;
            $pname = $nameA;
        } else {
            $adj = $adjB;
            $pid = $idB;
            $pname = $nameB;
        }
    }

    $sign = $adj >= 0 ? '+' : '-';
    $adjText = $sign . number_format(abs($adj), 1);
    $nameHtml = $pid > 0
        ? '<a class="k2-link-star" href="individual1.php?id=' . $pid . '">' . htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') . '</a>'
        : htmlspecialchars($pname, ENT_QUOTES, 'UTF-8');

    return $nameHtml . ' <span class="blue">' . $adjText . '</span>';
}
?>

<div class="k2-table-wrap">

<?php if ($row === null) { ?>
<p>Game not found.</p>
<?php } else { ?>
<table class="k2-table table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:50 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount">

<thead>
	<tr style="text-align:right;">
    	<th style="text-align:left;">ID</th>
        <th style="text-align:left;">&nbsp;Date</th>
        <th style="text-align:left;">Team A</th>
        <th></th>
        <th></th>
        <th style="text-align:left;">Team B</th>
        <th>&nbsp;&nbsp;&nbsp;Diff</th>
        <th>Sum</th>
        <th style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th>Rating A</th>
        <th>Rating B</th>
        <th>Rating Diff</th>
       	<th>ES Winner</th>
        <th style="text-align:left;">Adjustment</th>
	</tr>
</thead>

<tbody class="black">
	<tr style="text-align:right;">
        <td><?php echo (int) $row['id']; ?></td>
        <td>&nbsp;<?php echo htmlspecialchars((string) $row['Date'], ENT_QUOTES, 'UTF-8'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><a href="individual1.php?id=<?php echo (int) $row['idA']; ?>"><?php echo htmlspecialchars((string) $row['NameA'], ENT_QUOTES, 'UTF-8'); ?></a></td>
        <td><?php echo (int) $row['GoalsA']; ?></td>
        <td style="text-align:left;"><?php echo (int) $row['GoalsB']; ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo (int) $row['idB']; ?>"><?php echo htmlspecialchars((string) $row['NameB'], ENT_QUOTES, 'UTF-8'); ?></a></td>
        <td><?php echo (int) $row['GoalDifference']; ?></td>
        <td><?php echo (int) $row['SumOfGoals']; ?></td>
        <td style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;
<?php
    $actual = (float) $row['ActualScore'];
    if (abs($actual - 1.0) < 0.001) {
        echo '<a href="individual1.php?id=' . (int) $row['idA'] . '">' . htmlspecialchars((string) $row['NameA'], ENT_QUOTES, 'UTF-8') . '</a>';
    } elseif (abs($actual) < 0.001) {
        echo '<a href="individual1.php?id=' . (int) $row['idB'] . '">' . htmlspecialchars((string) $row['NameB'], ENT_QUOTES, 'UTF-8') . '</a>';
    } else {
        echo 'Draw';
    }
?>
			</td>
        <td><?php echo (int) round((float) $row['RatingA']); ?></td>
        <td><?php echo (int) round((float) $row['RatingB']); ?></td>
        <td><?php echo number_format(abs((float) $row['RatingDifference']), 1); ?></td>
        <td>
<?php
    $expectedA = (float) $row['ExpectedScoreA'];
    $expectedB = (float) $row['ExpectedScoreB'];
    if (abs($actual - 1.0) < 0.001) {
        echo number_format(100 * $expectedA, 1) . '%';
    } elseif (abs($actual) < 0.001) {
        echo number_format(100 * $expectedB, 1) . '%';
    } else {
        echo number_format(min(100 * $expectedA, 100 * $expectedB), 1) . '%';
    }
?>
		</td>
        <td style="text-align:left;"><?php echo k2_game_rating_adjustment_html($row); ?></td>
	</tr>
</tbody>

</table>
<?php } ?>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
