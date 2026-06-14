<?php
/**
 * Player Opponents H2H — load helpers and pair resolution.
 */
declare(strict_types=1);

require_once __DIR__ . '/status_queries.php';
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/player_opponents_lib.php';
require_once __DIR__ . '/player_opponents_load.php';
require_once __DIR__ . '/k2_archive_listbox.php';

function player_opponents_h2h_parse_opponent_id(mixed $raw, int $playerId): int
{
    $opponentId = is_numeric($raw) ? (int) $raw : 0;
    if ($opponentId <= 0 || $opponentId === $playerId) {
        return 0;
    }

    return $opponentId;
}

function player_opponents_h2h_pair_games_live(mysqli $con, int $playerId, int $opponentId): int
{
    $sql = 'SELECT COUNT(*) AS n FROM ratedresults WHERE '
        . '(idA = ? AND idB = ?) OR (idA = ? AND idB = ?)';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return (int) ($row['n'] ?? 0);
}

function player_opponents_h2h_pair_games(mysqli $con, int $playerId, int $opponentId): int
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }

    $stmt = $con->prepare(
        'SELECT games FROM player_matchup_summary WHERE player_id = ? AND opponent_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return player_opponents_h2h_pair_games_live($con, $playerId, $opponentId);
    }

    return (int) $row['games'];
}

/**
 * @return array{opponent_id: int, opponent_name: string, games: int}|null
 */
function player_opponents_h2h_resolve_opponent(mysqli $con, int $playerId, int $opponentId): ?array
{
    if ($opponentId <= 0 || $opponentId === $playerId) {
        return null;
    }

    $stmt = $con->prepare('SELECT ID, Name FROM playertable WHERE ID = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    return [
        'opponent_id' => (int) $row['ID'],
        'opponent_name' => (string) $row['Name'],
        'games' => player_opponents_h2h_pair_games($con, $playerId, $opponentId),
    ];
}

/**
 * Played opponents for dropdowns (summary when present, else live aggregation).
 *
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function player_opponents_h2h_played_opponents(mysqli $con, int $playerId): array
{
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return [];
    }

    if (k2_status_table_exists($con, 'player_matchup_summary')) {
        $sql = 'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, m.games '
            . 'FROM player_matchup_summary m '
            . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
            . 'WHERE m.player_id = ? AND m.games > 0 '
            . 'ORDER BY m.games DESC, opponent_name ASC';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $playerId);
        if (!$stmt->execute()) {
            $stmt->close();

            return [];
        }
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'opponent_id' => (int) $row['opponent_id'],
                'opponent_name' => (string) $row['opponent_name'],
                'games' => (int) $row['games'],
            ];
        }
        $stmt->close();

        return $rows;
    }

    return player_opponents_h2h_played_opponents_live($con, $playerId);
}

/**
 * @return list<array{opponent_id: int, opponent_name: string, games: int}>
 */
function player_opponents_h2h_played_opponents_live(mysqli $con, int $playerId): array
{
    $playerId = max(0, $playerId);
    $sql = 'SELECT opponentID, opponentname, COUNT(*) AS games FROM ('
        . 'SELECT idB AS opponentID, nameB AS opponentname FROM ratedresults WHERE idA = ? '
        . 'UNION ALL '
        . 'SELECT idA AS opponentID, nameA AS opponentname FROM ratedresults WHERE idB = ?'
        . ') AS sides GROUP BY opponentID, opponentname ORDER BY games DESC, opponentname ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $playerId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'opponent_id' => (int) $row['opponentID'],
            'opponent_name' => (string) $row['opponentname'],
            'games' => (int) $row['games'],
        ];
    }
    $stmt->close();

    return $rows;
}

function k2_h2h_games_meta_label(int $games): string
{
    return $games . ' game' . ($games === 1 ? '' : 's');
}

/**
 * @return array{player_id: int, name: string, display: bool, rank: ?int, rating: mixed}|null
 */
