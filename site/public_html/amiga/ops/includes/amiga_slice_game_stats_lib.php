<?php
/**
 * World Cup slice V2 — per-game texture, DD/CS, network, geo (mirrors slice_game_stats.py).
 *
 * @see docs/amiga-world-cups-player-slice-v2-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_slice_totals_lib.php';
require_once __DIR__ . '/../includes/amiga_player_geo_year_lib.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_outcome.php';

/** @var list<string> */
const AMIGA_SLICE_V2_SCALAR_KEYS = [
    'goal_ratio',
    'most_goals_scored',
    'most_goals_conceded',
    'biggest_win_difference',
    'biggest_loss_difference',
    'biggest_sum_of_goals',
    'biggest_draw_sum',
    'double_digits',
    'clean_sheets',
    'double_digits_ratio',
    'clean_sheets_ratio',
    'double_digits_conceded',
    'clean_sheets_conceded',
    'double_digits_conceded_ratio',
    'clean_sheets_conceded_ratio',
    'opponent_countries_faced',
    'opponent_countries_beaten',
    'different_opponents',
    'different_victims',
    'double_digits_victims',
    'clean_sheets_victims',
];

final class AmigaWorldCupSliceTracker
{
    /** @var array<string, mixed> */
    public array $row;

    /** @var array<string, true> */
    private array $opponentCountriesFaced = [];

    /** @var array<string, true> */
    private array $opponentCountriesBeaten = [];

    /** @var array<int, true> */
    private array $opponents = [];

    /** @var array<int, true> */
    private array $victims = [];

    /** @var array<int, true> */
    private array $ddVictims = [];

    /** @var array<int, true> */
    private array $csVictims = [];

    /**
     * @param array<string, mixed>|null $totalsRow
     */
    public static function fromTotalsRow(?array $totalsRow): self
    {
        $tracker = new self();
        $tracker->row = amiga_slice_from_totals_row($totalsRow ?? []);

        return $tracker;
    }

    public function seedOwnCountry(?string $ownCountry): void
    {
        $own = AmigaPlayerGeoYearTracker::normalizeCountry($ownCountry);
        if ($own !== null) {
            $this->opponentCountriesFaced[$own] = true;
        }
    }

