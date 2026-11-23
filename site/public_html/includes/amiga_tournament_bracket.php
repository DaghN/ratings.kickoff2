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
 * One knockout tie (design C2 of the card trials, Dagh 2026-11-23): the games as results, home player first,
 * with flags; goals grey, the winning goal of each game bold; each score links to its game, a play icon to its
 * video. The tie winner's name is bold, the loser's a little dimmed (flags stay bright). Extra time and
 * penalties under the game where they happened; after a small gap the aggregate, higher number first.
 *
 * @param array<string, mixed> $tie with 'card' from amiga_tournament_knockout_tie_card()
 */
function amiga_tournament_render_tie_card(array $tie, string $roundTitle, bool $isActive): void
{
    require_once __DIR__ . '/k2_rated_game_row.php';
    $card = $tie['card'];
    $legs = $card['legs'];
    $multi = count($legs) > 1;
    $byId = [];
    foreach ($card['rows'] as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $winner = !empty($tie['unresolved']) || $tie['winner_id'] === null ? null : (int) $tie['winner_id'];
    $loser = $winner === null ? null : (int) ($winner === (int) $tie['player_lo'] ? $tie['player_hi'] : $tie['player_lo']);
    $player = static function (int $pid, bool $flagFirst) use ($byId, $winner): string {
        $row = $byId[$pid] ?? ['name' => '#' . $pid, 'country' => ''];
        $flag = k2_amiga_country_flag_link((string) $row['country']);
        $state = $winner === null ? '' : ($pid === $winner ? ' is-winner' : ' is-loser');
        $link = '<span class="k2-amiga-tie__name' . $state . '">' . k2_amiga_player_link($pid, (string) $row['name']) . '</span>';

        return '<span class="k2-amiga-tie__player">' . ($flagFirst ? $flag . $link : $link . $flag) . '</span>';
    };
    $legLabel = static fn (int $i): string => $i === 0 ? '1st' : ($i === 1 ? '2nd' : ($i === 2 ? '3rd' : ($i + 1) . 'th'));
    $decider = static function (array $leg): string {
        $bits = [];
        if ($leg['after_et'] !== null) {
            $bits[] = 'a.e.t. ' . $leg['after_et'][0] . '–' . $leg['after_et'][1];
        }
        if ($leg['pens'] !== null) {
            $bits[] = 'pens ' . $leg['pens'][0] . '–' . $leg['pens'][1];
        }

        return $bits !== [] ? implode(', ', $bits) : ($leg['after_et'] === null && $leg['pens'] === null ? (string) $leg['extra'] : '');
    };
    // Aggregate: the winner's goals first.
    $agg = static fn (int $first, int $second): string => (int) ($byId[$first]['aggregate'] ?? 0) . '–' . (int) ($byId[$second]['aggregate'] ?? 0);
    $firstHome = $legs !== [] ? (int) $legs[0]['home'] : (int) $tie['player_lo'];
    $firstAway = $legs !== [] ? (int) $legs[0]['away'] : (int) $tie['player_hi'];
    ?>
            <div class="k2-amiga-tie<?php echo $isActive ? ' is-active' : ''; ?>"
                 id="<?php echo k2_h(amiga_tournament_tie_card_id((string) $tie['scope_key'])); ?>">
                <?php foreach ($legs as $i => $leg) {
                    $scoreline = '<span class="k2-status-score">' . k2_rated_game_scoreline_html((int) $leg['goals_home'], (int) $leg['goals_away']) . '</span>';
                    $help = $roundTitle . ($multi ? ', ' . $legLabel($i) . ' leg' : '');
                    ?>
                <div class="k2-amiga-tie__game">
                    <?php if ($multi) { ?><span class="k2-amiga-tie__leg"><?php echo $legLabel($i); ?></span><?php } ?>
                    <?php echo $player((int) $leg['home'], true); ?>
                    <?php if ($leg['url'] !== '') { ?><a class="k2-amiga-tie__score" href="<?php echo k2_h($leg['url']); ?>" aria-label="<?php echo k2_h($help); ?>"><?php echo $scoreline; ?></a><?php } else { ?><span class="k2-amiga-tie__score"><?php echo $scoreline; ?></span><?php } ?>
                    <?php echo $player((int) $leg['away'], false); ?>
                    <span class="k2-amiga-tie__video"><?php echo $leg['video']; ?></span>
                </div>
                    <?php if (($d = $decider($leg)) !== '') { ?>
                <div class="k2-amiga-tie__decider<?php echo $multi ? ' k2-amiga-tie__decider--indent' : ''; ?>"><?php echo k2_h($d); ?></div>
                    <?php } ?>
                <?php } ?>
                <?php if ($winner === null && !empty($tie['unresolved'])) { ?>
                <div class="k2-amiga-tie__decider">Tie unresolved</div>
                <?php } ?>
                <?php if ($multi) { // the winner's (higher) number first; a level aggregate in the order the games read ?>
                <div class="k2-amiga-tie__foot">Aggregate <?php echo $winner !== null ? $agg($winner, (int) $loser) : $agg($firstHome, $firstAway); ?></div>
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
