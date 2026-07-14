<?php
/**
 * Calendar-year peaks and geography scalars (mirrors scripts/amiga/player_geo_year.py).
 *
 * @see docs/amiga-hof-tournament-geo-policy.md
 */
declare(strict_types=1);

/** @var list<string> */
const AMIGA_GEO_RISE_METRICS = [
    'countries_played_in',
    'opponent_countries_faced',
    'opponent_countries_beaten',
];

/**
 * @return list<string>
 */
function amiga_geo_rise_player_columns(): array
{
    $cols = [];
    foreach (AMIGA_GEO_RISE_METRICS as $metric) {
        $cols[] = "{$metric}_last_rise_tournament_id";
        $cols[] = "{$metric}_last_rise_event_date";
    }

    return $cols;
}

/**
 * @return array<string, mixed>
 */
function amiga_geo_empty_rise_fields(): array
{
    $out = [];
    foreach (AMIGA_GEO_RISE_METRICS as $metric) {
        $out["{$metric}_last_rise_tournament_id"] = null;
        $out["{$metric}_last_rise_event_date"] = null;
    }

    return $out;
}

final class AmigaPlayerGeoYearTracker
{
    /** @var array<int, array<int, array{games: int, tournaments: int}>> */
    private array $yearBuckets = [];

    /** @var array<int, array<string, true>> */
    private array $hostCountries = [];

    /** @var array<int, array<string, true>> */
    private array $opponentFaced = [];

    /** @var array<int, array<string, true>> */
    private array $opponentBeaten = [];

    /** @var array<int, array<string, true>> */
    private array $opponentBeatenBy = [];

    /** @var array<int, array<string, int|null>> */
    private array $riseTournamentId = [];

    /** @var array<int, array<string, mixed>> */
    private array $riseEventDate = [];

