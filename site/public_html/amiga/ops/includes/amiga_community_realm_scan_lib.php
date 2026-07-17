<?php
/**
 * Community realm scan at tournament cutoff (mirrors scripts/amiga/community_stat_facts.py).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_community_stat_registry.php';
require_once __DIR__ . '/amiga_community_game_metrics.php';
require_once __DIR__ . '/amiga_honours_totals_lib.php';
require_once __DIR__ . '/amiga_realm_snapshot_lib.php';

function amiga_community_country_token(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim($value);

    return $text !== '' ? $text : null;
}

/**
 * @return array{
 *   facts: list<array<string, mixed>>,
 *   tournaments_finalized: int,
 *   distinct_host_countries: int,
 *   wc_games_played: int,
 *   distinct_opponent_pairs: int,
 *   players_debuted: int
 * }
 */
function amiga_community_build_realm_scan(mysqli $con, int $tournamentId): array
{
    $cutoff = amiga_realm_load_cutoff($con, $tournamentId);
    $cutoffWhere = amiga_realm_game_cutoff_sql('t');
    $eventDate = $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];

    $sql = "
        SELECT g.id AS game_id, g.player_a_id, g.player_b_id, g.goals_a, g.goals_b, g.phase,
               t.event_date, t.country AS host_country, t.name AS tournament_name, t.is_world_cup,
               pa.country AS country_a, pb.country AS country_b,
               r.sum_of_goals, r.actual_score,
               r.dd_player_a, r.dd_player_b, r.cs_player_a, r.cs_player_b
        FROM amiga_games g
        INNER JOIN amiga_game_ratings r ON r.game_id = g.id
        INNER JOIN tournaments t ON t.id = g.tournament_id
        INNER JOIN amiga_players pa ON pa.id = g.player_a_id
        INNER JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE {$cutoffWhere}
        ORDER BY g.game_date ASC, g.id ASC
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare community realm scan games: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute community realm scan games: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $stmt->close();

    /** @var array<string, float> $values */
    $values = [];
    /** @var array<string, array<int, true>> $activePlayers */
    $activePlayers = [];
    /** @var array<string, array<int, true>> $activePlayersByNationality year\0country => player ids */
    $activePlayersByNationality = [];
    /** @var array<string, array<int, true>> $activePlayersWcByNationality year\0country => player ids (WC games only) */
    $activePlayersWcByNationality = [];
    /** @var array<string, array<int, true>> $activePlayersByNationalityAllTime */
    $activePlayersByNationalityAllTime = [];
    /** @var array<string, array<string, true>> $nationalitiesByYear */
    $nationalitiesByYear = [];
    /** @var array<string, array<string, true>> $nationalitiesWcByYear */
    $nationalitiesWcByYear = [];
    /** @var array<string, array<string, true>> $hostCountriesByYear */
    $hostCountriesByYear = [];
    /** @var array<string, array<string, true>> $pairsByYear */
    $pairsByYear = [];
    /** @var array<string, true> $pairsCumulative */
    $pairsCumulative = [];
    /** @var array<int, string> $debutYearByPlayer */
    $debutYearByPlayer = [];
    /** @var array<int, string> $debutCountryByPlayer */
    $debutCountryByPlayer = [];
    $wcGamesPlayed = 0;

    $factKey = static function (
        string $periodType,
        string $periodKey,
        string $sliceType,
        string $sliceKey,
        string $metricKey,
        string $countBasis,
    ): string {
        return implode("\0", [$periodType, $periodKey, $sliceType, $sliceKey, $metricKey, $countBasis]);
    };

    $bump = static function (string $key, float $delta) use (&$values): void {
        if ($delta == 0.0) {
            return;
        }
        $values[$key] = ($values[$key] ?? 0.0) + $delta;
    };

    while ($row = $res->fetch_assoc()) {
        $year = amiga_community_year_key(isset($row['event_date']) ? (string) $row['event_date'] : null);
        $hostCountry = amiga_community_country_token(isset($row['host_country']) ? (string) $row['host_country'] : null);
        $countryA = amiga_community_country_token(isset($row['country_a']) ? (string) $row['country_a'] : null);
        $countryB = amiga_community_country_token(isset($row['country_b']) ? (string) $row['country_b'] : null);
        $metrics = amiga_community_rated_game_metrics($row);
        $playerA = (int) $metrics['player_a_id'];
        $playerB = (int) $metrics['player_b_id'];
        $goalsA = (int) $metrics['goals_a'];
        $goalsB = (int) $metrics['goals_b'];
        $sumGoals = (int) $metrics['sum_of_goals'];
        $isWc = (int) ($row['is_world_cup'] ?? 0) === 1;

        if ($year !== null) {
            $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'games', 'game'), 1);
            $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'goals', 'game'), (float) $sumGoals);
            if ($metrics['is_draw']) {
                $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'draws', 'game'), 1);
            }
            $bump(
                $factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'double_digits', 'game'),
                (float) $metrics['dd_slots']
            );
            $bump(
                $factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'clean_sheets', 'game'),
                (float) $metrics['cs_slots']
            );
            if ($metrics['is_high_scoring']) {
                $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'high_scoring_games', 'game'), 1);
            }
            if ($metrics['is_low_scoring']) {
                $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'low_scoring_games', 'game'), 1);
            }
            if ($hostCountry !== null) {
                $bump($factKey('year', $year, 'host_country', $hostCountry, 'games', 'game'), 1);
                $bump($factKey('year', $year, 'host_country', $hostCountry, 'goals', 'game'), (float) ($goalsA + $goalsB));
            }
            if ($countryA !== null) {
                $bump($factKey('year', $year, 'player_nationality', $countryA, 'games', 'participant'), 1);
                $bump($factKey('year', $year, 'player_nationality', $countryA, 'goals', 'participant'), (float) $goalsA);
            }
            if ($countryB !== null) {
                $bump($factKey('year', $year, 'player_nationality', $countryB, 'games', 'participant'), 1);
                $bump($factKey('year', $year, 'player_nationality', $countryB, 'goals', 'participant'), (float) $goalsB);
            }
            if ($isWc) {
                $bump($factKey('year', $year, 'world_cup', AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY, 'games', 'game'), 1);
                $bump($factKey('year', $year, 'world_cup', AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY, 'goals', 'game'), (float) $sumGoals);
            }

            foreach ([$countryA, $countryB] as $country) {
                if ($country !== null) {
                    $nationalitiesByYear[$year][$country] = true;
                }
            }
            if ($isWc) {
                foreach ([$countryA, $countryB] as $country) {
                    if ($country !== null) {
                        $nationalitiesWcByYear[$year][$country] = true;
                    }
                }
            }

            [$pairA, $pairB] = amiga_community_canonical_pair($playerA, $playerB);
            $pairKey = $pairA . ':' . $pairB;
            $pairsByYear[$year][$pairKey] = true;
            $pairsCumulative[$pairKey] = true;

            foreach ([[$playerA, $countryA], [$playerB, $countryB]] as [$pid, $country]) {
                if (!isset($debutYearByPlayer[$pid])) {
                    $debutYearByPlayer[$pid] = $year;
                    if ($country !== null) {
                        $debutCountryByPlayer[$pid] = $country;
                    }
                }
            }

            $activePlayers['year' . "\0" . $year . "\0" . 'realm'][$playerA] = true;
            $activePlayers['year' . "\0" . $year . "\0" . 'realm'][$playerB] = true;
            if ($countryA !== null) {
                $activePlayersByNationality[$year . "\0" . $countryA][$playerA] = true;
                $activePlayersByNationalityAllTime[$countryA][$playerA] = true;
            }
            if ($countryB !== null) {
                $activePlayersByNationality[$year . "\0" . $countryB][$playerB] = true;
                $activePlayersByNationalityAllTime[$countryB][$playerB] = true;
            }
            if ($isWc) {
                $activePlayers['year' . "\0" . $year . "\0" . 'world_cup'][$playerA] = true;
                $activePlayers['year' . "\0" . $year . "\0" . 'world_cup'][$playerB] = true;
                if ($countryA !== null) {
                    $activePlayersWcByNationality[$year . "\0" . $countryA][$playerA] = true;
                }
                if ($countryB !== null) {
                    $activePlayersWcByNationality[$year . "\0" . $countryB][$playerB] = true;
                }
            }
        }

        if ($isWc) {
            $wcGamesPlayed++;
        }

        if ($hostCountry !== null) {
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'host_country', $hostCountry, 'games', 'game'),
                1
            );
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'host_country', $hostCountry, 'goals', 'game'),
                (float) ($goalsA + $goalsB)
            );
        }
        if ($countryA !== null) {
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'player_nationality', $countryA, 'games', 'participant'),
                1
            );
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'player_nationality', $countryA, 'goals', 'participant'),
                (float) $goalsA
            );
        }
        if ($countryB !== null) {
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'player_nationality', $countryB, 'games', 'participant'),
                1
            );
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'player_nationality', $countryB, 'goals', 'participant'),
                (float) $goalsB
            );
        }
    }

    $tourSql = "
        SELECT t.event_date, t.country
        FROM tournaments t
        WHERE (t.rating_finalized = 1 OR t.id = ?)
          AND (
            t.event_date < ?
            OR (t.event_date = ? AND (t.chrono < ? OR (t.chrono = ? AND t.id <= ?)))
          )
        ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC
    ";
    $tstmt = $con->prepare($tourSql);
    if ($tstmt === false) {
        throw new RuntimeException('prepare community realm scan tournaments: ' . $con->error);
    }
    // Include the tournament being finalized even when rating_finalized is still 0
    // (PHP sets the flag only after community/realm writers succeed — limbo safety).
    $tstmt->bind_param('issddi', $tid, $eventDate, $eventDate, $chrono, $chrono, $tid);
    if (!$tstmt->execute()) {
        throw new RuntimeException('execute community realm scan tournaments: ' . $tstmt->error);
    }
    $tres = $tstmt->get_result();
    $tournamentsFinalized = 0;
    $distinctHostCountriesSet = [];
    while ($trow = $tres->fetch_assoc()) {
        $tournamentsFinalized++;
        $year = amiga_community_year_key(isset($trow['event_date']) ? (string) $trow['event_date'] : null);
        $host = amiga_community_country_token(isset($trow['country']) ? (string) $trow['country'] : null);
        if ($year !== null) {
            $bump($factKey('year', $year, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'tournaments', 'game'), 1);
            if ($host !== null) {
                $bump($factKey('year', $year, 'host_country', $host, 'tournaments', 'game'), 1);
                $hostCountriesByYear[$year][$host] = true;
            }
        }
        if ($host !== null) {
            $distinctHostCountriesSet[$host] = true;
            $bump(
                $factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'host_country', $host, 'tournaments', 'game'),
                1
            );
        }
    }
    $tstmt->close();

    foreach ($activePlayers as $activeKey => $players) {
        if ($players === []) {
            continue;
        }
        [$periodType, $periodKey, $sliceKind] = explode("\0", $activeKey, 3);
        $periodKey = (string) $periodKey;
        $sliceType = $sliceKind === 'realm' ? 'realm' : 'world_cup';
        $sliceKey = $sliceType === 'realm' ? AMIGA_COMMUNITY_REALM_SLICE_KEY : AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY;
        $values[$factKey($periodType, $periodKey, $sliceType, $sliceKey, 'active_players', 'game')] = (float) count($players);
    }

    foreach ($activePlayersByNationality as $natKey => $players) {
        if ($players === []) {
            continue;
        }
        [$year, $country] = explode("\0", $natKey, 2);
        $values[$factKey('year', (string) $year, 'player_nationality', (string) $country, 'active_players', 'participant')] =
            (float) count($players);
    }

    foreach ($activePlayersWcByNationality as $natKey => $players) {
        if ($players === []) {
            continue;
        }
        [$year, $country] = explode("\0", $natKey, 2);
        $values[$factKey('year', (string) $year, 'player_nationality', (string) $country, 'wc_active_players', 'participant')] =
            (float) count($players);
    }

    foreach ($activePlayersByNationalityAllTime as $country => $players) {
        if ($players === []) {
            continue;
        }
        $values[$factKey('all_time', AMIGA_COMMUNITY_ALL_TIME_PERIOD_KEY, 'player_nationality', (string) $country, 'active_players', 'participant')] =
            (float) count($players);
    }

    $debutCountsByNationality = [];
    foreach ($debutYearByPlayer as $pid => $debutYear) {
        $country = $debutCountryByPlayer[$pid] ?? null;
        if ($country === null) {
            continue;
        }
        $natKey = $debutYear . "\0" . $country;
        $debutCountsByNationality[$natKey] = ($debutCountsByNationality[$natKey] ?? 0) + 1;
    }
    foreach ($debutCountsByNationality as $natKey => $count) {
        if ($count <= 0) {
            continue;
        }
        [$year, $country] = explode("\0", $natKey, 2);
        $values[$factKey('year', (string) $year, 'player_nationality', (string) $country, 'player_debuts', 'participant')] =
            (float) $count;
    }

    foreach ($nationalitiesByYear as $year => $countries) {
        $yearKey = (string) $year;
        if ($countries !== []) {
            $values[$factKey('year', $yearKey, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'distinct_nationalities', 'game')] =
                (float) count($countries);
        }
    }
    foreach ($nationalitiesWcByYear as $year => $countries) {
        $yearKey = (string) $year;
        if ($countries !== []) {
            $values[$factKey('year', $yearKey, 'world_cup', AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY, 'distinct_nationalities', 'game')] =
                (float) count($countries);
        }
    }
    foreach ($hostCountriesByYear as $year => $countries) {
        $yearKey = (string) $year;
        if ($countries !== []) {
            $values[$factKey('year', $yearKey, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'distinct_host_countries', 'game')] =
                (float) count($countries);
        }
    }
    foreach ($pairsByYear as $year => $pairs) {
        $yearKey = (string) $year;
        if ($pairs !== []) {
            $values[$factKey('year', $yearKey, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'distinct_pairs', 'game')] =
                (float) count($pairs);
        }
    }

    $debutCounts = [];
    foreach ($debutYearByPlayer as $debutYear) {
        $debutCounts[$debutYear] = ($debutCounts[$debutYear] ?? 0) + 1;
    }
    foreach ($debutCounts as $year => $count) {
        $yearKey = (string) $year;
        if ($count > 0) {
            $values[$factKey('year', $yearKey, 'realm', AMIGA_COMMUNITY_REALM_SLICE_KEY, 'player_debuts', 'game')] = (float) $count;
        }
    }

    $facts = [];
    foreach ($values as $key => $value) {
        if ($value == 0.0) {
            continue;
        }
        [$periodType, $periodKey, $sliceType, $sliceKey, $metricKey, $countBasis] = explode("\0", $key, 6);
        $facts[] = [
            'tournament_id' => $tournamentId,
            'period_type' => $periodType,
            'period_key' => $periodKey,
            'slice_type' => $sliceType,
            'slice_key' => $sliceKey,
            'metric_key' => $metricKey,
            'count_basis' => $countBasis,
            'value' => $value,
        ];
    }

    return [
        'facts' => $facts,
        'tournaments_finalized' => $tournamentsFinalized,
        'distinct_host_countries' => count($distinctHostCountriesSet),
        'wc_games_played' => $wcGamesPlayed,
        'distinct_opponent_pairs' => count($pairsCumulative),
        'players_debuted' => count($debutYearByPlayer),
    ];
}

/**
 * @param array{
 *   tournaments_finalized: int,
 *   distinct_host_countries: int,
 *   wc_games_played: int,
 *   distinct_opponent_pairs: int,
 *   players_debuted: int
 * } $scan
 * @return array<string, int>
 */
function amiga_community_headline_extensions_from_scan(array $scan): array
{
    return [
        'TournamentsFinalized' => (int) $scan['tournaments_finalized'],
        'DistinctHostCountries' => (int) $scan['distinct_host_countries'],
        'WcGamesPlayed' => (int) $scan['wc_games_played'],
        'DistinctOpponentPairs' => (int) $scan['distinct_opponent_pairs'],
        'PlayersDebuted' => (int) $scan['players_debuted'],
    ];
}
