<?php
/**
 * Tournament entity hero — feast grid matching country hero (flag left, name + stats right).
 *
 * Expects $k2TournamentHeroSummary (name, country, event_date, player_count, game_count)
 * and optional $k2TournamentHeroWinner (player_id, player_name, player_country).
 * Optional $k2TournamentHeroBadges — list of plain-text badge labels (live view).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_countries_lib.php';
require_once __DIR__ . '/amiga_profile_blocks.php';

$summary = is_array($k2TournamentHeroSummary ?? null) ? $k2TournamentHeroSummary : [];
$tournamentName = trim((string) ($summary['name'] ?? ''));
if ($tournamentName === '') {
    return;
}

$hostCountry = trim((string) ($summary['country'] ?? ''));
$eventDate = $summary['event_date'] ?? null;
$playerCount = (int) ($summary['player_count'] ?? 0);
$gameCount = (int) ($summary['game_count'] ?? 0);
$winner = is_array($k2TournamentHeroWinner ?? null) ? $k2TournamentHeroWinner : null;
$badges = is_array($k2TournamentHeroBadges ?? null) ? $k2TournamentHeroBadges : [];

$flagMeta = $hostCountry !== '' ? k2_amiga_country_flag_meta($hostCountry) : null;
$flagLabel = $flagMeta !== null ? $flagMeta['label'] : $hostCountry;
$rosterHref = $hostCountry !== '' ? k2_amiga_country_roster_href($hostCountry) : '';
$flagImg = $hostCountry !== '' ? k2_amiga_country_flag_img($hostCountry, [
    'class' => 'k2-country-hero__flag-img',
    'decorative' => false,
]) : '';

$dateDisplay = amiga_profile_format_event_date($eventDate);
$playersDisplay = $playerCount > 0 ? number_format($playerCount) : '—';
$gamesDisplay = $gameCount > 0 ? number_format($gameCount) : '—';

$winnerDisplay = '—';
if ($winner !== null) {
    $winnerId = (int) ($winner['player_id'] ?? 0);
    $winnerName = trim((string) ($winner['player_name'] ?? ''));
    $winnerCountry = trim((string) ($winner['player_country'] ?? ''));
    if ($winnerId > 0 && $winnerName !== '') {
        $winnerDisplay = k2_amiga_lb_player_cell($winnerId, $winnerName, $winnerCountry);
    }
}
?>
<article class="k2-amiga-tournament-hero k2-country-hero k2-country-hero--feast">
    <div class="k2-country-hero__inner">
        <div class="k2-country-hero__media"><?php
            if ($flagImg !== '') {
                ?><a class="k2-country-roster-link k2-country-hero__flag-link" href="<?php echo k2_h($rosterHref); ?>" aria-label="Players from <?php echo k2_h($flagLabel); ?>"><span class="k2-country-hero__flag"><?php echo $flagImg; ?></span></a><?php
            } elseif ($hostCountry !== '') {
                ?><span class="k2-country-hero__flag k2-country-hero__flag--text" aria-hidden="true"><?php echo k2_h($hostCountry); ?></span><?php
            } else {
                ?><span class="k2-country-hero__flag k2-country-hero__flag--text" aria-hidden="true">—</span><?php
            }
        ?></div>
        <div class="k2-country-hero__body">
            <h2 class="k2-country-hero__name"><?php echo k2_h($tournamentName); ?></h2>
            <div class="k2-player-hero__stats">
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">Date</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo $dateDisplay; ?></span>
                </div>
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">Players</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo k2_h($playersDisplay); ?></span>
                </div>
                <div class="k2-player-hero__stat">
                    <span class="k2-player-hero__stat-label">Games</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo k2_h($gamesDisplay); ?></span>
                </div>
                <div class="k2-player-hero__stat k2-amiga-tournament-hero__stat--winner">
                    <span class="k2-player-hero__stat-label">Winner</span>
                    <span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent k2-amiga-tournament-hero__stat-value--winner"><?php echo $winnerDisplay; ?></span>
                </div>
            </div>
            <?php if ($badges !== []) { ?>
            <div class="k2-amiga-tournament-hero__badges">
                <?php foreach ($badges as $badgeLabel) {
                    $badgeLabel = trim((string) $badgeLabel);
                    if ($badgeLabel === '') {
                        continue;
                    }
                    ?>
                <span class="k2-amiga-tournament-badge"><?php echo k2_h($badgeLabel); ?></span>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    </div>
</article>