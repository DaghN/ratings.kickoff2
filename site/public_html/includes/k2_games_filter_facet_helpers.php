<?php
/**
 * Shared helpers for faceted games filter listbox counts.
 */
declare(strict_types=1);

/** @return list<array<string, mixed>> */
function k2_games_facet_query_rows(mysqli $con, string $sql, string $types, array $params): array
{
	$stmt = $con->prepare($sql);
	if ($stmt === false) {
		return [];
	}

	if ($types !== '') {
		$refs = [];
		foreach ($params as $key => $value) {
			$refs[$key] = &$params[$key];
		}
		$stmt->bind_param($types, ...$refs);
	}

	if (!$stmt->execute()) {
		$stmt->close();

		return [];
	}

	$res = $stmt->get_result();
	if ($res === false) {
		$stmt->close();

		return [];
	}

	$rows = [];
	while ($row = $res->fetch_assoc()) {
		$rows[] = $row;
	}
	$res->free();
	$stmt->close();

	return $rows;
}

/**
 * Fill zero-count gaps between min and max values that have count > 0 (keep interior gaps).
 *
 * @param array<int, int> $sparse
 * @return array<int, int>
 */
function k2_games_facet_expand_numeric_gaps(array $sparse): array
{
	$positiveKeys = [];
	foreach ($sparse as $value => $count) {
		if ((int) $count > 0) {
			$positiveKeys[(int) $value] = true;
		}
	}
	if ($positiveKeys === []) {
		return [];
	}

	$min = min(array_keys($positiveKeys));
	$max = max(array_keys($positiveKeys));
	$out = [];
	for ($v = $min; $v <= $max; $v++) {
		$out[$v] = (int) ($sparse[$v] ?? 0);
	}

	return $out;
}

/** @param array<int, int> $counts */
function k2_games_facet_inject_selected_numeric(array $counts, int $selected): array
{
	if ($selected >= 0 && !array_key_exists($selected, $counts)) {
		$counts[$selected] = 0;
	}

	return $counts;
}

/**
 * @param array<int, int> $counts
 * @return list<array{value: string, label: string, meta: string}>
 */
function k2_games_facet_numeric_choices(
	array $counts,
	string $idleValue,
	bool $desc,
	?callable $labelFn = null
): array {
	$choices = [['value' => $idleValue, 'label' => '', 'meta' => '']];
	if ($counts === []) {
		return $choices;
	}

	$keys = array_keys($counts);
	if ($desc) {
		rsort($keys, SORT_NUMERIC);
	} else {
		sort($keys, SORT_NUMERIC);
	}

	foreach ($keys as $value) {
		$label = $labelFn !== null ? (string) $labelFn((int) $value) : (string) $value;
		$choices[] = [
			'value' => (string) $value,
			'label' => $label,
			'meta' => (string) (int) $counts[$value],
		];
	}

	return $choices;
}