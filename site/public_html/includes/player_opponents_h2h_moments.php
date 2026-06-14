<?php
/**
 * Player Opponents H2H — pair moments (read-time ratedresults scan, 3×3 scorecard deck).
 *
 * Scores keep their true stored orientation: NameA GoalsA – GoalsB NameB (a 0–17
 * is never flipped to 17–0). The page subject is tinted blue and the opponent red
 * on whichever side (A or B) they appear — rivalry identity, not a hero angle.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/player_feast_helpers.php';

/**
 * @return list<array<string, mixed>>
 */
function player_opponents_h2h_pair_games_rows(mysqli $con, int $playerId, int $opponentId): array
{
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId <= 0 || $opponentId <= 0 || $playerId === $opponentId) {
        return [];
    }

    $sql = 'SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, ActualScore '
        . 'FROM ratedresults WHERE (idA = ? AND idB = ?) OR (idA = ? AND idB = ?) '
        . 'ORDER BY Date ASC, id ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = player_opponents_h2h_normalize_game_row($row, $playerId);
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * Normalise a rated game keeping true A/B orientation plus subject-relative
 * metrics used only to pick which game fills each slot.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function player_opponents_h2h_normalize_game_row(array $row, int $subjectId): array
{
    $gameId = (int) pm_row_col($row, 'id');
    $idA = (int) pm_row_col($row, 'idA');
    $idB = (int) pm_row_col($row, 'idB');
    $nameA = (string) pm_row_col($row, 'NameA');
    $nameB = (string) pm_row_col($row, 'NameB');
    $goalsA = (int) pm_row_col($row, 'GoalsA');
    $goalsB = (int) pm_row_col($row, 'GoalsB');

    if ($goalsA > $goalsB) {
        $winner = 'a';
    } elseif ($goalsB > $goalsA) {
        $winner = 'b';
    } else {
        $winner = 'draw';
    }

    $subjectIsA = $idA === $subjectId;
    $subjectGf = $subjectIsA ? $goalsA : $goalsB;
    $subjectGa = $subjectIsA ? $goalsB : $goalsA;
    $subjectWin = ($subjectIsA && $winner === 'a') || (!$subjectIsA && $winner === 'b');
    $subjectLoss = ($subjectIsA && $winner === 'b') || (!$subjectIsA && $winner === 'a');
    $draw = $winner === 'draw';

    $dateRaw = (string) pm_row_col($row, 'Date');
    $dateTs = strtotime($dateRaw);

    return [
        'game_id' => $gameId,
        'href' => k2_route('game', ['id' => $gameId]),
        'date' => $dateRaw,
        'date_label' => $dateTs !== false ? date('j M Y', $dateTs) : $dateRaw,
        'id_a' => $idA,
        'id_b' => $idB,
        'name_a' => $nameA,
        'name_b' => $nameB,
        'goals_a' => $goalsA,
        'goals_b' => $goalsB,
        'subject_is_a' => $subjectIsA,
        'winner' => $winner,
        'subject_gf' => $subjectGf,
        'subject_ga' => $subjectGa,
        'total_goals' => $goalsA + $goalsB,
        'draw' => $draw,
        'subject_win' => $subjectWin,
        'subject_loss' => $subjectLoss,
        'win_margin_subject' => $subjectWin ? $subjectGf - $subjectGa : 0,
        'win_margin_opponent' => $subjectLoss ? $subjectGa - $subjectGf : 0,
    ];
}

function k2_h2h_moment_short_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'Player';
    }
    $space = strpos($name, ' ');
    if ($space === false) {
        return $name;
    }

    return substr($name, 0, $space);
}

/**
 * Slot definitions in the locked 3×3 order.
 *
 * tone drives the active card's accent: 'subject' / 'opponent' tint to the
 * rivalry colours; the rest are thematic-neutral.
 *
 * @return list<array{key: string, kicker: string, tone: string}>
 */
