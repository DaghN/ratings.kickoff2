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
    private function pair(int $playerId, int $opponentId): array
    {
        if (!isset($this->pairs[$playerId][$opponentId])) {
            $this->pairs[$playerId][$opponentId] = [
                'games' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'dd_wins' => 0,
                'dd_losses' => 0,
                'cs_wins' => 0,
                'cs_losses' => 0,
            ];
        }

        return $this->pairs[$playerId][$opponentId];
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
        $pa['games']++;
        $pa['wins'] += $wA;
        $pa['draws'] += $dA;
        $pa['losses'] += $lA;
        $pa['goals_for'] += $goalsA;
        $pa['goals_against'] += $goalsB;
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
        $pb['games']++;
        $pb['wins'] += $wB;
        $pb['draws'] += $dB;
        $pb['losses'] += $lB;
        $pb['goals_for'] += $goalsB;
        $pb['goals_against'] += $goalsA;
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
     * @return array<string, int>
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
function amiga_matchup_apply_peak_from_event_rating(array &$st, float $ratingAfter): void
{
    if ((int) ($st['games'] ?? 0) <= 0) {
        return;
    }
    $peak = (float) ($st['peak_rating'] ?? 0);
    $low = (float) ($st['lowest_rating'] ?? 0);
    if ($peak <= 0 || $ratingAfter > $peak) {
        $st['peak_rating'] = $ratingAfter;
    }
    if ($low <= 0 || $ratingAfter < $low) {
        $st['lowest_rating'] = $ratingAfter;
    }
}
