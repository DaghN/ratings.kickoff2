<?php
/**
 * Query helpers for amiga/games.php (mirrors player/games.php filters).
 */
declare(strict_types=1);

function amiga_games_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function amiga_games_valid_result(string $value): string
{
    return in_array($value, ['all', 'win', 'draw', 'loss'], true) ? $value : 'all';
}

function amiga_games_valid_direction(string $value): string
{
    return strtolower($value) === 'asc' ? 'asc' : 'desc';
}

function amiga_games_build_url(array $params): string
{
    return '/amiga/games.php?' . http_build_query($params);
}

function amiga_games_query_all(mysqli $con, string $sql, string $types = '', array $params = []): array
{
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Query failed: ' . $con->error);
    }

    if ($types !== '') {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $stmt->bind_param($types, ...$refs);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        throw new RuntimeException('Query failed: ' . $con->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function amiga_games_valid_day(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
        return $value;
    }

    return '';
}

function amiga_games_where_clause(
    int $playerId,
    string $resultFilter,
    int $opponentId,
    string $utcDay,
    string &$types,
    array &$params
): string {
    $where = ['(r.idA = ? OR r.idB = ?)'];
    $types = 'ii';
    $params = [$playerId, $playerId];

    if ($utcDay !== '') {
        $where[] = 'DATE(r.`Date`) = ?';
        $types .= 's';
        $params[] = $utcDay;
    }

    if ($resultFilter === 'win') {
        $where[] = '((r.idA = ? AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore) < 0.001))';
        $types .= 'ii';
        $params[] = $playerId;
        $params[] = $playerId;
    } elseif ($resultFilter === 'draw') {
        $where[] = 'ABS(r.ActualScore - 0.5) < 0.001';
    } elseif ($resultFilter === 'loss') {
        $where[] = '((r.idA = ? AND ABS(r.ActualScore) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore - 1.0) < 0.001))';
        $types .= 'ii';
        $params[] = $playerId;
        $params[] = $playerId;
    }

    if ($opponentId > 0) {
        $where[] = '((r.idA = ? AND r.idB = ?) OR (r.idB = ? AND r.idA = ?))';
        $types .= 'iiii';
        $params[] = $playerId;
        $params[] = $opponentId;
        $params[] = $playerId;
        $params[] = $opponentId;
    }

    return implode(' AND ', $where);
}

function amiga_games_sort_header(string $key, string $label, string $align, array $state, string $help, string $tooltipLabel = '', string $extraClass = ''): string
{
    $isActive = $state['sort'] === $key;
    $nextDir = $isActive && $state['dir'] === 'desc' ? 'asc' : 'desc';
    $classes = ['k2-table-sortable'];
    if ($align === 'left') {
        $classes[] = 'k2-table-cell--left';
    }
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($isActive) {
        $classes[] = $state['dir'] === 'desc' ? 'k2-table-sorted-desc' : 'k2-table-sorted-asc';
    }

    $params = [
        'id' => $state['player_id'],
        'sort' => $key,
        'dir' => $nextDir,
    ];
    if ($state['result'] !== 'all') {
        $params['result'] = $state['result'];
    }
    if ($state['opponent'] > 0) {
        $params['opponent'] = $state['opponent'];
    }
    if (!empty($state['day'])) {
        $params['day'] = $state['day'];
    }

    $aria = $isActive ? ($state['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
    $attrs = [
        'class="' . implode(' ', $classes) . '"',
        'aria-sort="' . $aria . '"',
        'data-k2-help="' . amiga_games_h($help) . '"',
    ];
    if ($tooltipLabel !== '') {
        $attrs[] = 'data-k2-tooltip-label="' . amiga_games_h($tooltipLabel) . '"';
    }

    return '<th ' . implode(' ', $attrs) . '>'
        . '<a href="' . amiga_games_h(amiga_games_build_url($params)) . '">' . $label . '</a>'
        . '</th>';
}
