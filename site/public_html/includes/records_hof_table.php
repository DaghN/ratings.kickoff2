<?php
/**
 * Hall of Fame record tables — shared column sizing for side-by-side panels.
 */
declare(strict_types=1);

/**
 * Approximate shared min width for column 1 from static label strings (ch units).
 * Labels are hardcoded in hall-of-fame.php; keep the array in sync when adding rows.
 */
function k2_hof_synced_label_col_ch(array $labels): int
{
	$max = 0;
	foreach ($labels as $label) {
		$len = mb_strlen($label);
		if ($len > $max) {
			$max = $len;
		}
	}

	// +2ch fudge for 8px horizontal cell padding vs proportional body font.
	return $max + 2;
}
