<?php
/**
 * Amiga country Rivals — H2H poster, pickers, pair detail, charts hook.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/amiga_country_rivals_load.php';
require_once __DIR__ . '/amiga_country_rivals_lib.php';
require_once __DIR__ . '/amiga_country_rivals_h2h_games_lib.php';
require_once __DIR__ . '/amiga_player_opponents_country_h2h.php';
require_once __DIR__ . '/amiga_player_opponents_h2h.php';
require_once __DIR__ . '/player_opponents_h2h.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';
require_once __DIR__ . '/player_opponents_h2h_charts.php';
require_once __DIR__ . '/performance_rating.php';

function amiga_country_rivals_nation_label(string $countryToken): string
{
    $label = $countryToken === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $countryToken;

    return $label . ' nationals';
}

/**
 * @return list<array{country_token: string, games: int}>
 */
function amiga_country_rivals_h2h_played_rivals(mysqli $con, string $heroCountry, ?AmigaSnapshotContext $ctx = null): array
{
    $out = [];
    foreach (amiga_country_rivals_rows($con, $heroCountry, $ctx) as $row) {
        $out[] = [
            'country_token' => (string) $row['rival_token'],
            'games' => (int) $row['games'],
        ];
    }

    return $out;
}

function amiga_country_rivals_top_rival_token(
    mysqli $con,
    string $heroCountry,
    ?AmigaSnapshotContext $ctx = null
): string {
    $played = amiga_country_rivals_h2h_played_rivals($con, $heroCountry, $ctx);

    return $played === [] ? '' : (string) $played[0]['country_token'];
}

function amiga_country_rivals_h2h_redirect_default_rival_if_needed(
    mysqli $con,
    string $heroCountry,
    ?AmigaSnapshotContext $ctx = null
): void {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    if ($heroCountry === '') {
        return;
    }

    $requestedRival = array_key_exists('rival', $_GET)
        ? amiga_country_rivals_normalize_token((string) ($_GET['rival'] ?? ''))
        : '';

    if ($requestedRival !== '' && !amiga_country_rivals_is_domestic_rival($heroCountry, $requestedRival)) {
        return;
    }

    $topRival = amiga_country_rivals_top_rival_token($con, $heroCountry, $ctx);
    if ($topRival === '') {
        return;
    }

    header('Location: ' . k2_amiga_route('amiga-country-rivals-h2h', [
        'country' => $heroCountry,
        'rival' => $topRival,
        'pick' => 'games',
    ]), true, 302);
    exit;
}

