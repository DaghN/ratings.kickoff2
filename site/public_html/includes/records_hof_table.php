<?php
/**
 * Hall of Fame record table — shared column sizing for the main record list.
 */
declare(strict_types=1);

/** Extra ch for holder cells (.k2-table-cell--pad-x-md inside 8px-padded td). */
const K2_HOF_HOLDER_COL_EXTRA_CH = 6;

/** @var array{values: list<string>, holders: list<string>, dates: list<string>} */
$GLOBALS['k2_hof_sync_samples'] = [
	'values' => [],
	'holders' => [],
	'dates' => [],
];

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

function records_hof_sync_reset(): void
{
	$GLOBALS['k2_hof_sync_samples'] = [
		'values' => [],
		'holders' => [],
		'dates' => [],
	];
}

/** @return list<string> */
function records_hof_sync_samples(string $bucket): array
{
	$samples = $GLOBALS['k2_hof_sync_samples'][$bucket] ?? [];

	return is_array($samples) ? $samples : [];
}

function records_hof_sync_plain_text(string $html): string
{
	$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace('/\s+/u', ' ', trim($text));

	return $text ?? '';
}

function records_hof_sync_track(string $valueHtml, string $holderHtml, string $dateHtml): void
{
	$valueText = records_hof_sync_plain_text($valueHtml);
	if ($valueText !== '') {
		$GLOBALS['k2_hof_sync_samples']['values'][] = $valueText;
	}

	$holderText = records_hof_sync_plain_text($holderHtml);
	if ($holderText !== '' && $holderText !== '-') {
		$GLOBALS['k2_hof_sync_samples']['holders'][] = $holderText;
	}

	$dateText = records_hof_sync_plain_text($dateHtml);
	if ($dateText !== '' && $dateText !== '-') {
		$GLOBALS['k2_hof_sync_samples']['dates'][] = $dateText;
	}
}

function k2_hof_synced_col_ch_from_samples(array $samples, int $paddingCh, int $floorCh): int
{
	$max = 0;
	foreach ($samples as $sample) {
		$len = mb_strlen((string) $sample);
		if ($len > $max) {
			$max = $len;
		}
	}

	return max($floorCh, $max + $paddingCh);
}

/**
 * Shared column widths from static labels + cells rendered in the HoF record table.
 *
 * @return array{label: int, value: int, holder: int, date: int, left_half: int, right_half: int}
 */
function records_hof_sync_compute_widths(array $labels): array
{
	$labelCh = k2_hof_synced_label_col_ch($labels);
	$valueCh = k2_hof_synced_col_ch_from_samples(
		records_hof_sync_samples('values'),
		2,
		mb_strlen('100.0%') + 2
	);
	$holderCh = k2_hof_synced_col_ch_from_samples(
		records_hof_sync_samples('holders'),
		K2_HOF_HOLDER_COL_EXTRA_CH,
		mb_strlen('Eternalstudent') + K2_HOF_HOLDER_COL_EXTRA_CH
	);
	$dateCh = k2_hof_synced_col_ch_from_samples(
		records_hof_sync_samples('dates'),
		2,
		mb_strlen('Sep 30, 2026 (Legendary)') + 2
	);

	return [
		'label' => $labelCh,
		'value' => $valueCh,
		'holder' => $holderCh,
		'date' => $dateCh,
		'left_half' => $labelCh + $valueCh,
		'right_half' => $holderCh + $dateCh,
	];
}

/** Inline style for .server-records-hof (--k2-hof-*-col-ch vars). */
function records_hof_sync_style_attr(array $widths): string
{
	return sprintf(
		'--k2-hof-label-col-ch: %1$dch; --k2-hof-value-col-ch: %2$dch; --k2-hof-holder-col-ch: %3$dch; --k2-hof-date-col-ch: %4$dch;',
		(int) $widths['label'],
		(int) $widths['value'],
		(int) $widths['holder'],
		(int) $widths['date']
	);
}

/** Shared colgroup for HoF record table (table-layout: fixed + CSS vars on .server-records-hof). */
function records_hof_render_colgroup(): void
{
	echo "<colgroup>\n";
	echo '    <col class="k2-hof-col--label">' . "\n";
	echo '    <col class="k2-hof-col--value">' . "\n";
	echo '    <col class="k2-hof-col--holder">' . "\n";
	echo '    <col class="k2-hof-col--date">' . "\n";
	echo "</colgroup>\n";
}
