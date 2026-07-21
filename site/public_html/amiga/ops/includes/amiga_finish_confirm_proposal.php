<?php
/**
 * Organizer finish-confirm proposal (slice 2) — prefill A–D / Tier E for Table UI.
 *
 * Policy: docs/amiga-organizer-finish-confirm-policy.md
 * Write path: amiga_finish_override_write.php
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_finish_override_write.php';
require_once __DIR__ . '/amiga_post_game_participation.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_participation_placement.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_running_tournament_lib.php';

/**
 * @param array<int, int> $overrides
 * @param list<int> $entrantIds
 */
function amiga_ops_finish_confirm_overrides_are_full_ladder(array $overrides, array $entrantIds): bool
{
    try {
        amiga_ops_finish_override_validate_full_ladder($overrides, $entrantIds);

        return true;
    } catch (InvalidArgumentException) {
        return false;
    }
}

/**
 * True when Tier E holds a valid secretary ladder for all registered entrants.
 */
function amiga_ops_finish_confirm_tournament_is_confirmed(mysqli $con, int $tournamentId): bool
{
    if ($tournamentId < 1) {
        return false;
    }
    $entrantIds = amiga_ops_finish_override_registered_entrant_ids($con, $tournamentId);
    if ($entrantIds === []) {
        return false;
    }
    $overrides = amiga_ops_participation_finish_overrides_for_tournament($con, $tournamentId);

    return amiga_ops_finish_confirm_overrides_are_full_ladder($overrides, $entrantIds);
}

/**
 * Densify a partial position map into a full 1..N ladder.
 *
 * @param list<int> $entrantIds
 * @param array<int, int|null> $suggested player_id => position or null
 * @param array<int, int> $tieBreakOrder player_id => sort key (lower first)
 * @return array<int, int>
 */
function amiga_ops_finish_confirm_densify_ladder(
    array $entrantIds,
    array $suggested,
    array $tieBreakOrder = [],
): array {
    $entrantIds = array_values(array_unique(array_map('intval', $entrantIds)));
    usort(
        $entrantIds,
        static function (int $a, int $b) use ($suggested, $tieBreakOrder): int {
            $pa = $suggested[$a] ?? null;
            $pb = $suggested[$b] ?? null;
            $pa = ($pa !== null && (int) $pa > 0) ? (int) $pa : PHP_INT_MAX;
            $pb = ($pb !== null && (int) $pb > 0) ? (int) $pb : PHP_INT_MAX;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            $ta = $tieBreakOrder[$a] ?? PHP_INT_MAX;
            $tb = $tieBreakOrder[$b] ?? PHP_INT_MAX;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            return $a <=> $b;
        }
    );

    $ladder = [];
    $pos = 1;
    foreach ($entrantIds as $playerId) {
        $ladder[(int) $playerId] = $pos;
        $pos++;
    }

    return $ladder;
}

/**
 * Build secretary confirm proposal for a generated kitchen.
 *
 * @param list<array{player_id:int,player_name:string,country?:string,status?:string}> $entrants
 * @param list<array{player_id:int,player_name?:string,position?:int}> $tableRows organizer merged table rows
 * @param array{name?:string,has_league?:int|bool,has_cup?:int|bool,is_world_cup?:int|bool} $tournament
 * @return array{
 *   confirmed:bool,
 *   source:string,
 *   is_world_cup:bool,
 *   entrant_count:int,
 *   ladder:array<int,int>,
 *   ordered:list<array{player_id:int,player_name:string,position:int,medal_label:string}>
 * }
 */
