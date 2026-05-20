<?php
/**
 * Load player hero variables from playertable. Requires $con (mysqli) and $id.
 */
if (!isset($con) || !isset($id)) {
	return;
}
$heroQuery = "SELECT Name, Rating, PeakRating, NumberGames, Display FROM playertable WHERE id='" . mysqli_real_escape_string($con, (string) $id) . "'";
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
}
