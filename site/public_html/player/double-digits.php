<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_safety.php";
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
$id = k2_positive_int_param('id', 'Invalid player id.');
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
$name = $Name ?? '';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(DD), SUM(DDC), SUM(CS), SUM(CSC), AVG(DD), AVG(DDC), AVG(CS), AVG(CSC)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, DDPlayerA AS DD, DDPlayerB AS DDC, CSPlayerA AS CS, CSPlayerB AS CSC FROM ratedresults 
    WHERE idA = " . $id . "
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, DDPlayerB AS DD, DDPlayerA AS DDC, CSPlayerB AS CS, CSPlayerA AS CSC FROM ratedresults 
    WHERE idB = " . $id . "
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = k2_query_or_public_error($con, $query, 'individual2c matchup table');

mysqli_close($con);

function individual2c_ratio_cell(float $ratio): string
{
    return $ratio == 0.0 ? '0%' : number_format(100 * $ratio, 1) . '%';
}
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'double-digits';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table k2-table--opponent-matchup" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="1" data-k2-default-direction="desc">

<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double Digits</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean Sheets</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digits ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheets ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS Ratio</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD conceded</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">CS conceded</th>
        <th data-k2-sort="number" data-k2-tooltip-label="DD conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD C Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="CS conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS C Ratio</th>
    </tr>
</thead>

<tbody>
	<?php
    while ($row = mysqli_fetch_row($result)) {
        $opponentid = (int) $row[0];
        $opponentname = (string) $row[1];
        $games = (int) $row[2];
        $doubleDigits = (int) $row[3];
        $doubleDigitsConceded = (int) $row[4];
        $cleanSheets = (int) $row[5];
        $cleanSheetsConceded = (int) $row[6];
        $ddRatio = (float) $row[7];
        $ddConcededRatio = (float) $row[8];
        $csRatio = (float) $row[9];
        $csConcededRatio = (float) $row[10];
    ?>

    <tr>
        <td class="k2-table-cell--left"><?php echo k2_player_link($opponentid, $opponentname); ?></td>
        <td><?php echo $games; ?></td>
        <td><?php echo $doubleDigits; ?></td>
        <td><?php echo $cleanSheets; ?></td>
        <td><?php echo individual2c_ratio_cell($ddRatio); ?></td>
        <td><?php echo individual2c_ratio_cell($csRatio); ?></td>
        <td><?php echo $doubleDigitsConceded; ?></td>
        <td><?php echo $cleanSheetsConceded; ?></td>
        <td><?php echo individual2c_ratio_cell($ddConcededRatio); ?></td>
        <td><?php echo individual2c_ratio_cell($csConcededRatio); ?></td>
    </tr>

    <?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
