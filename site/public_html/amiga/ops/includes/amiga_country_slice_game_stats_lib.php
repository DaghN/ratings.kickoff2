<?php
/**
 * World Cup country slice per-game tracker (mirrors country_slice_game_stats.py).
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_slice_totals_lib.php';
require_once __DIR__ . '/../includes/amiga_player_geo_year_lib.php';
require_once __DIR__ . '/../includes/post_game_outcome.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_performance_rating.php';

final class AmigaCountryWorldCupSliceTracker
{
    public string $countryToken;

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

    /** @var list<array{opponent: float, score: float}> */
    private array $perfPairs = [];

    private float $sumOpponentRating = 0.0;

    public function __construct(string $countryToken)
    {
        $this->countryToken = $countryToken;
        $this->row = amiga_country_slice_empty_world_cup();
    }

    public function seedOwnCountry(): void
    {
        if ($this->countryToken !== AMIGA_COUNTRY_UNKNOWN_TOKEN) {
            $this->opponentCountriesFaced[$this->countryToken] = true;
        }
    }

    public function applyPlayerGamePerspective(
        int $opponentId,
        string $opponentCountryToken,
        int $goalsFor,
        int $goalsAgainst,
        float $actualScore,
        bool $ddFor,
        float $opponentRating,
    ): void {
        $won = $actualScore === 1.0;
        $this->sumOpponentRating += $opponentRating;
        $this->perfPairs[] = ['opponent' => $opponentRating, 'score' => $actualScore];

        if ($opponentCountryToken === $this->countryToken) {
            $this->row['domestic_games'] = (int) ($this->row['domestic_games'] ?? 0) + 1;
        } else {
            $this->row['international_games'] = (int) ($this->row['international_games'] ?? 0) + 1;
        }

        $this->opponents[$opponentId] = true;
        if ($won) {
            $this->victims[$opponentId] = true;
        }

        $oppCountry = AmigaPlayerGeoYearTracker::normalizeCountry($opponentCountryToken);
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
        if ($goalsAgainst === 0) {
            $this->csVictims[$opponentId] = true;
        }
    }

    /**
     * @param array<string, mixed> $target
     */
    public function flushInto(array &$target): void
    {
        $target['domestic_games'] = (int) ($this->row['domestic_games'] ?? 0);
        $target['international_games'] = (int) ($this->row['international_games'] ?? 0);
        $target['opponent_countries_faced'] = count($this->opponentCountriesFaced);
        $target['opponent_countries_beaten'] = count($this->opponentCountriesBeaten);
        $target['different_opponents'] = count($this->opponents);
        $target['different_victims'] = count($this->victims);
        $target['double_digits_victims'] = count($this->ddVictims);
        $target['clean_sheets_victims'] = count($this->csVictims);

        $games = (int) ($target['games'] ?? 0);
        if ($games > 0) {
            $target['average_opponent_rating'] = round($this->sumOpponentRating / $games, 4);
        } else {
            $target['average_opponent_rating'] = null;
        }

        $perf = amiga_performance_rating_from_pairs($this->perfPairs);
        $target['performance_rating'] = $perf !== null ? round($perf, 4) : null;

        amiga_country_slice_finalize_row($target);
    }
}

/**
 * @param list<array<string, mixed>> $games
 * @param array<int, string|null> $playerCountries
 * @param array<string, AmigaCountryWorldCupSliceTracker> $trackers
 */
function amiga_country_slice_apply_wc_games(
    array $games,
    array $playerCountries,
    array &$trackers,
): void {
    foreach ($games as $game) {
        $idA = (int) $game['idA'];
        $idB = (int) $game['idB'];
        $goalsA = (int) $game['GoalsA'];
        $goalsB = (int) $game['GoalsB'];
        $ratingA = (float) ($game['rating_a'] ?? 0.0);
        $ratingB = (float) ($game['rating_b'] ?? 0.0);
        $outcome = k2_post_game_outcome_from_goals($goalsA, $goalsB, $idA, $idB);
        $scoreB = $outcome['actual_score'] === 0.5
            ? 0.5
            : 1.0 - (float) $outcome['actual_score'];

        $tokenA = amiga_country_token_for_player($playerCountries, $idA);
        $tokenB = amiga_country_token_for_player($playerCountries, $idB);

        if (!isset($trackers[$tokenA])) {
            $trackers[$tokenA] = new AmigaCountryWorldCupSliceTracker($tokenA);
            $trackers[$tokenA]->seedOwnCountry();
        }
        if (!isset($trackers[$tokenB])) {
            $trackers[$tokenB] = new AmigaCountryWorldCupSliceTracker($tokenB);
            $trackers[$tokenB]->seedOwnCountry();
        }

        $trackers[$tokenA]->applyPlayerGamePerspective(
            $idB,
            $tokenB,
            $goalsA,
            $goalsB,
            (float) $outcome['actual_score'],
            (bool) $outcome['dd_player_a'],
            $ratingB,
        );
        $trackers[$tokenB]->applyPlayerGamePerspective(
            $idA,
            $tokenA,
            $goalsB,
            $goalsA,
            $scoreB,
            (bool) $outcome['dd_player_b'],
            $ratingA,
        );
    }
}