function player_opponents_h2h_moment_slot_defs(string $subjectShort, string $opponentShort): array
{
    return [
        ['key' => 'first_game', 'kicker' => 'First game', 'tone' => 'bookend'],
        ['key' => 'last_game', 'kicker' => 'Latest game', 'tone' => 'bookend'],
        ['key' => 'most_goals_in_game', 'kicker' => 'Goal feast', 'tone' => 'feast'],
        ['key' => 'most_scored_subject', 'kicker' => $subjectShort . "'s best haul", 'tone' => 'subject'],
        ['key' => 'most_scored_opponent', 'kicker' => $opponentShort . "'s best haul", 'tone' => 'opponent'],
        ['key' => 'biggest_draw', 'kicker' => 'Highest draw', 'tone' => 'draw'],
        ['key' => 'subject_biggest_win', 'kicker' => $subjectShort . "'s biggest win", 'tone' => 'subject'],
        ['key' => 'opponent_biggest_win', 'kicker' => $opponentShort . "'s biggest win", 'tone' => 'opponent'],
        ['key' => 'fewest_goals_in_game', 'kicker' => 'Tightest game', 'tone' => 'tight'],
    ];
}

/**
 * @param list<array<string, mixed>> $games
 * @return list<array<string, mixed>>
 */
function player_opponents_h2h_moments_slots(
    array $games,
    string $subjectName,
    string $opponentName
): array {
    $subjectShort = k2_h2h_moment_short_name($subjectName);
    $opponentShort = k2_h2h_moment_short_name($opponentName);
    $defs = player_opponents_h2h_moment_slot_defs($subjectShort, $opponentShort);

    if ($games === []) {
        return array_map(
            static function (array $def): array {
                return array_merge($def, ['active' => false, 'game' => null]);
            },
            $defs
        );
    }

    $first = $games[0];
    $last = $games[count($games) - 1];

    $mostTotal = null;
    $fewestTotal = null;
    $mostSubject = null;
    $mostOpponent = null;
    $biggestDraw = null;
    $subjectBestWin = null;
    $opponentBestWin = null;

    foreach ($games as $game) {
        $total = (int) $game['total_goals'];
        if ($mostTotal === null || $total > (int) $mostTotal['total_goals']) {
            $mostTotal = $game;
        }
        if ($fewestTotal === null || $total < (int) $fewestTotal['total_goals']) {
            $fewestTotal = $game;
        }

        $subjectGf = (int) $game['subject_gf'];
        if ($mostSubject === null || $subjectGf > (int) $mostSubject['subject_gf']) {
            $mostSubject = $game;
        }

        $subjectGa = (int) $game['subject_ga'];
        if ($mostOpponent === null || $subjectGa > (int) $mostOpponent['subject_ga']) {
            $mostOpponent = $game;
        }

        if (!empty($game['draw'])) {
            if ($biggestDraw === null || $subjectGf > (int) $biggestDraw['subject_gf']) {
                $biggestDraw = $game;
            }
        }

        if (!empty($game['subject_win'])) {
            $margin = (int) $game['win_margin_subject'];
            if ($subjectBestWin === null || $margin > (int) $subjectBestWin['win_margin_subject']) {
                $subjectBestWin = $game;
            }
        }

        if (!empty($game['subject_loss'])) {
            $margin = (int) $game['win_margin_opponent'];
            if ($opponentBestWin === null || $margin > (int) $opponentBestWin['win_margin_opponent']) {
                $opponentBestWin = $game;
            }
        }
    }

    $byKey = [
        'first_game' => ['active' => true, 'game' => $first],
        'last_game' => ['active' => true, 'game' => $last],
        'most_goals_in_game' => ['active' => true, 'game' => $mostTotal],
        'most_scored_subject' => ['active' => true, 'game' => $mostSubject],
        'most_scored_opponent' => ['active' => true, 'game' => $mostOpponent],
        'fewest_goals_in_game' => ['active' => true, 'game' => $fewestTotal],
        'biggest_draw' => ['active' => $biggestDraw !== null, 'game' => $biggestDraw],
        'subject_biggest_win' => ['active' => $subjectBestWin !== null, 'game' => $subjectBestWin],
        'opponent_biggest_win' => ['active' => $opponentBestWin !== null, 'game' => $opponentBestWin],
    ];

    $slots = [];
    foreach ($defs as $def) {
        $key = $def['key'];
        $slots[] = array_merge($def, [
            'active' => (bool) $byKey[$key]['active'],
            'game' => $byKey[$key]['game'],
        ]);
    }

    return $slots;
}

