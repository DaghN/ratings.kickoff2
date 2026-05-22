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
