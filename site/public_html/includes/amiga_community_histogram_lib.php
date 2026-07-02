<?php
/**
 * Amiga Activity Shape wing — histogram oracles (read-time, slice 8 probes).
 *
 * @see docs/amiga-activity-charts-policy.md §5.6
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
require_once __DIR__ . '/amiga_player_slice_lib.php';

/** @var list<string> */
const AMIGA_COMMUNITY_HISTOGRAM_KINDS = [
    'career_games',
    'tournaments_played',
    'distinct_opponents',
    'active_years',
    'countries_played',
    'world_cups_played',
    'rating',
    'goal_sum',
    'tournament_games',
];

/**
 * @return array{oracle: string, population_label: string, title: string}
 */
function amiga_community_histogram_kind_meta(string $kind): array
{
    amiga_community_histogram_validate_kind($kind);

    return match ($kind) {
        'career_games' => [
            'oracle' => 'player_snapshot',
            'population_label' => 'players',
            'title' => 'Career games',
        ],
        'tournaments_played' => [
            'oracle' => 'player_snapshot',
            'population_label' => 'players',
            'title' => 'Tournaments played',
        ],
        'distinct_opponents' => [
            'oracle' => 'player_snapshot',
            'population_label' => 'players',
            'title' => 'Distinct opponents',
        ],
        'active_years' => [
            'oracle' => 'game_scan',
            'population_label' => 'players',
            'title' => 'Active calendar years',
        ],
        'countries_played' => [
            'oracle' => 'player_snapshot',
            'population_label' => 'players',
            'title' => 'Countries played in',
        ],
        'world_cups_played' => [
            'oracle' => 'player_snapshot+slice',
            'population_label' => 'players',
            'title' => 'World Cups played',
        ],
        'rating' => [
            'oracle' => 'player_snapshot',
            'population_label' => 'players',
            'title' => 'Rating',
        ],
        'goal_sum' => [
            'oracle' => 'game_scan',
            'population_label' => 'games',
            'title' => 'Total goals per game',
        ],
        'tournament_games' => [
            'oracle' => 'tournament_scan',
            'population_label' => 'tournaments',
            'title' => 'Rated games per tournament',
        ],
    };
}

/**
 * Locked bucket definitions (policy §5.6 defaults).
 *
 * @return array{type: string, buckets?: list<array<string, mixed>>}
 */
function amiga_community_histogram_bucket_defs(string $kind): array
{
    amiga_community_histogram_validate_kind($kind);

    return match ($kind) {
        'career_games' => [
            'type' => 'ranges',
            'buckets' => [
                ['label' => '1–9', 'min' => 1, 'max' => 9],
                ['label' => '10–24', 'min' => 10, 'max' => 24],
                ['label' => '25–49', 'min' => 25, 'max' => 49],
                ['label' => '50–99', 'min' => 50, 'max' => 99],
                ['label' => '100–249', 'min' => 100, 'max' => 249],
                ['label' => '250–499', 'min' => 250, 'max' => 499],
                ['label' => '500–999', 'min' => 500, 'max' => 999],
                ['label' => '1000+', 'min' => 1000, 'max' => null],
            ],
        ],
        'tournaments_played' => [
            'type' => 'ranges',
            'buckets' => [
                ['label' => '1', 'min' => 1, 'max' => 1],
                ['label' => '2–4', 'min' => 2, 'max' => 4],
                ['label' => '5–9', 'min' => 5, 'max' => 9],
                ['label' => '10–19', 'min' => 10, 'max' => 19],
                ['label' => '20–49', 'min' => 20, 'max' => 49],
                ['label' => '50+', 'min' => 50, 'max' => null],
            ],
        ],
        'distinct_opponents' => [
            'type' => 'ranges',
            'buckets' => [
                ['label' => '1–4', 'min' => 1, 'max' => 4],
                ['label' => '5–9', 'min' => 5, 'max' => 9],
                ['label' => '10–19', 'min' => 10, 'max' => 19],
                ['label' => '20–49', 'min' => 20, 'max' => 49],
                ['label' => '50–99', 'min' => 50, 'max' => 99],
                ['label' => '100+', 'min' => 100, 'max' => null],
            ],
        ],
        'active_years' => [
            'type' => 'exact',
            'min' => 1,
            'max' => 25,
        ],
        'countries_played' => [
            'type' => 'exact',
            'min' => 1,
            'max' => 12,
        ],
        'world_cups_played' => [
            'type' => 'exact',
            'min' => 0,
            'max' => 24,
        ],
        'rating' => [
            'type' => 'step',
            'min' => 650,
            'max' => 2450,
            'step' => 50,
            'tail_label' => '2450+',
        ],
        'goal_sum' => [
            'type' => 'exact_tail',
            'min' => 0,
            'max' => 14,
            'tail_label' => '15+',
            'tail_min' => 15,
        ],
        'tournament_games' => [
            'type' => 'ranges',
            'buckets' => [
                ['label' => '1–9', 'min' => 1, 'max' => 9],
                ['label' => '10–24', 'min' => 10, 'max' => 24],
                ['label' => '25–49', 'min' => 25, 'max' => 49],
                ['label' => '50–99', 'min' => 50, 'max' => 99],
                ['label' => '100+', 'min' => 100, 'max' => null],
            ],
        ],
    };
}

