<?php
/**
 * Amiga Opponents country grain — H2H poster, pickers, pair detail (no charts).
 *
 * @see docs/amiga-opponents-country-grain-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_player_opponents_lib.php';
require_once __DIR__ . '/amiga_player_opponents_country_load.php';
require_once __DIR__ . '/amiga_player_opponents_country_perf_lib.php';
require_once __DIR__ . '/amiga_player_opponents_h2h.php';
require_once __DIR__ . '/amiga_player_h2h_country_lib.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';
require_once __DIR__ . '/player_opponents_h2h_charts.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/performance_rating.php';

function amiga_player_opponents_h2h_parse_country_param(mixed $raw): string
{
    if ($raw === null) {
        return '';
    }

    if (is_string($raw) || is_int($raw) || is_float($raw)) {
        return amiga_player_opponents_country_token_from_field((string) $raw);
    }

    return '';
}

/**
 * @return list<array{country_token: string, games: int}>
 */
function amiga_player_opponents_h2h_played_countries(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $out = [];
    foreach (amiga_player_opponents_country_rows($con, $playerId, $ctx) as $row) {
        $out[] = [
            'country_token' => (string) $row['country_token'],
            'games' => (int) $row['games'],
        ];
    }

    return $out;
}

/**
 * Default H2H country when `country=` is omitted: most-played opponent country,
 * excluding the hero's own nation (falls back to top bucket if all are domestic).
 *
 * @param list<array{country_token: string, games: int}> $played sorted by games desc
 */
function amiga_player_opponents_h2h_default_country_token(array $played, string $heroCountry = ''): string
{
    if ($played === []) {
        return '';
    }

    $heroToken = amiga_player_opponents_country_token_from_field($heroCountry);
    foreach ($played as $row) {
        $token = (string) $row['country_token'];
        if ($token !== $heroToken) {
            return $token;
        }
    }

    return (string) $played[0]['country_token'];
}

/**
 * @return array{country_token: string, games: int}|null
 */
function amiga_player_opponents_h2h_resolve_country(
    mysqli $con,
    int $playerId,
    string $countryToken,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    $countryToken = amiga_player_opponents_country_token_from_field($countryToken);
    if ($countryToken === '') {
        return null;
    }

    $bucket = amiga_player_opponents_country_bucket($con, $playerId, $countryToken, $ctx);
    if ($bucket === null) {
        return null;
    }

    return [
        'country_token' => (string) $bucket['country_token'],
        'games' => (int) $bucket['games'],
    ];
}

/**
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}|null
 */
function amiga_player_opponents_h2h_country_record(array $bucket): ?array
{
    if ((int) ($bucket['games'] ?? 0) <= 0) {
        return null;
    }

    return [
        'games' => (int) $bucket['games'],
        'wins' => (int) $bucket['wins'],
        'draws' => (int) $bucket['draws'],
        'losses' => (int) $bucket['losses'],
        'goals_for' => (int) $bucket['goals_for'],
        'goals_against' => (int) $bucket['goals_against'],
    ];
}

function k2_h2h_poster_country_player_count_html(int $playerCount): string
{
    if ($playerCount === 1) {
        return '1 player';
    }

    return k2_fmt_int($playerCount, '0') . ' players';
}

