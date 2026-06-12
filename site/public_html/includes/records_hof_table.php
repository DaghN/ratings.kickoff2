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

/** Worst-case value cell width (5-digit counts, percentages, scorelines). */
function k2_hof_synced_value_col_ch(): int
{
	return mb_strlen('100.0%') + 2;
}

/** Worst-case date cell width (M j, Y + age marker). */
function k2_hof_synced_date_col_ch(): int
{
	return mb_strlen('Sep 30, 2026 (Legendary)') + 2;
}

/** Shared colgroup for paired HoF tables (table-layout: fixed + CSS vars on the panels root). */
function records_hof_render_colgroup(): void
{
	echo "<colgroup>\n";
	echo '    <col class="k2-hof-col--label">' . "\n";
	echo '    <col class="k2-hof-col--value">' . "\n";
	echo '    <col class="k2-hof-col--holder">' . "\n";
	echo '    <col class="k2-hof-col--date">' . "\n";
	echo "</colgroup>\n";
}