    public static function normalizeCountry(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    public static function calendarYear(?string $eventDate): ?int
    {
        if ($eventDate === null || $eventDate === '') {
            return null;
        }
        if (strlen($eventDate) >= 4 && ctype_digit(substr($eventDate, 0, 4))) {
            return (int) substr($eventDate, 0, 4);
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $games
     * @param array<int, int> $gamesInEvent
     * @param list<int> $participantIds
     * @param array<int, ?string> $playerCountries
     */
    public function applyTournament(
        int $tournamentId,
        ?string $eventDate,
        ?string $hostCountry,
        array $games,
        array $gamesInEvent,
        array $participantIds,
        array $playerCountries,
    ): void {
        $affected = [];
        foreach ($participantIds as $pid) {
            $pid = (int) $pid;
            if ((int) ($gamesInEvent[$pid] ?? 0) > 0) {
                $affected[$pid] = true;
            }
        }
        foreach ($games as $game) {
            $affected[(int) $game['idA']] = true;
            $affected[(int) $game['idB']] = true;
        }

        $priorCounts = [];
        foreach (array_keys($affected) as $pid) {
            $priorCounts[$pid] = $this->displayGeoCounts($pid, $playerCountries[$pid] ?? null);
        }

        $year = self::calendarYear($eventDate);
        $host = self::normalizeCountry($hostCountry);

        foreach ($participantIds as $pid) {
            $pid = (int) $pid;
            $gamesN = (int) ($gamesInEvent[$pid] ?? 0);
            if ($gamesN <= 0) {
                continue;
            }
            if ($host !== null) {
                $this->hostCountries[$pid][$host] = true;
            }
            if ($year !== null) {
                if (!isset($this->yearBuckets[$pid][$year])) {
                    $this->yearBuckets[$pid][$year] = ['games' => 0, 'tournaments' => 0];
                }
                $this->yearBuckets[$pid][$year]['games'] += $gamesN;
                $this->yearBuckets[$pid][$year]['tournaments'] += 1;
            }
        }

        foreach ($games as $game) {
            $idA = (int) $game['idA'];
            $idB = (int) $game['idB'];
            $goalsA = (int) $game['GoalsA'];
            $goalsB = (int) $game['GoalsB'];
            $countryA = self::normalizeCountry($playerCountries[$idA] ?? null);
            $countryB = self::normalizeCountry($playerCountries[$idB] ?? null);

            if ($countryB !== null) {
                $this->opponentFaced[$idA][$countryB] = true;
            }
            if ($countryA !== null) {
                $this->opponentFaced[$idB][$countryA] = true;
            }
            if ($goalsA > $goalsB && $countryB !== null) {
                $this->opponentBeaten[$idA][$countryB] = true;
            } elseif ($goalsB > $goalsA && $countryA !== null) {
                $this->opponentBeaten[$idB][$countryA] = true;
            }
            if ($goalsA < $goalsB && $countryB !== null) {
                $this->opponentBeatenBy[$idA][$countryB] = true;
            } elseif ($goalsB < $goalsA && $countryA !== null) {
                $this->opponentBeatenBy[$idB][$countryA] = true;
            }
        }

        foreach (array_keys($affected) as $pid) {
            $after = $this->displayGeoCounts($pid, $playerCountries[$pid] ?? null);
            $before = $priorCounts[$pid];
            foreach (AMIGA_GEO_RISE_METRICS as $metric) {
                if ($after[$metric] > $before[$metric]) {
                    $this->riseTournamentId[$pid][$metric] = $tournamentId;
                    $this->riseEventDate[$pid][$metric] = $eventDate;
                }
            }
        }
    }

    /**
     * @return array{
     *   countries_played_in: int,
     *   opponent_countries_faced: int,
     *   opponent_countries_beaten: int,
     *   opponent_countries_beaten_by: int
     * }
     */
    private function displayGeoCounts(int $playerId, ?string $ownCountry): array
    {
        return [
            'countries_played_in' => count($this->hostCountries[$playerId] ?? []),
            'opponent_countries_faced' => count($this->opponentFaced[$playerId] ?? []),
            'opponent_countries_beaten' => count($this->opponentBeaten[$playerId] ?? []),
            'opponent_countries_beaten_by' => count($this->opponentBeatenBy[$playerId] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scalarsFor(int $playerId, ?string $ownCountry): array
    {
        $counts = $this->displayGeoCounts($playerId, $ownCountry);
        [$peakGames, $peakGamesYear] = $this->peakFor($playerId, 'games');
        [$peakEvents, $peakEventsYear] = $this->peakFor($playerId, 'tournaments');

        $out = [
            'peak_year_games' => $peakGames,
            'peak_year_games_year' => $peakGamesYear,
            'peak_year_tournaments' => $peakEvents,
            'peak_year_tournaments_year' => $peakEventsYear,
            'countries_played_in' => $counts['countries_played_in'],
            'opponent_countries_faced' => $counts['opponent_countries_faced'],
            'opponent_countries_beaten' => $counts['opponent_countries_beaten'],
            'opponent_countries_beaten_by' => $counts['opponent_countries_beaten_by'],
        ] + amiga_geo_empty_rise_fields();

        foreach (AMIGA_GEO_RISE_METRICS as $metric) {
            $out["{$metric}_last_rise_tournament_id"] = $this->riseTournamentId[$playerId][$metric] ?? null;
            $out["{$metric}_last_rise_event_date"] = $this->riseEventDate[$playerId][$metric] ?? null;
        }

        return $out;
    }

    /**
     * @return array{0: int, 1: ?int}
     */
    private function peakFor(int $playerId, string $key): array
    {
        $buckets = $this->yearBuckets[$playerId] ?? [];
        if ($buckets === []) {
            return [0, null];
        }
        $bestValue = 0;
        $bestYears = [];
        foreach ($buckets as $year => $bucket) {
            $value = (int) ($bucket[$key] ?? 0);
            if ($value > $bestValue) {
                $bestValue = $value;
                $bestYears = [(int) $year];
            } elseif ($value === $bestValue && $value > 0) {
                $bestYears[] = (int) $year;
            }
        }
        if ($bestValue <= 0 || $bestYears === []) {
            return [0, null];
        }

        return [$bestValue, min($bestYears)];
    }
}

/**
 * @return array<int, ?string>
 */
function amiga_geo_year_load_player_countries(mysqli $con): array
{
    $out = [];
    $res = mysqli_query($con, 'SELECT id, country FROM amiga_players');
    if (!$res) {
        return $out;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $out[(int) $row['id']] = $row['country'] !== null ? (string) $row['country'] : null;
    }
    mysqli_free_result($res);

    return $out;
}

function amiga_geo_year_tracker_through_tournament(mysqli $con, int $throughTournamentId): AmigaPlayerGeoYearTracker
{
    $playerCountries = amiga_geo_year_load_player_countries($con);
    $tracker = new AmigaPlayerGeoYearTracker();

    $stmt = $con->prepare(
        'SELECT tc.event_date, tc.chrono, tc.id, tc.country
         FROM tournaments tc
         WHERE (tc.rating_finalized = 1 OR tc.id = ?)
           AND (tc.event_date, tc.chrono, tc.id) <= (
               SELECT t.event_date, t.chrono, t.id FROM tournaments t WHERE t.id = ? LIMIT 1
           )
         ORDER BY tc.event_date ASC, tc.chrono ASC, tc.id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare geo tracker tournaments: ' . $con->error);
    }
    $stmt->bind_param('ii', $throughTournamentId, $throughTournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute geo tracker tournaments: ' . $con->error);
    }
    $tourRes = $stmt->get_result();
    $tournaments = [];
    while ($tourRes && ($row = $tourRes->fetch_assoc())) {
        $tournaments[] = $row;
    }
    $stmt->close();

    foreach ($tournaments as $tour) {
        $tid = (int) $tour['id'];
        $games = [];
        $gameStmt = $con->prepare(
            'SELECT g.player_a_id AS idA, g.player_b_id AS idB, g.goals_a AS GoalsA, g.goals_b AS GoalsB '
            . 'FROM amiga_games g WHERE g.tournament_id = ? ORDER BY g.game_date ASC, g.id ASC'
        );
        if ($gameStmt !== false) {
            $gameStmt->bind_param('i', $tid);
            if ($gameStmt->execute()) {
                $gameRes = $gameStmt->get_result();
                while ($gameRes && ($game = $gameRes->fetch_assoc())) {
                    $games[] = $game;
                }
            }
            $gameStmt->close();
        }
        $participantIds = [];
        foreach ($games as $game) {
            $participantIds[(int) $game['idA']] = true;
            $participantIds[(int) $game['idB']] = true;
        }
        $participantIds = array_keys($participantIds);
        $gamesInEvent = [];
        $snapStmt = $con->prepare(
            'SELECT player_id, games_in_event FROM amiga_player_event_snapshots WHERE tournament_id = ?'
        );
        if ($snapStmt !== false) {
            $snapStmt->bind_param('i', $tid);
            if ($snapStmt->execute()) {
                $snapRes = $snapStmt->get_result();
                while ($snapRes && ($snap = $snapRes->fetch_assoc())) {
                    $gamesInEvent[(int) $snap['player_id']] = (int) ($snap['games_in_event'] ?? 0);
                }
            }
            $snapStmt->close();
        }
        if ($gamesInEvent === []) {
            foreach ($participantIds as $pid) {
                $gamesInEvent[$pid] = 0;
            }
            foreach ($games as $game) {
                $gamesInEvent[(int) $game['idA']] = ($gamesInEvent[(int) $game['idA']] ?? 0) + 1;
                $gamesInEvent[(int) $game['idB']] = ($gamesInEvent[(int) $game['idB']] ?? 0) + 1;
            }
        }
        $tracker->applyTournament(
            $tid,
            $tour['event_date'] !== null ? (string) $tour['event_date'] : null,
            $tour['country'] !== null ? (string) $tour['country'] : null,
            $games,
            $gamesInEvent,
            $participantIds,
            $playerCountries,
        );
    }

    return $tracker;
}
