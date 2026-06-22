<?php
/**
 * Community stats read helpers (present + time-travel cutoff).
 *
 * @see docs/amiga-community-stats-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_stat_registry.php';
require_once __DIR__ . '/k2_safety.php';

/**
 * @return array<string, mixed>|null
 */
function amiga_community_headline_load(mysqli $con, ?int $cutoffTournamentId = null): ?array
{
    if ($cutoffTournamentId !== null) {
        $cols = implode(', ', array_map(static fn (string $c): string => "`{$c}`", amiga_community_headline_column_names()));
        $stmt = $con->prepare(
            "SELECT {$cols} FROM amiga_community_stats_snapshots WHERE tournament_id = ? LIMIT 1"
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $cutoffTournamentId);
        if (!$stmt->execute()) {
            $stmt->close();

            return null;
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        $stmt->close();

        return $row === false ? null : $row;
    }

    $stmt = $con->prepare('SELECT * FROM amiga_community_stats WHERE id = 1 LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();

    return $row === false ? null : $row;
}

function amiga_community_first_event_label(mysqli $con): string
{
    $res = mysqli_query(
        $con,
        'SELECT MIN(t.event_date) AS first_event FROM tournaments t '
        . 'INNER JOIN amiga_games g ON g.tournament_id = t.id'
    );
    if ($res === false) {
        return 'the first rated game';
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);
    $first = (string) ($row['first_event'] ?? '');
    if ($first === '') {
        return 'the first rated game';
    }
    $ts = strtotime($first);

    return $ts !== false ? date('F j, Y', $ts) : htmlspecialchars($first, ENT_QUOTES, 'UTF-8');
}