function amiga_community_histogram_validate_kind(string $kind): void
{
    if (!in_array($kind, AMIGA_COMMUNITY_HISTOGRAM_KINDS, true)) {
        throw new InvalidArgumentException('Unknown histogram kind: ' . $kind);
    }
}

/**
 * Cutoff bind tuple for tournament-scoped scans (empty when present).
 *
 * @return array{
 *   active: bool,
 *   label: string,
 *   types: string,
 *   params: list<int|float|string>,
 *   cutoff: array<string, mixed>|null
 * }
 */
function amiga_community_histogram_cutoff_bind(?AmigaSnapshotContext $ctx = null): array
{
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    if (!$ctx->isActive()) {
        return [
            'active' => false,
            'label' => 'present',
            'types' => '',
            'params' => [],
            'cutoff' => null,
        ];
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [
            'active' => false,
            'label' => 'present',
            'types' => '',
            'params' => [],
            'cutoff' => null,
        ];
    }

    $label = $ctx->label();
    if ($label === null || $label === '') {
        $as = $ctx->asParam();
        $label = $as ?? ('tournament:' . (int) $cutoff['tournament_id']);
    }

    return [
        'active' => true,
        'label' => $label,
        'types' => 'sdi',
        'params' => [
            (string) $cutoff['event_date'],
            (float) $cutoff['chrono'],
            (int) $cutoff['tournament_id'],
        ],
        'cutoff' => $cutoff,
    ];
}

/**
 * @return list<int>
 */