function amiga_ops_finish_confirm_build_proposal(
    mysqli $con,
    int $tournamentId,
    array $tournament,
    array $entrants,
    array $tableRows,
): array {
    $registered = [];
    $names = [];
    foreach ($entrants as $entrant) {
        if (($entrant['status'] ?? 'registered') !== 'registered') {
            continue;
        }
        $pid = (int) $entrant['player_id'];
        if ($pid < 1) {
            continue;
        }
        $registered[] = $pid;
        $names[$pid] = (string) ($entrant['player_name'] ?? ('Player #' . $pid));
    }
    $registered = array_values(array_unique($registered));
    sort($registered, SORT_NUMERIC);

    $isWorldCup = (bool) ((int) ($tournament['is_world_cup'] ?? 0));
    $hasLeague = (bool) ((int) ($tournament['has_league'] ?? 1));
    $hasCup = (bool) ((int) ($tournament['has_cup'] ?? 0));
    $tournamentName = (string) ($tournament['name'] ?? '');

    $tieBreak = [];
    foreach ($tableRows as $idx => $row) {
        $pid = (int) ($row['player_id'] ?? 0);
        if ($pid > 0 && !isset($tieBreak[$pid])) {
            $tieBreak[$pid] = (int) $idx;
        }
        if ($pid > 0 && !isset($names[$pid]) && isset($row['player_name'])) {
            $names[$pid] = (string) $row['player_name'];
        }
    }
    foreach ($registered as $i => $pid) {
        if (!isset($tieBreak[$pid])) {
            $tieBreak[$pid] = 1000 + $i;
        }
    }

    $overrides = amiga_ops_participation_finish_overrides_for_tournament($con, $tournamentId);
    $confirmed = amiga_ops_finish_confirm_overrides_are_full_ladder($overrides, $registered);
    $source = 'empty';
    $ladder = [];

    if ($confirmed) {
        $ladder = [];
        foreach ($overrides as $pid => $pos) {
            $ladder[(int) $pid] = (int) $pos;
        }
        $source = 'tier_e';
    } elseif ($registered !== []) {
        $standingRows = [];
        if (amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
            $standingRows = amiga_running_tournament_compute_standings($con, $tournamentId);
        }
        if ($standingRows === [] && $tableRows !== []) {
            foreach ($tableRows as $row) {
                $pid = (int) ($row['player_id'] ?? 0);
                if ($pid < 1) {
                    continue;
                }
                $standingRows[] = [
                    'scope_type' => 'league',
                    'scope_key' => '',
                    'player_id' => $pid,
                    'position' => (int) ($row['position'] ?? 0),
                ];
            }
        }

        $suggested = [];
        if ($standingRows !== []) {
            $suggested = amiga_participation_derive_event_finish_position(
                $standingRows,
                $tournamentName,
                $hasLeague,
                $hasCup,
                $registered,
                [],
                $isWorldCup
            );
            $source = 'derive';
        } else {
            foreach ($tableRows as $row) {
                $pid = (int) ($row['player_id'] ?? 0);
                if ($pid > 0) {
                    $suggested[$pid] = (int) ($row['position'] ?? 0);
                }
            }
            $source = 'table_order';
        }

        $ladder = amiga_ops_finish_confirm_densify_ladder($registered, $suggested, $tieBreak);
        if ($source === 'derive') {
            $allFromDerive = true;
            foreach ($registered as $pid) {
                if (($suggested[$pid] ?? null) === null) {
                    $allFromDerive = false;
                    break;
                }
            }
            if (!$allFromDerive) {
                $source = 'derive_filled';
            }
        }
    }

    $ordered = [];
    if ($ladder !== []) {
        asort($ladder, SORT_NUMERIC);
        foreach ($ladder as $pid => $pos) {
            $pid = (int) $pid;
            $pos = (int) $pos;
            $medal = '';
            if ($isWorldCup) {
                $medal = amiga_participation_wc_podium_word_from_finish($pos);
                if ($medal === '—') {
                    $medal = '';
                }
            }
            $ordered[] = [
                'player_id' => $pid,
                'player_name' => $names[$pid] ?? ('Player #' . $pid),
                'position' => $pos,
                'medal_label' => $medal,
            ];
        }
    }

    return [
        'confirmed' => $confirmed,
        'source' => $source,
        'is_world_cup' => $isWorldCup,
        'entrant_count' => count($registered),
        'ladder' => $ladder,
        'ordered' => $ordered,
    ];
}

/**
 * Parse POST finish_pos[player_id] => position into a ladder map.
 *
 * @param array<string, mixed> $postPos
 * @return array<int, int>
 */
function amiga_ops_finish_confirm_ladder_from_post(array $postPos): array
{
    $ladder = [];
    foreach ($postPos as $playerIdRaw => $positionRaw) {
        $playerId = (int) $playerIdRaw;
        $position = (int) $positionRaw;
        if ($playerId < 1) {
            continue;
        }
        $ladder[$playerId] = $position;
    }

    return $ladder;
}