    public function applyPerspective(
        int $opponentId,
        ?string $opponentCountry,
        int $goalsFor,
        int $goalsAgainst,
        float $actualScore,
        bool $ddFor,
    ): void {
        $won = $actualScore === 1.0;
        $drew = $actualScore === 0.5;
        $lost = $actualScore === 0.0;
        $margin = abs($goalsFor - $goalsAgainst);
        $sumOfGoals = $goalsFor + $goalsAgainst;

        $this->opponents[$opponentId] = true;
        if ($won) {
            $this->victims[$opponentId] = true;
        }
        $oppCountry = AmigaPlayerGeoYearTracker::normalizeCountry($opponentCountry);
        if ($oppCountry !== null) {
            $this->opponentCountriesFaced[$oppCountry] = true;
            if ($won) {
                $this->opponentCountriesBeaten[$oppCountry] = true;
            }
        }

        if ($ddFor) {
            $this->row['double_digits'] = (int) ($this->row['double_digits'] ?? 0) + 1;
            $this->ddVictims[$opponentId] = true;
        }
        if ($goalsAgainst >= 10) {
            $this->row['double_digits_conceded'] = (int) ($this->row['double_digits_conceded'] ?? 0) + 1;
        }
        if ($goalsAgainst === 0) {
            $this->row['clean_sheets'] = (int) ($this->row['clean_sheets'] ?? 0) + 1;
            $this->csVictims[$opponentId] = true;
        }
        if ($goalsFor === 0) {
            $this->row['clean_sheets_conceded'] = (int) ($this->row['clean_sheets_conceded'] ?? 0) + 1;
        }

        if ($goalsFor >= 1 && $goalsFor > (int) ($this->row['most_goals_scored'] ?? 0)) {
            $this->row['most_goals_scored'] = $goalsFor;
        }
        if ($goalsAgainst > (int) ($this->row['most_goals_conceded'] ?? 0)) {
            $this->row['most_goals_conceded'] = $goalsAgainst;
        }
        if ($won && $margin > (int) ($this->row['biggest_win_difference'] ?? 0)) {
            $this->row['biggest_win_difference'] = $margin;
        }
        if ($lost && $margin > (int) ($this->row['biggest_loss_difference'] ?? 0)) {
            $this->row['biggest_loss_difference'] = $margin;
        }
        if ($drew && $sumOfGoals > (int) ($this->row['biggest_draw_sum'] ?? 0)) {
            $this->row['biggest_draw_sum'] = $sumOfGoals;
        }
        if ($sumOfGoals > (int) ($this->row['biggest_sum_of_goals'] ?? 0)) {
            $this->row['biggest_sum_of_goals'] = $sumOfGoals;
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    public function flushV2Into(array &$target): void
    {
        $this->syncNetworkGeoCounts();
        $this->recomputeRatios();
        foreach (AMIGA_SLICE_V2_SCALAR_KEYS as $key) {
            $target[$key] = $this->row[$key];
        }
    }

    private function syncNetworkGeoCounts(): void
    {
        $this->row['opponent_countries_faced'] = count($this->opponentCountriesFaced);
        $this->row['opponent_countries_beaten'] = count($this->opponentCountriesBeaten);
        $this->row['different_opponents'] = count($this->opponents);
        $this->row['different_victims'] = count($this->victims);
        $this->row['double_digits_victims'] = count($this->ddVictims);
        $this->row['clean_sheets_victims'] = count($this->csVictims);
    }

    private function recomputeRatios(): void
    {
        $games = (int) ($this->row['games'] ?? 0);
        $gf = (int) ($this->row['goals_for'] ?? 0);
        $ga = (int) ($this->row['goals_against'] ?? 0);
        $this->row['goal_ratio'] = $ga > 0 ? round($gf / $ga, 8) : null;
        $dd = (int) ($this->row['double_digits'] ?? 0);
        $cs = (int) ($this->row['clean_sheets'] ?? 0);
        $ddc = (int) ($this->row['double_digits_conceded'] ?? 0);
        $csc = (int) ($this->row['clean_sheets_conceded'] ?? 0);
        $this->row['double_digits_ratio'] = $games > 0 ? round($dd / $games, 4) : null;
        $this->row['clean_sheets_ratio'] = $games > 0 ? round($cs / $games, 4) : null;
        $this->row['double_digits_conceded_ratio'] = $games > 0 ? round($ddc / $games, 4) : null;
        $this->row['clean_sheets_conceded_ratio'] = $games > 0 ? round($csc / $games, 4) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function v2OracleValues(): array
    {
        $this->syncNetworkGeoCounts();
        $this->recomputeRatios();
        $out = [];
        foreach (AMIGA_SLICE_V2_SCALAR_KEYS as $key) {
            $out[$key] = $this->row[$key];
        }

        return $out;
    }
}

/**
 * @param array<string, mixed> $v1Row
 * @param list<array<string, mixed>> $games
 * @param array<int, string|null> $playerCountries
 * @return array<string, mixed>
 */
function amiga_slice_build_v2_oracle_for_player(
    array $v1Row,
    array $games,
    array $playerCountries,
    int $playerId,
): array {
    $tracker = AmigaWorldCupSliceTracker::fromTotalsRow($v1Row);
    $empty = amiga_slice_empty_world_cup();
    foreach (AMIGA_SLICE_V2_SCALAR_KEYS as $key) {
        $tracker->row[$key] = $empty[$key];
    }
    $tracker->seedOwnCountry($playerCountries[$playerId] ?? null);
    foreach ($games as $game) {
        $idA = (int) $game['idA'];
        $idB = (int) $game['idB'];
        if ($playerId !== $idA && $playerId !== $idB) {
            continue;
        }
        $goalsA = (int) $game['GoalsA'];
        $goalsB = (int) $game['GoalsB'];
        $outcome = k2_post_game_outcome_from_goals($goalsA, $goalsB, $idA, $idB);
        if ($playerId === $idA) {
            $tracker->applyPerspective(
                $idB,
                $playerCountries[$idB] ?? null,
                $goalsA,
                $goalsB,
                (float) $outcome['actual_score'],
                (bool) $outcome['dd_player_a'],
            );
        } else {
            $scoreB = $outcome['actual_score'] === 0.5 ? 0.5 : 1.0 - (float) $outcome['actual_score'];
            $tracker->applyPerspective(
                $idA,
                $playerCountries[$idA] ?? null,
                $goalsB,
                $goalsA,
                $scoreB,
                (bool) $outcome['dd_player_b'],
            );
        }
    }

    return $tracker->v2OracleValues();
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_slice_load_wc_games_through_tournament(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'g.goals_a AS GoalsA, g.goals_b AS GoalsB '
        . 'FROM amiga_games g '
        . 'INNER JOIN tournaments t ON t.id = g.tournament_id '
        . 'INNER JOIN tournaments tc ON tc.id = ? '
        . "WHERE t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]' "
        . 'AND ('
        . '  t.event_date < tc.event_date '
        . '  OR (t.event_date = tc.event_date AND t.chrono < tc.chrono) '
        . '  OR (t.event_date = tc.event_date AND t.chrono = tc.chrono AND t.id <= tc.id)'
        . ') '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC, g.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $games = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $games[] = $row;
    }
    $stmt->close();

    return $games;
}

/**
 * @param array<int, array<string, mixed>> $sliceByPlayer
 * @param array<int, string|null> $playerCountries
 */
function amiga_slice_apply_v2_through_tournament(
    mysqli $con,
    int $tournamentId,
    array &$sliceByPlayer,
    array $playerCountries,
): void {
    $games = amiga_slice_load_wc_games_through_tournament($con, $tournamentId);
    if ($games === []) {
        return;
    }
    foreach ($sliceByPlayer as $playerId => &$slice) {
        $pid = (int) $playerId;
        if ((int) ($slice['tournaments_played'] ?? 0) <= 0) {
            continue;
        }
        $v2 = amiga_slice_build_v2_oracle_for_player($slice, $games, $playerCountries, $pid);
        foreach ($v2 as $key => $value) {
            $slice[$key] = $value;
        }
    }
    unset($slice);
}