function k2_h2h_poster_country_card_html(string $countryToken, string $side = 'opponent', ?int $playerCount = null): string
{
    $side = $side === 'subject' ? 'subject' : 'opponent';
    $label = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;
    $href = k2_amiga_country_roster_href($countryToken);
    $meta = k2_amiga_country_flag_meta($countryToken);
    $mediaInner = $meta !== null
        ? '<div class="k2-h2h2-card__avatar k2-h2h2-card__avatar--country" aria-hidden="true">'
            . k2_amiga_country_flag_img($countryToken, ['decorative' => true])
            . '</div>'
        : '<div class="k2-h2h2-card__avatar" aria-hidden="true">' . k2_h(mb_strtoupper(mb_substr($label, 0, 1))) . '</div>';

    $rosterLabel = 'View players from ' . $label;
    $sideInk = $side === 'subject' ? 'blue' : 'red';
    $statValue = $playerCount !== null
        ? k2_h(k2_h2h_poster_country_player_count_html($playerCount))
        : k2_h($label);
    $inner = '<div class="k2-h2h2-card__media">' . $mediaInner . '</div>'
        . '<div class="k2-h2h2-card__body">'
        . '<p class="k2-h2h2-card__name">' . k2_h($label) . '</p>'
        . '<div class="k2-h2h2-card__stats" role="group" aria-label="Players">'
        . k2_h2h_poster_card_stat_html('Players', $statValue, $sideInk)
        . '</div>'
        . '</div>';

    $class = 'k2-h2h2-card k2-h2h2-card--' . $side . ' k2-h2h2-card--country k2-h2h2-card--link';

    return '<a class="' . $class . '" href="' . k2_h($href) . '" aria-label="' . k2_h($rosterLabel) . '">' . $inner . '</a>';
}

/**
 * @param array{player_id:int,name:string,display:bool,rank:?int,rating:mixed} $subjectCard
 * @param array{games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int}|null $record
 */
function player_opponents_render_h2h_country_poster(
    mysqli $con,
    array $subjectCard,
    string $countryToken,
    ?array $record,
    int $games,
    ?AmigaSnapshotContext $ctx = null
): void {
    $countryLabel = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;
    $countryPlayerCount = amiga_countries_player_count($con, $countryToken, $ctx);
    $hasGames = $record !== null && $games > 0;
    $w = $hasGames ? (int) $record['wins'] : 0;
    $d = $hasGames ? (int) $record['draws'] : 0;
    $l = $hasGames ? (int) $record['losses'] : 0;
    $total = $w + $d + $l;

    $pct = static function (int $part) use ($total): string {
        if ($total <= 0) {
            return '0';
        }

        return rtrim(rtrim(number_format(($part / $total) * 100, 3, '.', ''), '0'), '.');
    };

    $subjectName = (string) ($subjectCard['name'] ?? '');
    $meterLabel = sprintf(
        '%s: %d won, %d drawn, %d lost in %d games versus players from %s.',
        $subjectName,
        $w,
        $d,
        $l,
        $games,
        $countryLabel
    );
    ?>
<section class="k2-h2h2-poster" id="h2h-rivalry"<?php echo $hasGames ? '' : ' data-empty="1"'; ?>>
	<div class="k2-h2h2-marquee">
		<?php echo k2_h2h_poster_card_html($subjectCard, 'subject'); ?>
		<div class="k2-h2h2-vs" aria-hidden="true">vs</div>
		<?php echo k2_h2h_poster_country_card_html($countryToken, 'opponent', $countryPlayerCount); ?>
	</div>

	<?php if ($hasGames) { ?>
	<div class="k2-h2h2-record" role="group" aria-label="<?php echo k2_h(sprintf('%s wins, draws, losses vs %s', $subjectName, $countryLabel)); ?>">
		<div class="k2-h2h2-stat k2-h2h2-stat--win">
			<span class="k2-h2h2-num blue"><?php echo k2_h(k2_fmt_int($w, '0')); ?></span>
			<span class="k2-h2h2-lab">Wins</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--draw">
			<span class="k2-h2h2-num"><?php echo k2_h(k2_fmt_int($d, '0')); ?></span>
			<span class="k2-h2h2-lab">Draws</span>
		</div>
		<div class="k2-h2h2-stat k2-h2h2-stat--opp-win">
			<span class="k2-h2h2-num red"><?php echo k2_h(k2_fmt_int($l, '0')); ?></span>
			<span class="k2-h2h2-lab">Wins</span>
		</div>
	</div>

	<div class="k2-h2h2-meter" role="img" aria-label="<?php echo k2_h($meterLabel); ?>">
		<span class="k2-h2h2-seg k2-h2h2-seg--win<?php echo $w > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($w); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--draw<?php echo $d > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($d); ?>%"></span>
		<span class="k2-h2h2-seg k2-h2h2-seg--loss<?php echo $l > 0 ? ' is-on' : ''; ?>" style="width: <?php echo $pct($l); ?>%"></span>
	</div>
	<?php } else { ?>
	<p class="k2-h2h2-none">No rated games yet</p>
	<?php } ?>
</section>
	<?php
}

