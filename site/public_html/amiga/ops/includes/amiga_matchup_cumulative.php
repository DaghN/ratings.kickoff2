<?php
/**
 * In-memory cumulative directed pair stats for Amiga tournament finalize.
 *
 * @see scripts/amiga/matchup_cumulative.py
 */
declare(strict_types=1);

/**
 * @phpstan-type PairTotals array{
 *   games: int,
 *   wins: int,
 *   draws: int,
 *   losses: int,
 *   goals_for: int,
 *   goals_against: int,
 *   max_goals_for: int,
 *   max_goals_against: int,
 *   min_goals_for: int,
 *   min_goals_against: int,
 *   max_win_margin: int|null,
 *   max_loss_margin: int|null,
 *   max_draw_goals: int|null,
 *   max_goal_sum: int,
 *   min_goal_sum: int,
 *   dd_wins: int,
 *   dd_losses: int,
 *   cs_wins: int,
 *   cs_losses: int
 * }
 */
final class AmigaMatchupCumulative
{
    /** @var array<int, array<int, PairTotals>> */
    private array $pairs = [];

    /**
     * @return PairTotals
     */
    private function emptyPairTotals(): array
    {
        return [
            'games' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'max_goals_for' => 0,
            'max_goals_against' => 0,
            'min_goals_for' => 0,
            'min_goals_against' => 0,
            'max_win_margin' => null,
            'max_loss_margin' => null,
            'max_draw_goals' => null,
            'max_goal_sum' => 0,
            'min_goal_sum' => 0,
            'dd_wins' => 0,
            'dd_losses' => 0,
            'cs_wins' => 0,
            'cs_losses' => 0,
        ];
    }

    /**
     * @return PairTotals
     */
    private function pair(int $playerId, int $opponentId): array
    {
        if (!isset($this->pairs[$playerId][$opponentId])) {
            $this->pairs[$playerId][$opponentId] = $this->emptyPairTotals();
        }

        return $this->pairs[$playerId][$opponentId];
    }

    /**
     * @param PairTotals $totals
     */
    private function applyDirectedOutcome(array &$totals, int $w, int $d, int $l, int $gf, int $ga): void
    {
        $gs = $gf + $ga;
        $totals['games']++;
        $totals['wins'] += $w;
        $totals['draws'] += $d;
        $totals['losses'] += $l;
        $totals['goals_for'] += $gf;
        $totals['goals_against'] += $ga;

        if ($totals['games'] === 1) {
            $totals['max_goals_for'] = $gf;
            $totals['max_goals_against'] = $ga;
            $totals['min_goals_for'] = $gf;
            $totals['min_goals_against'] = $ga;
            $totals['max_goal_sum'] = $gs;
            $totals['min_goal_sum'] = $gs;
            $totals['max_win_margin'] = $w > 0 ? $gf - $ga : null;
            $totals['max_loss_margin'] = $l > 0 ? $ga - $gf : null;
            $totals['max_draw_goals'] = $d > 0 ? $gf : null;

            return;
        }

        $totals['max_goals_for'] = max($totals['max_goals_for'], $gf);
        $totals['max_goals_against'] = max($totals['max_goals_against'], $ga);
        $totals['min_goals_for'] = min($totals['min_goals_for'], $gf);
        $totals['min_goals_against'] = min($totals['min_goals_against'], $ga);
        $totals['max_goal_sum'] = max($totals['max_goal_sum'], $gs);
        $totals['min_goal_sum'] = min($totals['min_goal_sum'], $gs);
        if ($w > 0) {
            $margin = $gf - $ga;
            $prev = $totals['max_win_margin'] ?? 0;
            $totals['max_win_margin'] = max($prev, $margin);
        }
        if ($l > 0) {
            $margin = $ga - $gf;
            $prev = $totals['max_loss_margin'] ?? 0;
            $totals['max_loss_margin'] = max($prev, $margin);
        }
        if ($d > 0) {
            if ($totals['max_draw_goals'] === null) {
                $totals['max_draw_goals'] = $gf;
            } else {
                $totals['max_draw_goals'] = max($totals['max_draw_goals'], $gf);
            }
        }
    }

