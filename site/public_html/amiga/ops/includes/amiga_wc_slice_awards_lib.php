<?php
/**
 * World Cup per-event awards + single-WC peaks (mirrors scripts/amiga/wc_slice_awards.py).
 *
 * Applied once per World Cup finalize, mutating the cumulative WC slice rows BEFORE
 * amiga_ops_persist_world_cup_slices writes them:
 *   - 4.7 awards: +1 best_attack_awards to the highest event GF/g, +1 best_defense_awards
 *     to the lowest event GA/g. games >= 1 to be eligible; ties -> lowest player_id.
 *   - 4.8 single-WC peaks: per participant, update best_single_wc_gf_per_game /
 *     best_single_wc_ga_per_game (+ anchor tournament id) on a strict beat.
 *
 * Per-event GF/GA/games come from amiga_games (matches participation; verify-wc-hof
 * recomputes both independently).
 */
declare(strict_types=1);

/**
 * Per-game average rounded to 4 d.p. (matches slice decimal(6,4) / Python event_average).
 */
function amiga_wc_event_average(int $goals, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return round($goals / $games, 4);
}

/**
 * This tournament's per-participant {games, goals_for, goals_against} from amiga_games.
 *
 * @return array<int, array{games: int, goals_for: int, goals_against: int}>
 */
function amiga_wc_event_participation_from_games(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT player_a_id, player_b_id, goals_a, goals_b FROM amiga_games WHERE tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare wc event participation: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc event participation: ' . $stmt->error);
    }
    $res = $stmt->get_result();

    /** @var array<int, array{games: int, goals_for: int, goals_against: int}> $agg */
    $agg = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $a = (int) $row['player_a_id'];
        $b = (int) $row['player_b_id'];
        $ga = (int) $row['goals_a'];
        $gb = (int) $row['goals_b'];
        if (!isset($agg[$a])) {
            $agg[$a] = ['games' => 0, 'goals_for' => 0, 'goals_against' => 0];
        }
        if (!isset($agg[$b])) {
            $agg[$b] = ['games' => 0, 'goals_for' => 0, 'goals_against' => 0];
        }
        $agg[$a]['games']++;
        $agg[$a]['goals_for'] += $ga;
        $agg[$a]['goals_against'] += $gb;
        $agg[$b]['games']++;
        $agg[$b]['goals_for'] += $gb;
        $agg[$b]['goals_against'] += $ga;
    }
    $stmt->close();

    return $agg;
}

/**
 * @param array<int, array{games: int, goals_for: int, goals_against: int}> $participation
 * @return array{0: ?int, 1: ?int} [attackWinnerPid, defenseWinnerPid]
 */
function amiga_wc_compute_event_award_winners(array $participation): array
{
    /** @var array{0: float, 1: int}|null $attackBest (-gf_pg, pid) minimised */
    $attackBest = null;
    /** @var array{0: float, 1: int}|null $defenseBest (ga_pg, pid) minimised */
    $defenseBest = null;
    foreach ($participation as $pid => $agg) {
        $games = (int) $agg['games'];
        if ($games <= 0) {
            continue;
        }
        $pid = (int) $pid;
        $gfPg = amiga_wc_event_average((int) $agg['goals_for'], $games);
        $gaPg = amiga_wc_event_average((int) $agg['goals_against'], $games);
        if ($gfPg !== null) {
            $key = [-$gfPg, $pid];
            if ($attackBest === null || $key < $attackBest) {
                $attackBest = $key;
            }
        }
        if ($gaPg !== null) {
            $key = [$gaPg, $pid];
            if ($defenseBest === null || $key < $defenseBest) {
                $defenseBest = $key;
            }
        }
    }

    return [
        $attackBest !== null ? (int) $attackBest[1] : null,
        $defenseBest !== null ? (int) $defenseBest[1] : null,
    ];
}

/**
 * @param array<string, mixed> $sliceRow
 * @param array{games: int, goals_for: int, goals_against: int} $event
 */
function amiga_wc_update_single_peak(array &$sliceRow, array $event, int $tournamentId): void
{
    $games = (int) $event['games'];
    if ($games <= 0) {
        return;
    }
    $gfPg = amiga_wc_event_average((int) $event['goals_for'], $games);
    $gaPg = amiga_wc_event_average((int) $event['goals_against'], $games);
    if ($gfPg !== null) {
        $cur = $sliceRow['best_single_wc_gf_per_game'] ?? null;
        if ($cur === null || $gfPg > (float) $cur) {
            $sliceRow['best_single_wc_gf_per_game'] = $gfPg;
            $sliceRow['best_single_wc_gf_per_game_tournament_id'] = $tournamentId;
        }
    }
    if ($gaPg !== null) {
        $cur = $sliceRow['best_single_wc_ga_per_game'] ?? null;
        if ($cur === null || $gaPg < (float) $cur) {
            $sliceRow['best_single_wc_ga_per_game'] = $gaPg;
            $sliceRow['best_single_wc_ga_per_game_tournament_id'] = $tournamentId;
        }
    }
}

/**
 * Mutate cumulative WC slice rows for this World Cup's participants (awards + peaks).
 *
 * @param array<int, array<string, mixed>> $sliceByPlayer
 */
function amiga_ops_apply_wc_slice_awards_and_peaks(
    mysqli $con,
    int $tournamentId,
    array &$sliceByPlayer,
): void {
    $participation = amiga_wc_event_participation_from_games($con, $tournamentId);

    foreach ($participation as $pid => $event) {
        $pid = (int) $pid;
        if (!isset($sliceByPlayer[$pid])) {
            continue;
        }
        amiga_wc_update_single_peak($sliceByPlayer[$pid], $event, $tournamentId);
    }

    [$attackPid, $defensePid] = amiga_wc_compute_event_award_winners($participation);
    if ($attackPid !== null && isset($sliceByPlayer[$attackPid])) {
        $sliceByPlayer[$attackPid]['best_attack_awards'] =
            (int) ($sliceByPlayer[$attackPid]['best_attack_awards'] ?? 0) + 1;
    }
    if ($defensePid !== null && isset($sliceByPlayer[$defensePid])) {
        $sliceByPlayer[$defensePid]['best_defense_awards'] =
            (int) ($sliceByPlayer[$defensePid]['best_defense_awards'] ?? 0) + 1;
    }
}