/**
 * @param array{player_id:int,name:string} $subjectCard
 * @param array<string, mixed> $bucket
 */
function player_opponents_render_h2h_country_pair_detail(
    array $subjectCard,
    string $countryToken,
    array $bucket
): void {
    $games = (int) ($bucket['games'] ?? 0);
    if ($games <= 0) {
        return;
    }

    $countryLabel = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;
    $opponentCard = [
        'player_id' => 0,
        'name' => 'players from ' . $countryLabel,
        'display' => false,
        'rank' => null,
        'rating' => null,
        'profile_href' => k2_amiga_country_roster_href($countryToken),
        'country' => $countryToken,
    ];

    $detail = player_opponents_h2h_pair_detail_map_row($bucket, true);
    $detail['perf_rating_subject'] = isset($bucket['performance_rating']) && $bucket['performance_rating'] !== null
        ? (int) round((float) $bucket['performance_rating'])
        : null;
    $detail['perf_rating_opponent'] = isset($bucket['performance_rating_vs_hero']) && $bucket['performance_rating_vs_hero'] !== null
        ? (int) round((float) $bucket['performance_rating_vs_hero'])
        : null;

    player_opponents_render_h2h_pair_detail($subjectCard, $opponentCard, $detail);
}

function player_opponents_render_h2h_country_all_games_link(
    int $playerId,
    string $countryToken,
    int $games
): void {
    if ($games <= 0 || $playerId <= 0) {
        return;
    }

    $countryLabel = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;
    $href = amiga_player_opponents_games_filtered_by_country_href($playerId, $countryToken);
    $label = sprintf(
        'All %s rated games vs players from %s →',
        k2_fmt_int($games, '0'),
        $countryLabel
    );
    ?>
<p class="k2-h2h2-all-games">
	<a class="k2-h2h2-all-games__link" href="<?php echo k2_h($href); ?>"><?php echo k2_h($label); ?></a>
</p>
    <?php
}

/**
 * @param list<array{country_token: string, games: int}> $rows
 */
