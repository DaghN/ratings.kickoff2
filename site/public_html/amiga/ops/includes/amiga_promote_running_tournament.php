<?php
declare(strict_types=1);

/**
 * Promote running fixture scores into amiga_games at Make official (RTB).
 */

const AMIGA_PROMOTE_LIVE_SOURCE_SCORES_ID_BASE = 1000000000;

function amiga_promote_next_live_source_scores_id(mysqli $con): int
{
    $base = AMIGA_PROMOTE_LIVE_SOURCE_SCORES_ID_BASE;
    $stmt = $con->prepare(
        'SELECT COALESCE(MAX(source_scores_id), ? - 1) AS max_id '
        . 'FROM amiga_games WHERE source_scores_id >= ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote next source id: ' . $con->error);
    }
    $stmt->bind_param('ii', $base, $base);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote next source id: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['max_id'] ?? ($base - 1)) + 1;
}

function amiga_promote_next_game_date(mysqli $con): string
{
    $res = $con->query(
        "SELECT COALESCE("
        . "DATE_FORMAT(DATE_ADD(MAX(game_date), INTERVAL 1 SECOND), '%Y-%m-%d %H:%i:%s'), "
        . "DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%d %H:%i:%s')) AS next_game_date "
        . "FROM amiga_games"
    );
    if ($res === false) {
        throw new RuntimeException('promote next game date: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();

    return (string) ($row['next_game_date'] ?? gmdate('Y-m-d H:i:s'));
}

/**
 * Next tournament chrono at promote — mirrors scripts/amiga/tournament_fixtures.py
 * next_tournament_chrono: bump within same event_date, else global append.
 */
function amiga_promote_next_tournament_chrono(
    mysqli $con,
    string $eventDate,
    int $excludeTournamentId
): float {
    $stmt = $con->prepare(
        'SELECT COALESCE(MAX(chrono), 0) AS same_day_max '
        . 'FROM tournaments WHERE event_date = ? AND id <> ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote same-day chrono: ' . $con->error);
    }
    $stmt->bind_param('si', $eventDate, $excludeTournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote same-day chrono: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $sameDayMax = (float) ($row['same_day_max'] ?? 0);

    $stmt = $con->prepare(
        'SELECT COALESCE(MAX(chrono), 0) AS global_max FROM tournaments WHERE id <> ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote global chrono: ' . $con->error);
    }
    $stmt->bind_param('i', $excludeTournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote global chrono: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $globalMax = (float) ($row['global_max'] ?? 0);

    if ($sameDayMax > 0.0) {
        return $sameDayMax + 1.0;
    }

    return $globalMax + 1.0;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_promote_running_tournament_load_played_fixtures(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT f.id AS fixture_id, f.player_a_id, f.player_b_id, f.goals_a, f.goals_b, '
        . 'f.extra, f.phase_label, f.leg_no, s.tournament_id '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND f.status = ? '
        . 'AND f.player_a_id IS NOT NULL AND f.player_b_id IS NOT NULL '
        . 'AND f.goals_a IS NOT NULL AND f.goals_b IS NOT NULL '
        . 'ORDER BY s.sequence_no ASC, s.id ASC, f.leg_no ASC, f.id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote fixtures: ' . $con->error);
    }
    $played = 'played';
    $stmt->bind_param('is', $tournamentId, $played);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote fixtures: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

/**
 * @return array{promoted:int,game_ids:list<int>,skipped:bool,skip_reason:?string}
 */
function amiga_promote_running_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_running_tournament_lib.php';

    $stmt = $con->prepare(
        'SELECT id, source_id, format_overrides, rating_finalized, lifecycle_status, chrono, event_date '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote tournament: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote tournament: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $tournament = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($tournament === null) {
        throw new RuntimeException("Tournament {$tournamentId} not found.");
    }
    if (!amiga_running_tournament_is_live_ops_generated([
        'source_id' => $tournament['source_id'] !== null ? (int) $tournament['source_id'] : null,
        'format_overrides' => $tournament['format_overrides'],
    ])) {
        throw new RuntimeException("Tournament {$tournamentId} is not a live-ops generated tournament.");
    }
    if ((int) ($tournament['rating_finalized'] ?? 0) === 1) {
        return ['promoted' => 0, 'game_ids' => [], 'skipped' => true, 'skip_reason' => 'already_finalized'];
    }

    if ($tournament['chrono'] === null && $tournament['event_date'] !== null) {
        $eventDate = (string) $tournament['event_date'];
        $nextChrono = amiga_promote_next_tournament_chrono($con, $eventDate, $tournamentId);
        $stmt = $con->prepare('UPDATE tournaments SET chrono = ? WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare promote chrono update: ' . $con->error);
        }
        $stmt->bind_param('di', $nextChrono, $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute promote chrono update: ' . $stmt->error);
        }
        $stmt->close();
    }

    $officialCount = 0;
    $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare promote official count: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote official count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $officialCount = (int) ($row['n'] ?? 0);
    if ($officialCount > 0) {
        return ['promoted' => 0, 'game_ids' => [], 'skipped' => true, 'skip_reason' => 'games_already_exist'];
    }

    $scheduled = 0;
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND f.status = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare promote scheduled count: ' . $con->error);
    }
    $scheduledStatus = 'scheduled';
    $stmt->bind_param('is', $tournamentId, $scheduledStatus);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute promote scheduled count: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $scheduled = (int) ($row['n'] ?? 0);
    if ($scheduled > 0) {
        // Partial finish: caller should have voided remaining; refuse if any left.
        throw new RuntimeException(
            "Tournament {$tournamentId} has {$scheduled} scheduled fixture(s); "
            . 'void unplayed matches before promote (Finish and make official does this).'
        );
    }

    $fixtures = amiga_promote_running_tournament_load_played_fixtures($con, $tournamentId);
    if ($fixtures === []) {
        throw new RuntimeException("Tournament {$tournamentId} has no played fixtures to promote.");
    }

    $gameIds = [];
    $con->begin_transaction();
    try {
        foreach ($fixtures as $fixture) {
            $sourceScoresId = amiga_promote_next_live_source_scores_id($con);
            $gameDate = amiga_promote_next_game_date($con);
            $fixtureId = (int) $fixture['fixture_id'];
            $playerAId = (int) $fixture['player_a_id'];
            $playerBId = (int) $fixture['player_b_id'];
            $goalsA = (int) $fixture['goals_a'];
            $goalsB = (int) $fixture['goals_b'];
            $extra = $fixture['extra'] !== null ? (string) $fixture['extra'] : null;
            $phase = $fixture['phase_label'] !== null ? (string) $fixture['phase_label'] : null;

            $stmt = $con->prepare(
                'INSERT INTO amiga_games '
                . '(source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id, phase, goals_a, goals_b, extra) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if ($stmt === false) {
                throw new RuntimeException('prepare promote game insert: ' . $con->error);
            }
            $stmt->bind_param(
                'isiiiisiis',
                $sourceScoresId,
                $gameDate,
                $playerAId,
                $playerBId,
                $tournamentId,
                $fixtureId,
                $phase,
                $goalsA,
                $goalsB,
                $extra
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('execute promote game insert: ' . $stmt->error);
            }
            $gameIds[] = (int) $stmt->insert_id;
            $stmt->close();
        }
        if ($dryRun) {
            $con->rollback();
        } else {
            $con->commit();
        }
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return [
        'promoted' => count($gameIds),
        'game_ids' => $gameIds,
        'skipped' => false,
        'skip_reason' => null,
    ];
}