function player_opponents_h2h_load_player_card(mysqli $con, int $playerId): ?array
{
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return null;
    }

    $stmt = $con->prepare('SELECT Name, Rating, Display FROM playertable WHERE ID = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    $display = (int) ($row['Display'] ?? 0) === 1;
    $rank = null;
    if ($display) {
        $rankStmt = $con->prepare(
            'SELECT COUNT(*) + 1 AS plrank FROM playertable '
            . 'WHERE Display = 1 AND Rating > (SELECT Rating FROM playertable WHERE ID = ? LIMIT 1)'
        );
        if ($rankStmt) {
            $rankStmt->bind_param('i', $playerId);
            if ($rankStmt->execute()) {
                $rankRes = $rankStmt->get_result();
                $rankRow = $rankRes ? $rankRes->fetch_assoc() : null;
                if ($rankRes) {
                    $rankRes->free();
                }
                if ($rankRow !== null) {
                    $rank = (int) $rankRow['plrank'];
                }
            }
            $rankStmt->close();
        }
    }

    return [
        'player_id' => $playerId,
        'name' => (string) $row['Name'],
        'display' => $display,
        'rank' => $rank,
        'rating' => $row['Rating'],
    ];
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function player_opponents_h2h_pair_record_live(mysqli $con, int $playerId, int $opponentId): ?array
{
    $sql = 'SELECT COUNT(*) AS games, COALESCE(SUM(win), 0) AS wins, COALESCE(SUM(draw), 0) AS draws, '
        . 'COALESCE(SUM(defeat), 0) AS losses, COALESCE(SUM(goalsfor), 0) AS goals_for, '
        . 'COALESCE(SUM(goalsagainst), 0) AS goals_against FROM ('
        . 'SELECT homewin AS win, draw, awaywin AS defeat, goalsA AS goalsfor, goalsB AS goalsagainst '
        . 'FROM ratedresults WHERE idA = ? AND idB = ? '
        . 'UNION ALL '
        . 'SELECT awaywin AS win, draw, homewin AS defeat, goalsB AS goalsfor, goalsA AS goalsagainst '
        . 'FROM ratedresults WHERE idA = ? AND idB = ?'
        . ') AS sides';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return null;
    }

    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function player_opponents_h2h_pair_record(mysqli $con, int $playerId, int $opponentId): ?array
{
    if (!k2_status_table_exists($con, 'player_matchup_summary')) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }

    $stmt = $con->prepare(
        'SELECT games, wins, draws, losses, goals_for, goals_against '
        . 'FROM player_matchup_summary WHERE player_id = ? AND opponent_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }
    $stmt->bind_param('ii', $playerId, $opponentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === null) {
        return player_opponents_h2h_pair_record_live($con, $playerId, $opponentId);
    }

    return [
        'games' => (int) $row['games'],
        'wins' => (int) $row['wins'],
        'draws' => (int) $row['draws'],
        'losses' => (int) $row['losses'],
        'goals_for' => (int) $row['goals_for'],
        'goals_against' => (int) $row['goals_against'],
    ];
}

/**
 * One identity card: avatar ring + name + rank/rating (hero-style).
 * Whole card links to the player profile when an id is present.
 *
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $card
 */
function k2_h2h_poster_card_html(array $card, string $side): string
{
    $name = (string) ($card['name'] ?? '');
    $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
    $pid = (int) ($card['player_id'] ?? 0);
    $display = !empty($card['display']);
    $rank = ($display && ($card['rank'] ?? null) !== null) ? '#' . (int) $card['rank'] : '—';
    $rating = ($display && isset($card['rating']) && !k2_db_is_null($card['rating']))
        ? k2_fmt_int($card['rating'], '—')
        : '—';
    $href = $pid > 0 ? k2_route('player-profile', ['id' => $pid]) : '';

    $inner = '<div class="k2-h2h2-card__media">'
        . '<div class="k2-h2h2-card__avatar" aria-hidden="true">' . k2_h($initial) . '</div>'
        . '</div>'
        . '<div class="k2-h2h2-card__body">'
        . '<p class="k2-h2h2-card__name">' . k2_h($name) . '</p>'
        . '<dl class="k2-h2h2-card__stats">'
        . '<div class="k2-h2h2-card__stat"><dt>Rank</dt><dd>' . k2_h($rank) . '</dd></div>'
        . '<div class="k2-h2h2-card__stat"><dt>Rating</dt><dd>' . k2_h($rating) . '</dd></div>'
        . '</dl>'
        . '</div>';

    $class = 'k2-h2h2-card k2-h2h2-card--' . k2_h($side);
    if ($href !== '') {
        $label = $name !== '' ? 'View ' . $name . ' profile' : 'View player profile';

        return '<a class="' . $class . ' k2-h2h2-card--link" href="' . k2_h($href) . '"'
            . ' aria-label="' . k2_h($label) . '">' . $inner . '</a>';
    }

    return '<article class="' . $class . '">' . $inner . '</article>';
}

/**
 * Versus poster: mirrored identity cards around a `vs`, W/D/L hero, lead meter, goals.
 *
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $subjectCard
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $opponentCard
 * @param array{games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int}|null $record
 */
function player_opponents_render_h2h_poster(
    array $subjectCard,
    array $opponentCard,
    ?array $record,
    int $games
): void {
    $hasGames = $record !== null && $games > 0;
    $w = $hasGames ? (int) $record['wins'] : 0;
    $d = $hasGames ? (int) $record['draws'] : 0;
    $l = $hasGames ? (int) $record['losses'] : 0;
    $gf = $hasGames ? (int) $record['goals_for'] : 0;
    $ga = $hasGames ? (int) $record['goals_against'] : 0;
    $total = $w + $d + $l;

    $pct = static function (int $part) use ($total): string {
        if ($total <= 0) {
            return '0';
        }

        return rtrim(rtrim(number_format(($part / $total) * 100, 3, '.', ''), '0'), '.');
    };

    $subjectName = (string) ($subjectCard['name'] ?? '');
    $opponentName = (string) ($opponentCard['name'] ?? '');
    $meterLabel = sprintf(
        '%s: %d won, %d drawn, %d lost in %d games versus %s.',
        $subjectName,
        $w,
        $d,
        $l,
        $games,
        $opponentName
    );
    ?>
<section class="k2-h2h2-poster k2-h2h2-poster--mirrored"<?php echo $hasGames ? '' : ' data-empty="1"'; ?>>
	<div class="k2-h2h2-marquee">
		<?php echo k2_h2h_poster_card_html($subjectCard, 'subject'); ?>
		<div class="k2-h2h2-vs" aria-hidden="true">vs</div>
		<?php echo k2_h2h_poster_card_html($opponentCard, 'opponent'); ?>
	</div>

	<?php if ($hasGames) { ?>
	<div class="k2-h2h2-record" role="group" aria-label="Win, draw and loss record">
		<div class="k2-h2h2-stat k2-h2h2-stat--win">
			<span class="k2-h2h2-num blue"><?php echo k2_h(k2_fmt_int($w, '0')); ?></span>
			<span class="k2-h2h2-lab">Won</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--draw">
			<span class="k2-h2h2-num"><?php echo k2_h(k2_fmt_int($d, '0')); ?></span>
			<span class="k2-h2h2-lab">Drew</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--loss">
			<span class="k2-h2h2-num red"><?php echo k2_h(k2_fmt_int($l, '0')); ?></span>
			<span class="k2-h2h2-lab">Lost</span>
		</div>
	</div>

	<div class="k2-h2h2-meter" role="img" aria-label="<?php echo k2_h($meterLabel); ?>">
		<span class="k2-h2h2-seg k2-h2h2-seg--win<?php echo $w > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($w); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--draw<?php echo $d > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($d); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--loss<?php echo $l > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($l); ?>%"></span>
	</div>

	<p class="k2-h2h2-goals">
		<span class="k2-h2h2-goals-line">
			<span class="k2-h2h2-goal k2-h2h2-goal--for"><?php echo k2_h(k2_fmt_int($gf, '0')); ?></span>
			<span class="k2-h2h2-goals-sep" aria-hidden="true">–</span>
			<span class="k2-h2h2-goal k2-h2h2-goal--against"><?php echo k2_h(k2_fmt_int($ga, '0')); ?></span>
		</span>
		<span class="k2-h2h2-goals-label">Goals</span>
	</p>
	<?php } else { ?>
	<p class="k2-h2h2-none">No rated games yet</p>
	<?php } ?>
</section>
	<?php
}

/**
 * @param list<array{opponent_id: int, opponent_name: string, games: int}> $rows
 */
function k2_h2h_opponent_listbox_render(
    string $inputId,
    string $selectedValue,
    array $rows,
    string $ariaLabel,
    string $placeholder = 'Choose opponent…',
    string $emptyLabel = 'No opponents yet'
): void {
    $selectedLabel = '';
    $selectedValue = (string) $selectedValue;
    foreach ($rows as $row) {
        if ((string) (int) $row['opponent_id'] === $selectedValue) {
            $selectedLabel = (string) $row['opponent_name'];
            break;
        }
    }

    $listboxId = $inputId . '-listbox';
    $triggerId = $inputId . '-trigger';
    $hasRows = $rows !== [];
    $labelClass = 'k2-archive-listbox__label';
    if ($selectedLabel === '') {
        $labelClass .= ' k2-archive-listbox__label--placeholder';
        $selectedLabel = $hasRows ? $placeholder : $emptyLabel;
    }
    ?>
<div class="k2-archive-listbox k2-player-opponents-h2h__listbox" data-k2-archive-listbox>
    <input type="hidden" id="<?php echo k2_archive_listbox_h($inputId); ?>" class="k2-archive-listbox__value" value="<?php echo k2_archive_listbox_h($selectedValue); ?>" />
    <button
        type="button"
        id="<?php echo k2_archive_listbox_h($triggerId); ?>"
        class="k2-archive-listbox__trigger server-period-activity-leaderboard__input"
        aria-label="<?php echo k2_archive_listbox_h($ariaLabel); ?>"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="<?php echo k2_archive_listbox_h($listboxId); ?>"
        <?php echo $hasRows ? '' : ' disabled="disabled"'; ?>
    >
        <span class="<?php echo k2_archive_listbox_h($labelClass); ?>"><?php echo k2_archive_listbox_h($selectedLabel); ?></span>
        <span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
    </button>
    <ul id="<?php echo k2_archive_listbox_h($listboxId); ?>" class="k2-archive-listbox__panel" role="listbox" tabindex="-1" hidden="hidden">
<?php foreach ($rows as $row) {
    $value = (string) (int) $row['opponent_id'];
    $name = (string) $row['opponent_name'];
    $games = (int) $row['games'];
    $sel = $value === $selectedValue;
    $optClass = 'k2-archive-listbox__option k2-h2h-listbox__option' . ($sel ? ' is-selected' : '');
    ?>
        <li
            class="<?php echo k2_archive_listbox_h($optClass); ?>"
            role="option"
            data-value="<?php echo k2_archive_listbox_h($value); ?>"
            data-trigger-label="<?php echo k2_archive_listbox_h($name); ?>"
            aria-selected="<?php echo $sel ? 'true' : 'false'; ?>"
        >
            <span class="player-search-name k2-h2h-listbox__name"><?php echo k2_archive_listbox_h($name); ?></span>
            <span class="player-search-meta k2-h2h-listbox__meta"><?php echo k2_archive_listbox_h(k2_h2h_games_meta_label($games)); ?></span>
        </li>
<?php } ?>
    </ul>
</div>
    <?php
}

function player_opponents_render_h2h_panel(
    mysqli $con,
    int $playerId,
    string $playerName,
    int $selectedOpponentId = 0,
    bool $defaultToTopOpponent = false
): void {
    $playerId = max(0, $playerId);
    $playerName = trim($playerName);
    if ($playerName === '') {
        $playerName = '#' . $playerId;
    }

    $played = player_opponents_h2h_played_opponents($con, $playerId);
    if ($defaultToTopOpponent && $selectedOpponentId <= 0 && $played !== []) {
        $selectedOpponentId = (int) $played[0]['opponent_id'];
    }
    $byAlpha = $played;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp($a['opponent_name'], $b['opponent_name']);
        }
    );

    $pair = $selectedOpponentId > 0
        ? player_opponents_h2h_resolve_opponent($con, $playerId, $selectedOpponentId)
        : null;

    $searchUid = 'k2-h2h-search-' . $playerId;
    ?>
