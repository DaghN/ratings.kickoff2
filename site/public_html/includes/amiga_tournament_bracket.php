<?php
/**
 * Knockout bracket markup for cup tournaments (server-rendered).
 *
 * @param array{
 *   main: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>,
 *   placement_final: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>,
 *   placement_bracket: list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>
 * } $bracket from amiga_tournament_knockout_bracket_data()
 * @param string $activeScopeKey highlight current tie when viewing knockout detail
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

/**
 * One knockout tie: a row per player (flag, name, goals in each leg, aggregate), video glyphs under
 * the legs that have footage, and a line for extra time / penalties. Each leg's goals open that game.
 *
 * @param array<string, mixed> $tie with 'card' from amiga_tournament_knockout_tie_card()
 */
function amiga_tournament_render_tie_card(array $tie, string $roundTitle, bool $isActive): void
{
    $card = $tie['card'];
    $legs = $card['legs'];
    $legCount = max(1, count($legs));
    $multi = $legCount > 1;
    $hasVideo = array_filter(array_column($legs, 'video')) !== [];
    $ordinal = static fn (int $i): string => $i === 0 ? '1st' : ($i === 1 ? '2nd' : ($i === 2 ? '3rd' : ($i + 1) . 'th'));
    ?>
            <div class="k2-amiga-tie<?php echo $multi ? '' : ' k2-amiga-tie--single'; ?><?php echo $isActive ? ' is-active' : ''; ?>"
                 id="<?php echo k2_h(amiga_tournament_tie_card_id((string) $tie['scope_key'])); ?>"
                 style="--k2-tie-legs: <?php echo $legCount; ?>">
                <?php if ($multi) { ?>
                <div class="k2-amiga-tie__row k2-amiga-tie__row--head" aria-hidden="true">
                    <span></span>
                    <?php for ($i = 0; $i < $legCount; $i++) { ?><span><?php echo $ordinal($i); ?></span><?php } ?>
                    <span>Agg</span>
                </div>
                <?php } ?>
                <?php foreach ($card['rows'] as $row) { ?>
                <div class="k2-amiga-tie__row <?php echo k2_h((string) $row['state']); ?>">
                    <span class="k2-amiga-tie__player"><?php
                        echo k2_amiga_inline_flag_and_link((string) $row['country'], k2_amiga_player_link((int) $row['id'], (string) $row['name']));
                    ?></span>
                    <?php foreach ($row['goals'] as $i => $goals) {
                        $leg = $legs[$i] ?? null;
                        $help = $roundTitle . ($multi ? ', ' . $ordinal($i) . ' leg' : '') . ': open the game';
                        if ($leg !== null && $leg['url'] !== '') { ?>
                    <a class="k2-amiga-tie__leg" href="<?php echo k2_h($leg['url']); ?>" data-k2-help="<?php echo k2_h($help); ?>" data-k2-tooltip-hide-title="1"><?php echo (int) $goals; ?></a>
                        <?php } else { ?>
                    <span class="k2-amiga-tie__leg"><?php echo (int) $goals; ?></span>
                        <?php }
                    } ?>
                    <?php if ($multi) { ?><span class="k2-amiga-tie__agg"><?php echo (int) $row['aggregate']; ?></span><?php } ?>
                </div>
                <?php } ?>
                <?php if ($hasVideo) { ?>
                <div class="k2-amiga-tie__row k2-amiga-tie__row--video">
                    <span></span>
                    <?php foreach ($legs as $leg) { ?><span><?php echo $leg['video']; ?></span><?php } ?>
                    <?php if ($multi) { ?><span></span><?php } ?>
                </div>
                <?php } ?>
                <?php if (!empty($tie['unresolved'])) { ?>
                <p class="k2-amiga-tie__note">Tie unresolved</p>
                <?php } elseif ($card['note'] !== '') { ?>
                <p class="k2-amiga-tie__note"><?php echo k2_h($card['note']); ?></p>
                <?php } ?>
            </div>
    <?php
}

/**
 * @param list<array{phase: string, rank: int, ties: list<array<string, mixed>>}> $rounds
 */
function amiga_tournament_render_bracket_rounds(array $rounds, string $activeScopeKey = ''): void
{
    if ($rounds === []) {
        return;
    }
    ?>
<div class="k2-amiga-bracket__rounds" role="list">
    <?php foreach ($rounds as $round) { ?>
    <section class="k2-amiga-bracket__round" role="listitem" aria-label="<?php echo k2_h($round['phase']); ?>">
        <h4 class="k2-amiga-bracket__round-title"><?php echo k2_h($round['phase']); ?></h4>
        <div class="k2-amiga-bracket__ties">
        <?php foreach ($round['ties'] as $tie) {
            amiga_tournament_render_tie_card($tie, (string) $round['phase'], $activeScopeKey !== '' && $tie['scope_key'] === $activeScopeKey);
        } ?>
        </div>
    </section>
    <?php } ?>
</div>
    <?php
}

/**
 * @param array<string, mixed> $bracket from amiga_tournament_knockout_bracket_data()
 */
function amiga_tournament_render_bracket(array $bracket, string $activeScopeKey = ''): void
{
    $sideCups = $bracket['tracks'] ?? [];
    $otherGames = $bracket['other'] ?? [];
    if ($bracket['main'] === [] && $bracket['placement_final'] === [] && $bracket['placement_bracket'] === []
        && $sideCups === [] && $otherGames === []) {
        return;
    }
    ?>
<section id="bracket" class="k2-amiga-bracket" aria-labelledby="k2-amiga-bracket-heading">
    <h2 id="k2-amiga-bracket-heading" class="k2-panel-heading">Knockout bracket</h2>

    <?php if ($bracket['main'] !== []) { ?>
    <div class="k2-amiga-bracket__section">
        <?php amiga_tournament_render_bracket_rounds($bracket['main'], $activeScopeKey); ?>
    </div>
    <?php } ?>

    <?php // Placement brackets lead into the placement finals, so the finals come last. ?>
    <?php if ($bracket['placement_bracket'] !== []) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection">Placement brackets</h3>
        <?php amiga_tournament_render_bracket_rounds($bracket['placement_bracket'], $activeScopeKey); ?>
    </div>
    <?php } ?>

    <?php if ($bracket['placement_final'] !== []) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection">Placement finals</h3>
        <?php amiga_tournament_render_bracket_rounds($bracket['placement_final'], $activeScopeKey); ?>
    </div>
    <?php } ?>

    <?php // Side cups (KOA Cup, Bronze Cup, Playouts, …): their own knockout, after the main cup. ?>
    <?php foreach ($sideCups as $track => $rounds) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection"><?php echo k2_h(AMIGA_TOURNAMENT_SIDE_CUPS[$track] ?? ucfirst((string) $track)); ?></h3>
        <?php amiga_tournament_render_bracket_rounds($rounds, $activeScopeKey); ?>
    </div>
    <?php } ?>

    <?php if ($otherGames !== []) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection">Other games</h3>
        <?php amiga_tournament_render_bracket_rounds($otherGames, $activeScopeKey); ?>
    </div>
    <?php } ?>

    <p class="k2-amiga-bracket__hint">Goals in normal time; click a score to open that game, the play icon for its video. A tie is won on aggregate goals, then extra time and penalties.</p>
</section>
    <?php
}