function amiga_country_rivals_render_h2h_poster(
    mysqli $con,
    string $heroCountry,
    string $rivalCountry,
    ?array $record,
    int $games,
    ?AmigaSnapshotContext $ctx = null
): void {
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $playerCounts = amiga_countries_player_counts_by_token($con, $ctx);
    $heroPlayerCount = $playerCounts[$heroCountry] ?? 0;
    $rivalPlayerCount = $playerCounts[$rivalCountry] ?? 0;
    $heroLabel = $heroCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $heroCountry;
    $rivalLabel = $rivalCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $rivalCountry;
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
    $meterLabel = sprintf(
        '%s nationals: %d won, %d drawn, %d lost in %d games vs %s nationals.',
        $heroLabel,
        $w,
        $d,
        $l,
        $games,
        $rivalLabel
    );
    ?>
<section class="k2-h2h2-poster" id="h2h-rivalry"<?php echo $hasGames ? '' : ' data-empty="1"'; ?>>
	<div class="k2-h2h2-marquee">
		<?php echo k2_h2h_poster_country_card_html($heroCountry, 'subject', $heroPlayerCount); ?>
		<div class="k2-h2h2-vs" aria-hidden="true">vs</div>
		<?php echo k2_h2h_poster_country_card_html($rivalCountry, 'opponent', $rivalPlayerCount); ?>
	</div>
	<?php if ($hasGames) { ?>
	<div class="k2-h2h2-record" role="group" aria-label="<?php echo k2_h(sprintf('%s vs %s record', $heroLabel, $rivalLabel)); ?>">
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

function amiga_country_rivals_render_h2h_pair_detail(string $heroCountry, string $rivalCountry, array $bucket): void
{
    $heroCard = [
        'player_id' => 0,
        'name' => amiga_country_rivals_nation_label($heroCountry),
        'display' => false,
        'rank' => null,
        'rating' => null,
        'profile_href' => k2_amiga_country_roster_href($heroCountry),
        'country' => $heroCountry,
    ];
    $rivalCard = [
        'player_id' => 0,
        'name' => amiga_country_rivals_nation_label($rivalCountry),
        'display' => false,
        'rank' => null,
        'rating' => null,
        'profile_href' => k2_amiga_country_roster_href($rivalCountry),
        'country' => $rivalCountry,
    ];
    $detail = player_opponents_h2h_pair_detail_map_row($bucket, true);
    $detail['perf_rating_subject'] = isset($bucket['performance_rating']) && $bucket['performance_rating'] !== null
        ? (int) round((float) $bucket['performance_rating'])
        : null;
    $detail['perf_rating_opponent'] = isset($bucket['performance_rating_vs_hero']) && $bucket['performance_rating_vs_hero'] !== null
        ? (int) round((float) $bucket['performance_rating_vs_hero'])
        : null;
    player_opponents_render_h2h_pair_detail($heroCard, $rivalCard, $detail);
}

function amiga_country_rivals_render_h2h_all_games_link(string $heroCountry, string $rivalCountry, int $games): void
{
    if ($games <= 0) {
        return;
    }
    $href = amiga_country_rivals_games_filtered_href($heroCountry, $rivalCountry);
    $label = sprintf(
        'All %s rated games — %s vs %s →',
        k2_fmt_int($games, '0'),
        $heroCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $heroCountry,
        $rivalCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $rivalCountry
    );
    ?>
<p class="k2-h2h2-all-games">
	<a class="k2-h2h2-all-games__link" href="<?php echo k2_h($href); ?>"><?php echo k2_h($label); ?></a>
</p>
    <?php
}

function amiga_country_rivals_render_h2h_panel(
    mysqli $con,
    string $heroCountry,
    string $selectedRival = '',
    bool $defaultToTopRival = false,
    ?string $pickSource = null,
    ?AmigaSnapshotContext $ctx = null
): void {
    $heroCountry = amiga_country_rivals_normalize_token($heroCountry);
    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $played = amiga_country_rivals_h2h_played_rivals($con, $heroCountry, $ctx);
    if ($defaultToTopRival && $selectedRival === '' && $played !== []) {
        $selectedRival = (string) $played[0]['country_token'];
    }
    $byAlpha = $played;
    usort(
        $byAlpha,
        static function (array $a, array $b): int {
            return strcasecmp((string) $a['country_token'], (string) $b['country_token']);
        }
    );
    $bucket = $selectedRival !== ''
        ? amiga_country_rivals_bucket($con, $heroCountry, $selectedRival, $ctx)
        : null;
    $games = $bucket !== null ? (int) ($bucket['games'] ?? 0) : 0;
    $h2hBase = k2_amiga_country_rivals_href($heroCountry, 'h2h');
    $gamesShowName = $pickSource === 'games';
    $alphaShowName = $pickSource === 'alpha';
    ?>
<div
    class="k2-player-opponents-h2h k2-country-rivals-h2h"
    data-k2-carry-scroll
    data-realm="amiga"
    data-h2h-grain="nation-pair"
    data-hero-country="<?php echo k2_h($heroCountry); ?>"
    data-h2h-base="<?php echo k2_h($h2hBase); ?>"
    <?php if ($selectedRival !== '') { ?>
    data-rival-country="<?php echo k2_h($selectedRival); ?>"
    <?php } ?>
>
    <div class="k2-player-opponents-h2h__pickers k2-player-opponents-h2h__pickers--country">
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-rival-games-trigger">By games played</label>
            <?php k2_h2h_country_listbox_render(
                'k2-h2h-rival-games',
                (string) $selectedRival,
                $played,
                'Choose rival by games played',
                'Choose rival…',
                'No rivals yet',
                $gamesShowName
            ); ?>
        </div>
        <div class="k2-player-opponents-h2h__listbox-wrap">
            <label class="k2-player-opponents-h2h__select-label" for="k2-h2h-rival-alpha-trigger">A–Z</label>
            <?php k2_h2h_country_listbox_render(
                'k2-h2h-rival-alpha',
                (string) $selectedRival,
                $byAlpha,
                'Choose rival A to Z',
                'Choose rival…',
                'No rivals yet',
                $alphaShowName
            ); ?>
        </div>
    </div>
    <div class="k2-player-opponents-h2h__stage">
        <?php if ($bucket === null || $games <= 0) { ?>
        <p class="k2-player-opponents-h2h__prompt k2-hub-page-intro">Choose a rival nation above to compare head-to-head.</p>
        <?php } else {
            $rivalCountry = (string) $bucket['rival_token'];
            $record = [
                'games' => $games,
                'wins' => (int) $bucket['wins'],
                'draws' => (int) $bucket['draws'],
                'losses' => (int) $bucket['losses'],
                'goals_for' => (int) $bucket['goals_for'],
                'goals_against' => (int) $bucket['goals_against'],
            ];
            amiga_country_rivals_render_h2h_poster($con, $heroCountry, $rivalCountry, $record, $games, $ctx);
            amiga_country_rivals_render_h2h_pair_detail($heroCountry, $rivalCountry, $bucket);
            amiga_country_rivals_render_h2h_all_games_link($heroCountry, $rivalCountry, $games);
            $momentGames = amiga_country_rivals_h2h_games_rows($con, $heroCountry, $rivalCountry, $ctx);
            $rivalLabel = $rivalCountry === AMIGA_COUNTRIES_UNKNOWN_TOKEN ? 'Unknown' : $rivalCountry;
            $momentSlots = player_opponents_h2h_moments_slots(
                $momentGames,
                amiga_country_rivals_nation_label($heroCountry),
                amiga_country_rivals_nation_label($rivalCountry),
                $rivalLabel
            );
            player_opponents_render_h2h_moments_grid($momentSlots);
        } ?>
    </div>
    <?php if ($bucket !== null && $games > 0) {
        player_opponents_render_h2h_nation_pair_matchup_charts($heroCountry, (string) $bucket['rival_token'], 'amiga');
    } ?>
</div>
    <?php
}