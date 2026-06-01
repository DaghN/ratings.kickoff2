<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

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

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(goalsfor), SUM(goalsagainst), AVG(goalsfor), AVG(goalsagainst), MAX(goalsfor), MAX(goalsagainst), MIN(goalsfor), MIN(goalsagainst), MAX(CASE WHEN goalsfor > goalsagainst THEN goalsfor - goalsagainst ELSE NULL END), MAX(CASE WHEN goalsagainst > goalsfor THEN goalsagainst - goalsfor ELSE NULL END), MAX(CASE WHEN draw = 1 THEN goalsfor ELSE NULL END), SUM(draw), MAX(goalsfor+goalsagainst), MIN(goalsfor+goalsagainst)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, goalsA AS goalsfor, goalsB AS goalsagainst, draw AS draw FROM ratedresults 
    WHERE idA = " . $id . "
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, goalsB AS goalsfor, goalsA AS goalsagainst, draw AS draw FROM ratedresults 
    WHERE idB = " . $id . "
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = k2_query_or_public_error($con, $query, 'individual2b matchup table');

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'goals';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table k2-table--opponent-matchup" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="1" data-k2-default-direction="desc">

<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">Scored</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Conceded</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Scored average" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">Scored avg.</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Conceded average" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">Conc. avg.</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goal ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_scored(), ENT_QUOTES, 'UTF-8'); ?>">Most Scored</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Most Conceded</th>
        <th data-k2-sort="number" data-k2-help="Fewest goals scored in one game against this opponent.">Least Scored</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Least conceded" data-k2-help="Fewest goals conceded in one game against this opponent.">Least conc.</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Win margin</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Loss margin</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Draw</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Goal sum</th>
        <th data-k2-sort="number" data-k2-help="Fewest total goals in one game against this opponent.">Smallest sum</th>
    </tr>
</thead>

<tbody>
	<?php
    while ($row = mysqli_fetch_row($result)) {
        $opponentid = (int) $row[0];
        $opponentname = (string) $row[1];
        $games = (int) $row[2];
        $goalsfor = (int) $row[3];
        $goalsagainst = (int) $row[4];
        $averagefor = (float) $row[5];
        $averageagainst = (float) $row[6];
        $goalratio = $goalsagainst !== 0 ? $goalsfor / $goalsagainst : -1.0;
        $mostscored = (int) $row[7];
        $mostconceded = (int) $row[8];
        $leastscored = (int) $row[9];
        $leastconceded = (int) $row[10];
        $biggestwin = $row[11] !== null && $row[11] !== '' ? (int) $row[11] : null;
        $biggestloss = $row[12] !== null && $row[12] !== '' ? (int) $row[12] : null;
        $biggestdraw = (int) $row[13];
        $numberdraws = (int) $row[14];
        $biggestgoalsum = (int) $row[15];
        $smallestgoalsum = (int) $row[16];
        $drawSort = $numberdraws > 0 ? $biggestdraw : -1;
        $drawDisplay = $numberdraws > 0 ? $biggestdraw . '-' . $biggestdraw : '-';
    ?>

    <tr>
        <td class="k2-table-cell--left"><?php echo k2_player_link($opponentid, $opponentname); ?></td>
        <td><?php echo $games; ?></td>
        <td><?php echo $goalsfor; ?></td>
        <td><?php echo $goalsagainst; ?></td>
        <td><?php echo number_format($averagefor, 2); ?></td>
        <td><?php echo number_format($averageagainst, 2); ?></td>
        <td><?php
            if ($goalratio < 0) {
                echo '-';
            } else {
                echo number_format($goalratio, 2);
            }
        ?></td>
        <td><?php echo $mostscored; ?></td>
        <td><?php echo $mostconceded; ?></td>
        <td><?php echo $leastscored; ?></td>
        <td><?php echo $leastconceded; ?></td>
        <td><?php echo $biggestwin !== null ? $biggestwin : '-'; ?></td>
        <td><?php echo $biggestloss !== null ? $biggestloss : '-'; ?></td>
        <td data-k2-sort-value="<?php echo $drawSort; ?>"><?php echo $drawDisplay; ?></td>
        <td><?php echo $biggestgoalsum; ?></td>
        <td><?php echo $smallestgoalsum; ?></td>
    </tr>

    <?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