/**
 * One side of the scoreline: full name + goals, tinted by player identity.
 *
 * @param array<string, mixed> $game
 */
function k2_h2h_moment_side_html(array $game, string $ab): string
{
    $isSubject = ($ab === 'a') === (bool) $game['subject_is_a'];
    $name = $ab === 'a' ? (string) $game['name_a'] : (string) $game['name_b'];
    $goals = $ab === 'a' ? (int) $game['goals_a'] : (int) $game['goals_b'];
    $winner = (string) $game['winner'];

    $role = $isSubject ? 'subject' : 'opponent';
    $class = 'k2-h2h2-mcard__side k2-h2h2-mcard__side--' . $role;
    if ($winner === $ab) {
        $class .= ' is-winner';
    } elseif ($winner !== 'draw') {
        $class .= ' is-beaten';
    }

    return '<div class="' . $class . '">'
        . '<span class="k2-h2h2-mcard__name">' . k2_h($name) . '</span>'
        . '<span class="k2-h2h2-mcard__goals">' . k2_h(k2_fmt_int($goals, '0')) . '</span>'
        . '</div>';
}

/**
 * @param list<array<string, mixed>> $slots
 */
function player_opponents_render_h2h_moments_grid(array $slots): void
{
    if ($slots === []) {
        return;
    }
    ?>
<section class="k2-h2h2-moments" aria-label="Rivalry moments">
	<div class="k2-h2h2-moments__grid">
		<?php foreach ($slots as $slot) {
		    $active = !empty($slot['active']) && is_array($slot['game']);
		    $game = $active ? $slot['game'] : null;
		    $class = 'k2-h2h2-mcard';
		    if ($active && $game !== null) {
		        if (!empty($game['draw'])) {
		            $class .= ' is-drawn';
		        } elseif (!empty($game['subject_win'])) {
		            $class .= ' is-won-subject';
		        } else {
		            $class .= ' is-won-opponent';
		        }
		        $class .= ' is-active';
		    } else {
		        $class .= ' is-dim';
		    }
		    ?>
		<article class="<?php echo k2_h($class); ?>">
			<span class="k2-h2h2-mcard__kicker"><?php echo k2_h((string) ($slot['kicker'] ?? '')); ?></span>
			<?php if ($active && $game !== null) { ?>
			<a class="k2-h2h2-mcard__board" href="<?php echo k2_h((string) $game['href']); ?>">
				<?php echo k2_h2h_moment_side_html($game, 'a'); ?>
				<span class="k2-h2h2-mcard__dash" aria-hidden="true">–</span>
				<?php echo k2_h2h_moment_side_html($game, 'b'); ?>
			</a>
			<span class="k2-h2h2-mcard__date"><?php echo k2_h((string) $game['date_label']); ?></span>
			<?php } else { ?>
			<span class="k2-h2h2-mcard__board k2-h2h2-mcard__board--empty" aria-hidden="true">
				<span class="k2-h2h2-mcard__pending">—</span>
			</span>
			<span class="k2-h2h2-mcard__date k2-h2h2-mcard__date--empty">Not played yet</span>
			<?php } ?>
		</article>
		<?php } ?>
	</div>
</section>
    <?php
}