    /**
     * @param array<string, mixed> $game
     */
    public function applyGame(array $game): void
    {
        $idA = (int) ($game['player_a_id'] ?? $game['idA']);
        $idB = (int) ($game['player_b_id'] ?? $game['idB']);
        $goalsA = (int) ($game['goals_a'] ?? $game['GoalsA']);
        $goalsB = (int) ($game['goals_b'] ?? $game['GoalsB']);

        if ($goalsA > $goalsB) {
            [$wA, $dA, $lA, $wB, $dB, $lB] = [1, 0, 0, 0, 0, 1];
        } elseif ($goalsA < $goalsB) {
            [$wA, $dA, $lA, $wB, $dB, $lB] = [0, 0, 1, 1, 0, 0];
        } else {
            [$wA, $dA, $lA, $wB, $dB, $lB] = [0, 1, 0, 0, 1, 0];
        }

        $ddA = $goalsA >= 10;
        $ddB = $goalsB >= 10;
        $csA = $goalsB === 0;
        $csB = $goalsA === 0;

        $pa = $this->pair($idA, $idB);
        $this->applyDirectedOutcome($pa, $wA, $dA, $lA, $goalsA, $goalsB);
        if ($ddA) {
            $pa['dd_wins']++;
        }
        if ($ddB) {
            $pa['dd_losses']++;
        }
        if ($csA) {
            $pa['cs_wins']++;
        }
        if ($csB) {
            $pa['cs_losses']++;
        }
        $this->pairs[$idA][$idB] = $pa;

        $pb = $this->pair($idB, $idA);
        $this->applyDirectedOutcome($pb, $wB, $dB, $lB, $goalsB, $goalsA);
        if ($ddB) {
            $pb['dd_wins']++;
        }
        if ($ddA) {
            $pb['dd_losses']++;
        }
        if ($csB) {
            $pb['cs_wins']++;
        }
        if ($csA) {
            $pb['cs_losses']++;
        }
        $this->pairs[$idB][$idA] = $pb;
    }

    /**
     * @return array<int, PairTotals>
     */
    public function pairsForPlayer(int $playerId): array
    {
        return $this->pairs[$playerId] ?? [];
    }

    /**
     * @return array{
     *   different_opponents: int,
     *   different_victims: int,
     *   different_culprits: int,
     *   double_digits_victims: int,
     *   double_digits_culprits: int,
     *   clean_sheets_victims: int,
     *   clean_sheets_culprits: int
     * }
     */
    public function networkCounts(int $playerId): array
    {
        $pairs = $this->pairsForPlayer($playerId);
        $victims = 0;
        $culprits = 0;
        $ddVictims = 0;
        $ddCulprits = 0;
        $csVictims = 0;
        $csCulprits = 0;
        foreach ($pairs as $totals) {
            if ($totals['wins'] > 0) {
                $victims++;
            }
            if ($totals['losses'] > 0) {
                $culprits++;
            }
            if ($totals['dd_wins'] > 0) {
                $ddVictims++;
            }
            if ($totals['dd_losses'] > 0) {
                $ddCulprits++;
            }
            if ($totals['cs_wins'] > 0) {
                $csVictims++;
            }
            if ($totals['cs_losses'] > 0) {
                $csCulprits++;
            }
        }

        return [
            'different_opponents' => count($pairs),
            'different_victims' => $victims,
            'different_culprits' => $culprits,
            'double_digits_victims' => $ddVictims,
            'double_digits_culprits' => $ddCulprits,
            'clean_sheets_victims' => $csVictims,
            'clean_sheets_culprits' => $csCulprits,
        ];
    }

    /**
     * @param array<string, mixed> $st
     */
    public function applyNetworkToPlayerState(int $playerId, array &$st): void
    {
        $counts = $this->networkCounts($playerId);
        $st['different_opponents'] = $counts['different_opponents'];
        $st['different_victims'] = $counts['different_victims'];
        $st['different_culprits'] = $counts['different_culprits'];
        $st['double_digits_victims'] = $counts['double_digits_victims'];
        $st['double_digits_culprits'] = $counts['double_digits_culprits'];
        $st['clean_sheets_victims'] = $counts['clean_sheets_victims'];
        $st['clean_sheets_culprits'] = $counts['clean_sheets_culprits'];
    }

    /**
     * @param PairTotals $totals
     * @return array<string, int|null>
     */
    public function pairToRow(int $playerId, int $opponentId, array $totals): array
    {
        return [
            'player_id' => $playerId,
            'opponent_id' => $opponentId,
            'games' => $totals['games'],
            'wins' => $totals['wins'],
            'draws' => $totals['draws'],
            'losses' => $totals['losses'],
            'goals_for' => $totals['goals_for'],
            'goals_against' => $totals['goals_against'],
            'max_goals_for' => $totals['max_goals_for'],
            'max_goals_against' => $totals['max_goals_against'],
            'min_goals_for' => $totals['min_goals_for'],
            'min_goals_against' => $totals['min_goals_against'],
            'max_win_margin' => $totals['max_win_margin'],
            'max_loss_margin' => $totals['max_loss_margin'],
            'max_draw_goals' => $totals['max_draw_goals'],
            'max_goal_sum' => $totals['max_goal_sum'],
            'min_goal_sum' => $totals['min_goal_sum'],
            'dd_wins' => $totals['dd_wins'],
            'dd_losses' => $totals['dd_losses'],
            'cs_wins' => $totals['cs_wins'],
            'cs_losses' => $totals['cs_losses'],
        ];
    }