<div
	class="k2-player-opponents-h2h"
	data-k2-carry-scroll
	data-player-id="<?php echo $playerId; ?>"
	data-h2h-base="<?php echo k2_h(player_opponents_href($playerId, 'h2h')); ?>"
>
	<div class="k2-player-opponents-h2h__pickers">
		<div class="k2-player-opponents-h2h__search player-search" role="search">
			<label class="player-search-label" for="<?php echo k2_h($searchUid); ?>">Search</label>
			<input
				id="<?php echo k2_h($searchUid); ?>"
				class="player-search-input k2-header-search__input k2-player-opponents-h2h__search-input"
				type="search"
				maxlength="32"
				autocomplete="off"
				spellcheck="false"
				placeholder="Player name…"
				aria-expanded="false"
				aria-controls="<?php echo k2_h($searchUid); ?>-results"
			/>
			<ul
				id="<?php echo k2_h($searchUid); ?>-results"
				class="player-search-results k2-player-opponents-h2h__search-results"
				role="listbox"
				hidden
			></ul>
		</div>
		<div class="k2-player-opponents-h2h__listbox-wrap">
			<label class="k2-player-opponents-h2h__select-label" for="k2-h2h-games-<?php echo $playerId; ?>-trigger">By games played</label>
			<?php k2_h2h_opponent_listbox_render(
			    'k2-h2h-games-' . $playerId,
			    (string) $selectedOpponentId,
			    $played,
			    'Choose opponent by games played'
			); ?>
		</div>
		<div class="k2-player-opponents-h2h__listbox-wrap">
			<label class="k2-player-opponents-h2h__select-label" for="k2-h2h-alpha-<?php echo $playerId; ?>-trigger">A–Z</label>
			<?php k2_h2h_opponent_listbox_render(
			    'k2-h2h-alpha-' . $playerId,
			    (string) $selectedOpponentId,
			    $byAlpha,
			    'Choose opponent A to Z'
			); ?>
		</div>
	</div>

	<div class="k2-player-opponents-h2h__stage">
		<?php if ($pair === null) { ?>
		<p class="k2-player-opponents-h2h__prompt k2-hub-page-intro">Choose an opponent above to compare head-to-head.</p>
		<?php } else {
		    $subjectCard = player_opponents_h2h_load_player_card($con, $playerId);
		    $opponentCard = player_opponents_h2h_load_player_card($con, $pair['opponent_id']);
		    if ($subjectCard !== null && $opponentCard !== null) {
		        $record = $pair['games'] > 0
		            ? player_opponents_h2h_pair_record($con, $playerId, $pair['opponent_id'])
		            : null;
		        $games = (int) $pair['games'];
		        player_opponents_render_h2h_poster($subjectCard, $opponentCard, $record, $games);
		    } else { ?>
		<p class="k2-player-opponents-h2h__empty">Could not load player data for this pairing.</p>
		<?php }
		    } ?>
	</div>
</div>
    <?php
}