function amiga_community_histogram_fetch_int_column(mysqli $con, string $sql, string $types, array $params): array
{
    if ($types !== '') {
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare histogram: ' . $con->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('execute histogram: ' . $err);
        }
        $res = $stmt->get_result();
    } else {
        $res = $con->query($sql);
        if ($res === false) {
            throw new RuntimeException('query histogram: ' . $con->error);
        }
    }

    $values = [];
    while ($row = $res->fetch_assoc()) {
        $values[] = (int) ($row['v'] ?? 0);
    }
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }

    return $values;
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_player_snapshot_column(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    string $columnSql
): array {
    $where = 's.NumberGames > 0';

    if (!$ctx->isActive()) {
        $sql = 'SELECT CAST(' . $columnSql . ' AS SIGNED) AS v '
            . amiga_player_base_from_sql($con, 's')
            . ' WHERE ' . $where;

        return amiga_community_histogram_fetch_int_column($con, $sql, '', []);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $sql = 'SELECT CAST(' . $columnSql . ' AS SIGNED) AS v '
        . amiga_lb_snapshot_from_sql('s')
        . ' WHERE ' . $where;
    $types = 'sdi';
    $params = [
        (string) $cutoff['event_date'],
        (float) $cutoff['chrono'],
        (int) $cutoff['tournament_id'],
    ];

    return amiga_community_histogram_fetch_int_column($con, $sql, $types, $params);
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_world_cups_played(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $sliceKey = amiga_slice_key_world_cup();

    if (!$ctx->isActive()) {
        $sql = 'SELECT CAST(COALESCE(wcs.tournaments_played, 0) AS SIGNED) AS v '
            . amiga_player_base_from_sql($con, 's')
            . ' LEFT JOIN amiga_player_slice_totals wcs ON wcs.player_id = p.id AND wcs.slice_key = ? '
            . 'WHERE s.NumberGames > 0';
        $types = 's';
        $params = [$sliceKey];

        return amiga_community_histogram_fetch_int_column($con, $sql, $types, $params);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $sql = 'SELECT CAST(COALESCE(wcs.tournaments_played, 0) AS SIGNED) AS v '
        . amiga_lb_snapshot_from_sql('s')
        . ' LEFT JOIN ('
        . '  SELECT x.player_id, x.tournaments_played FROM ('
        . '    SELECT se.player_id, se.tournaments_played,'
        . '           ROW_NUMBER() OVER ('
        . '             PARTITION BY se.player_id'
        . '             ORDER BY se.event_date DESC, se.event_chrono DESC, se.as_of_tournament_id DESC'
        . '           ) AS rn'
        . '    FROM amiga_player_slice_at_event se'
        . '    WHERE se.slice_key = ?'
        . '      AND (se.event_date, se.event_chrono, se.as_of_tournament_id) <= (?, ?, ?)'
        . '  ) x WHERE x.rn = 1'
        . ') wcs ON wcs.player_id = p.id '
        . 'WHERE s.NumberGames > 0';

    $types = 'sdissdi';
    $params = [
        (string) $cutoff['event_date'],
        (float) $cutoff['chrono'],
        (int) $cutoff['tournament_id'],
        $sliceKey,
        (string) $cutoff['event_date'],
        (float) $cutoff['chrono'],
        (int) $cutoff['tournament_id'],
    ];

    return amiga_community_histogram_fetch_int_column($con, $sql, $types, $params);
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_active_years(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $gameCutoff = ' INNER JOIN tournaments t ON t.id = g.tournament_id ';
    $types = '';
    $params = [];
    $cutoffFilter = '';

    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return [];
        }
        $cutoffFilter = ' WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?) ';
        $types = 'sdi';
        $params = [
            (string) $cutoff['event_date'],
            (float) $cutoff['chrono'],
            (int) $cutoff['tournament_id'],
        ];
    }

    $yearsSql = 'SELECT player_id, COUNT(DISTINCT yr) AS v FROM ('
        . 'SELECT g.player_a_id AS player_id, YEAR(g.game_date) AS yr FROM amiga_games g'
        . $gameCutoff
        . $cutoffFilter
        . ' UNION ALL '
        . 'SELECT g.player_b_id AS player_id, YEAR(g.game_date) AS yr FROM amiga_games g'
        . $gameCutoff
        . $cutoffFilter
        . ') u GROUP BY player_id';

    if (!$ctx->isActive()) {
        $sql = 'SELECT CAST(COALESCE(y.v, 0) AS SIGNED) AS v '
            . amiga_player_base_from_sql($con, 's')
            . ' LEFT JOIN (' . $yearsSql . ') y ON y.player_id = p.id '
            . 'WHERE s.NumberGames > 0';

        return amiga_community_histogram_fetch_int_column($con, $sql, '', []);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return [];
    }

    $yearTypes = 'sdisdi';
    $yearParams = array_merge($params, $params);

    $sql = 'SELECT CAST(COALESCE(y.v, 0) AS SIGNED) AS v '
        . amiga_lb_snapshot_from_sql('s')
        . ' LEFT JOIN (' . $yearsSql . ') y ON y.player_id = p.id '
        . 'WHERE s.NumberGames > 0';

    $types = 'sdi' . $yearTypes;
    $bind = [
        (string) $cutoff['event_date'],
        (float) $cutoff['chrono'],
        (int) $cutoff['tournament_id'],
        ...$yearParams,
    ];

    return amiga_community_histogram_fetch_int_column($con, $sql, $types, $bind);
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_goal_sum(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $bind = amiga_community_histogram_cutoff_bind($ctx);
    $sql = 'SELECT CAST(COALESCE(gr.sum_of_goals, g.goals_a + g.goals_b) AS SIGNED) AS v '
        . 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings gr ON gr.game_id = g.id '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id ';

    if ($bind['active']) {
        $sql .= 'WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?)';

        return amiga_community_histogram_fetch_int_column($con, $sql, $bind['types'], $bind['params']);
    }

    return amiga_community_histogram_fetch_int_column($con, $sql, '', []);
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_tournament_games(mysqli $con, AmigaSnapshotContext $ctx): array
{
    $bind = amiga_community_histogram_cutoff_bind($ctx);
    $sql = 'SELECT CAST(COUNT(*) AS SIGNED) AS v '
        . 'FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id ';

    if ($bind['active']) {
        $sql .= 'WHERE (t.event_date, t.chrono, t.id) <= (?, ?, ?) ';
    }

    $sql .= 'GROUP BY g.tournament_id';

    return amiga_community_histogram_fetch_int_column($con, $sql, $bind['active'] ? $bind['types'] : '', $bind['params']);
}

/**
 * @return list<int>
 */
function amiga_community_histogram_raw_values(
    mysqli $con,
    string $kind,
    ?AmigaSnapshotContext $ctx = null
): array {
    amiga_community_histogram_validate_kind($kind);
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();

    return match ($kind) {
        'career_games' => amiga_community_histogram_raw_player_snapshot_column($con, $ctx, 's.NumberGames'),
        'tournaments_played' => amiga_community_histogram_raw_player_snapshot_column($con, $ctx, 's.tournaments_played'),
        'distinct_opponents' => amiga_community_histogram_raw_player_snapshot_column($con, $ctx, 's.DifferentOpponents'),
        'countries_played' => amiga_community_histogram_raw_player_snapshot_column($con, $ctx, 's.countries_played_in'),
        'rating' => amiga_community_histogram_raw_player_snapshot_column($con, $ctx, 's.Rating'),
        'world_cups_played' => amiga_community_histogram_raw_world_cups_played($con, $ctx),
        'active_years' => amiga_community_histogram_raw_active_years($con, $ctx),
        'goal_sum' => amiga_community_histogram_raw_goal_sum($con, $ctx),
        'tournament_games' => amiga_community_histogram_raw_tournament_games($con, $ctx),
    };
}

/**
 * @param list<int> $values
 * @return list<array{label: string, count: int}>
 */
function amiga_community_histogram_bucketize(string $kind, array $values): array
{
    $defs = amiga_community_histogram_bucket_defs($kind);
    $type = (string) ($defs['type'] ?? '');

    if ($type === 'ranges') {
        /** @var list<array{label: string, min: int, max: int|null}> $buckets */
        $buckets = $defs['buckets'] ?? [];
        $counts = [];
        foreach ($buckets as $bucket) {
            $counts[$bucket['label']] = 0;
        }
        foreach ($values as $value) {
            foreach ($buckets as $bucket) {
                $min = (int) $bucket['min'];
                $max = $bucket['max'];
                if ($value < $min) {
                    continue;
                }
                if ($max === null || $value <= $max) {
                    $counts[$bucket['label']]++;
                    break;
                }
            }
        }

        $out = [];
        foreach ($buckets as $bucket) {
            $out[] = ['label' => $bucket['label'], 'count' => $counts[$bucket['label']]];
        }

        return $out;
    }

    if ($type === 'exact') {
        $min = (int) ($defs['min'] ?? 0);
        $max = (int) ($defs['max'] ?? 0);
        $counts = [];
        for ($n = $min; $n <= $max; $n++) {
            $counts[(string) $n] = 0;
        }
        foreach ($values as $value) {
            if ($value < $min || $value > $max) {
                continue;
            }
            $key = (string) $value;
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }
        $out = [];
        for ($n = $min; $n <= $max; $n++) {
            $out[] = ['label' => (string) $n, 'count' => $counts[(string) $n]];
        }

        return $out;
    }

    if ($type === 'exact_tail') {
        $min = (int) ($defs['min'] ?? 0);
        $max = (int) ($defs['max'] ?? 0);
        $tailLabel = (string) ($defs['tail_label'] ?? ($max + 1) . '+');
        $tailMin = (int) ($defs['tail_min'] ?? $max + 1);
        $counts = [];
        for ($n = $min; $n <= $max; $n++) {
            $counts[(string) $n] = 0;
        }
        $counts[$tailLabel] = 0;
        foreach ($values as $value) {
            if ($value >= $tailMin) {
                $counts[$tailLabel]++;
                continue;
            }
            if ($value >= $min && $value <= $max) {
                $counts[(string) $value]++;
            }
        }
        $out = [];
        for ($n = $min; $n <= $max; $n++) {
            $out[] = ['label' => (string) $n, 'count' => $counts[(string) $n]];
        }
        $out[] = ['label' => $tailLabel, 'count' => $counts[$tailLabel]];

        return $out;
    }

    if ($type === 'step') {
        $min = (int) ($defs['min'] ?? 0);
        $max = (int) ($defs['max'] ?? 0);
        $step = (int) ($defs['step'] ?? 50);
        $tailLabel = (string) ($defs['tail_label'] ?? ($max + 1) . '+');
        $labels = [];
        $counts = [];
        for ($lo = $min; $lo <= $max - $step; $lo += $step) {
            $hi = $lo + $step - 1;
            $label = $lo . '–' . $hi;
            $labels[] = $label;
            $counts[$label] = 0;
        }
        $counts[$tailLabel] = 0;
        foreach ($values as $value) {
            if ($value >= $max) {
                $counts[$tailLabel]++;
                continue;
            }
            if ($value < $min) {
                continue;
            }
            $placed = false;
            foreach ($labels as $label) {
                [$loStr, $hiStr] = explode('–', $label, 2);
                $lo = (int) $loStr;
                $hi = (int) $hiStr;
                if ($value >= $lo && $value <= $hi) {
                    $counts[$label]++;
                    $placed = true;
                    break;
                }
            }
            if (!$placed && $labels !== []) {
                $last = $labels[count($labels) - 1];
                $counts[$last]++;
            }
        }
        $out = [];
        foreach ($labels as $label) {
            $out[] = ['label' => $label, 'count' => $counts[$label]];
        }
        $out[] = ['label' => $tailLabel, 'count' => $counts[$tailLabel]];

        return $out;
    }

    throw new InvalidArgumentException('Unknown bucket type for kind: ' . $kind);
}

/**
 * @return array{
 *   kind: string,
 *   population: int,
 *   population_label: string,
 *   buckets: list<array{label: string, count: int}>,
 *   oracle: string
 * }
 */
function amiga_community_histogram_compute(
    mysqli $con,
    string $kind,
    ?AmigaSnapshotContext $ctx = null
): array {
    $meta = amiga_community_histogram_kind_meta($kind);
    $values = amiga_community_histogram_raw_values($con, $kind, $ctx);

    return [
        'kind' => $kind,
        'population' => count($values),
        'population_label' => $meta['population_label'],
        'buckets' => amiga_community_histogram_bucketize($kind, $values),
        'oracle' => $meta['oracle'],
    ];
}

/**
 * @return array{
 *   kind: string,
 *   cutoff: string,
 *   ms: float,
 *   population: int,
 *   oracle: string,
 *   max_value: int
 * }
 */
function amiga_community_histogram_probe(
    mysqli $con,
    string $kind,
    ?AmigaSnapshotContext $ctx = null
): array {
    $meta = amiga_community_histogram_kind_meta($kind);
    $bind = amiga_community_histogram_cutoff_bind($ctx);
    $t0 = hrtime(true);
    $values = amiga_community_histogram_raw_values($con, $kind, $ctx);
    $ms = (hrtime(true) - $t0) / 1e6;
    $maxValue = $values === [] ? 0 : max($values);

    return [
        'kind' => $kind,
        'cutoff' => $bind['label'],
        'ms' => round($ms, 2),
        'population' => count($values),
        'oracle' => $meta['oracle'],
        'max_value' => $maxValue,
    ];
}