function k2_h2h_country_listbox_render(
    string $inputId,
    string $selectedValue,
    array $rows,
    string $ariaLabel,
    string $placeholder = 'Choose country…',
    string $emptyLabel = 'No countries yet',
    bool $showNameInTrigger = true
): void {
    $selectedLabel = '';
    $selectedValue = (string) $selectedValue;
    if ($showNameInTrigger) {
        foreach ($rows as $row) {
            if ((string) $row['country_token'] === $selectedValue) {
                $selectedLabel = (string) $row['country_token'];
                break;
            }
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
    $hiddenValue = $showNameInTrigger ? $selectedValue : '';
    ?>
<div class="k2-archive-listbox k2-player-opponents-h2h__listbox" data-k2-archive-listbox>
    <input type="hidden" id="<?php echo k2_archive_listbox_h($inputId); ?>" class="k2-archive-listbox__value" value="<?php echo k2_archive_listbox_h($hiddenValue); ?>" />
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
    $value = (string) $row['country_token'];
    $name = (string) $row['country_token'];
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

function amiga_player_opponents_render_country_h2h_panel(
    mysqli $con,
    int $playerId,
    string $playerName,
    string $selectedCountryToken = '',
    bool $defaultToTopCountry = false,
    ?string $pickSource = null,
    ?AmigaSnapshotContext $ctx = null,
    string $heroCountry = ''
): void {
    $playerId = max(0, $playerId);
    $playerName = trim($playerName);
    if ($playerName === '') {
        $playerName = '#' . $playerId;
    }

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $played = amiga_player_opponents_h2h_played_countries($con, $playerId, $ctx);
    if ($defaultToTopCountry && $selectedCountryToken === '' && $played !== []) {
        $selectedCountryToken = amiga_player_opponents_h2h_default_country_token($played, $heroCountry);
    }

    $byAlpha = $played;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp((string) $a['country_token'], (string) $b['country_token']);
        }
    );

    $pair = $selectedCountryToken !== ''
        ? amiga_player_opponents_h2h_resolve_country($con, $playerId, $selectedCountryToken, $ctx)
        : null;
    $bucket = ($pair !== null && $selectedCountryToken !== '')
        ? amiga_player_opponents_country_bucket($con, $playerId, $selectedCountryToken, $ctx)
        : null;

    $gamesShowName = $pickSource === 'games';
    $alphaShowName = $pickSource === 'alpha';
    $h2hBase = amiga_player_opponents_href($playerId, 'h2h', 'country');
    ?>
<div
    class="k2-player-opponents-h2h"
    data-k2-carry-scroll
    data-realm="amiga"
    data-h2h-grain="country"
    data-player-id="<?php echo $playerId; ?>"
    data-h2h-base="<?php echo k2_h($h2hBase); ?>"
    <?php if ($pair !== null) { ?>
    data-chart-country="<?php echo k2_h((string) $pair['country_token']); ?>"
    <?php } ?>
>
    <div class="k2-player-opponents-h2h__pickers k2-player-opponents-h2h__pickers--country">
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-country-games-<?php echo $playerId; ?>-trigger">By games played</label>
            <?php k2_h2h_country_listbox_render(
                'k2-h2h-country-games-' . $playerId,
                (string) $selectedCountryToken,
                $played,
                'Choose country by games played',
                'Choose country…',
                'No countries yet',
                $gamesShowName
            ); ?>
        </div>
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-country-alpha-<?php echo $playerId; ?>-trigger">A–Z</label>
            <?php k2_h2h_country_listbox_render(
                'k2-h2h-country-alpha-' . $playerId,
                (string) $selectedCountryToken,
                $byAlpha,
                'Choose country A to Z',
                'Choose country…',
                'No countries yet',
                $alphaShowName
            ); ?>
        </div>
    </div>

    <div class="k2-player-opponents-h2h__stage">
        <?php if ($pair === null || $bucket === null) { ?>
        <p class="k2-player-opponents-h2h__prompt k2-hub-page-intro">Choose a country above to compare head-to-head.</p>
        <?php } else {
            $subjectCard = amiga_player_opponents_h2h_load_player_card($con, $playerId, $ctx);
            if ($subjectCard !== null) {
                $countryToken = (string) $pair['country_token'];
                $games = (int) $pair['games'];
                $record = $games > 0 ? amiga_player_opponents_h2h_country_record($bucket) : null;
                player_opponents_render_h2h_country_poster($con, $subjectCard, $countryToken, $record, $games, $ctx);
                if ($games > 0) {
                    player_opponents_render_h2h_country_pair_detail($subjectCard, $countryToken, $bucket);
                    player_opponents_render_h2h_country_all_games_link($playerId, $countryToken, $games);
                    $countryLabel = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;
                    $momentGames = amiga_player_h2h_country_games_rows($con, $playerId, $countryToken, $ctx);
                    $momentSlots = player_opponents_h2h_moments_slots(
                        $momentGames,
                        (string) ($subjectCard['name'] ?? ''),
                        amiga_player_h2h_country_opponent_label($countryToken),
                        $countryLabel
                    );
                    player_opponents_render_h2h_moments_grid($momentSlots);
                }
            } else { ?>
        <p class="k2-player-opponents-h2h__empty">Could not load player data for this comparison.</p>
        <?php }
        } ?>
    </div>
    <?php if ($pair !== null) {
        player_opponents_render_h2h_country_matchup_charts(
            $playerId,
            (string) $pair['country_token'],
            $playerName,
            'amiga'
        );
    } ?>
</div>
    <?php
}
