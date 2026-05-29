<?php
/**
 * Load player hero variables from playertable. Requires $con (mysqli) and $id.
 */
if (!isset($con) || !isset($id)) {
	return;
}
$escHeroId = mysqli_real_escape_string($con, (string) $id);
$heroQuery = "SELECT Name, Rating, PeakRating, NumberGames, Display FROM playertable WHERE id='" . $escHeroId . "'";
$heroResult = mysqli_query($con, $heroQuery);
if ($heroResult && ($heroRow = mysqli_fetch_assoc($heroResult))) {
	$Name = $heroRow['Name'];
	$Rating = $heroRow['Rating'];
	$PeakRating = $heroRow['PeakRating'];
	$NumberGames = $heroRow['NumberGames'];
	$Display = $heroRow['Display'];
	if (!isset($name)) {
		$name = $Name;
	}
	$rankResult = mysqli_query(
		$con,
		"SELECT COUNT(*)+1 AS plrank FROM playertable WHERE display = 1 AND rating > (SELECT rating FROM playertable WHERE id='" . $escHeroId . "')"
	);
	if ($rankResult && ($rankRow = mysqli_fetch_row($rankResult))) {
		$rank = (int) $rankRow[0];
	}
}

$heroMilestoneCounts = null;
$heroMsCatalogTotal = 0;
if (isset($NumberGames) && (int) $NumberGames >= 1) {
	require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
	$heroMsCatalogTotal = k2_milestone_catalog_total($con);
	$heroMilestoneCounts = k2_milestone_player_counts($con, (int) $id);
}