    /**
     * @param list<int> $playerIds
     */
    public function loadFromSummary(mysqli $con, array $playerIds): void
    {
        if ($playerIds === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));
        $sql = 'SELECT player_id, opponent_id, games, wins, draws, losses, goals_for, goals_against, '
            . 'max_goals_for, max_goals_against, min_goals_for, min_goals_against, '
            . 'max_win_margin, max_loss_margin, max_draw_goals, max_goal_sum, min_goal_sum, '
            . 'dd_wins, dd_losses, cs_wins, cs_losses '
            . "FROM amiga_player_matchup_summary WHERE player_id IN ({$placeholders})";
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare matchup summary warm: ' . $con->error);
        }
        $stmt->bind_param($types, ...$playerIds);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute matchup summary warm: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $pid = (int) $row['player_id'];
            $oid = (int) $row['opponent_id'];
            $this->pairs[$pid][$oid] = [
                'games' => (int) $row['games'],
                'wins' => (int) $row['wins'],
                'draws' => (int) $row['draws'],
                'losses' => (int) $row['losses'],
                'goals_for' => (int) $row['goals_for'],
                'goals_against' => (int) $row['goals_against'],
                'max_goals_for' => (int) $row['max_goals_for'],
                'max_goals_against' => (int) $row['max_goals_against'],
                'min_goals_for' => (int) $row['min_goals_for'],
                'min_goals_against' => (int) $row['min_goals_against'],
                'max_win_margin' => $row['max_win_margin'] !== null ? (int) $row['max_win_margin'] : null,
                'max_loss_margin' => $row['max_loss_margin'] !== null ? (int) $row['max_loss_margin'] : null,
                'max_draw_goals' => $row['max_draw_goals'] !== null ? (int) $row['max_draw_goals'] : null,
                'max_goal_sum' => (int) $row['max_goal_sum'],
                'min_goal_sum' => (int) $row['min_goal_sum'],
                'dd_wins' => (int) $row['dd_wins'],
                'dd_losses' => (int) $row['dd_losses'],
                'cs_wins' => (int) $row['cs_wins'],
                'cs_losses' => (int) $row['cs_losses'],
            ];
        }
        $stmt->close();
    }
}

/**
 * @param array<string, mixed> $st
 */
function amiga_matchup_apply_peak_from_event_rating(
    array &$st,
    float $ratingAfter,
    int $tournamentId
): void {
    if ((int) ($st['games'] ?? 0) <= 0) {
        return;
    }
    $priorPeak = (float) ($st['peak_rating'] ?? 0);
    if ($priorPeak <= 0 || $ratingAfter > $priorPeak) {
        $st['peak_rating'] = $ratingAfter;
    }
    $priorPeakTid = isset($st['peak_rating_tournament_id'])
        ? ($st['peak_rating_tournament_id'] !== null ? (int) $st['peak_rating_tournament_id'] : null)
        : null;
    if ($priorPeak <= 0 || $ratingAfter > $priorPeak + 1e-9) {
        $st['peak_rating_tournament_id'] = $tournamentId;
    } elseif ($priorPeakTid === null && $priorPeak > 0) {
        $st['peak_rating_tournament_id'] = $tournamentId;
    } else {
        $st['peak_rating_tournament_id'] = $priorPeakTid;
    }

    $priorLow = (float) ($st['lowest_rating'] ?? 0);
    if ($priorLow <= 0 || $ratingAfter < $priorLow) {
        $st['lowest_rating'] = $ratingAfter;
    }
    $priorLowTid = isset($st['lowest_rating_tournament_id'])
        ? ($st['lowest_rating_tournament_id'] !== null ? (int) $st['lowest_rating_tournament_id'] : null)
        : null;
    if ($priorLow <= 0 || $ratingAfter < $priorLow - 1e-9) {
        $st['lowest_rating_tournament_id'] = $tournamentId;
    } elseif ($priorLowTid === null && $priorLow > 0) {
        $st['lowest_rating_tournament_id'] = $tournamentId;
    } else {
        $st['lowest_rating_tournament_id'] = $priorLowTid;
    }
}
