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
            $isActive = $activeScopeKey !== '' && $tie['scope_key'] === $activeScopeKey;
            $winnerId = $tie['winner_id'] !== null ? (int) $tie['winner_id'] : null;
            $aId = (int) $tie['player_a_id'];
            $bId = (int) $tie['player_b_id'];
            $unresolved = !empty($tie['unresolved']);
            ?>
            <div class="k2-amiga-bracket__tie<?php echo $isActive ? ' is-active' : ''; ?>">
                <div class="k2-amiga-bracket__slot<?php echo $winnerId === $aId ? ' is-winner' : ($winnerId === $bId && !$unresolved ? ' is-loser' : ''); ?>">
                    <?php echo k2_amiga_player_link($aId, (string) $tie['player_a_name']); ?>
                </div>
                <a class="k2-amiga-bracket__score<?php echo $unresolved ? ' is-unresolved' : ''; ?>"
                   href="<?php echo k2_h($tie['url']); ?>"
                   title="<?php echo k2_h($round['phase']); ?> — leg detail"><?php
                    echo k2_h((string) $tie['score']);
                ?></a>
                <div class="k2-amiga-bracket__slot<?php echo $winnerId === $bId ? ' is-winner' : ($winnerId === $aId && !$unresolved ? ' is-loser' : ''); ?>">
                    <?php echo k2_amiga_player_link($bId, (string) $tie['player_b_name']); ?>
                </div>
            </div>
        <?php } ?>
        </div>
    </section>
    <?php } ?>
</div>
    <?php
}

/**
 * @param array<string, list<array{phase: string, rank: int, ties: list<array<string, mixed>>}>> $bracket
 */
function amiga_tournament_render_bracket(array $bracket, string $activeScopeKey = ''): void
{
    if ($bracket['main'] === [] && $bracket['placement_final'] === [] && $bracket['placement_bracket'] === []) {
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

    <?php if ($bracket['placement_final'] !== []) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection">Placement finals</h3>
        <?php amiga_tournament_render_bracket_rounds($bracket['placement_final'], $activeScopeKey); ?>
    </div>
    <?php } ?>

    <?php if ($bracket['placement_bracket'] !== []) { ?>
    <div class="k2-amiga-bracket__section k2-amiga-bracket__section--placement">
        <h3 class="k2-amiga-bracket__subsection">Placement brackets</h3>
        <?php amiga_tournament_render_bracket_rounds($bracket['placement_bracket'], $activeScopeKey); ?>
    </div>
    <?php } ?>

    <p class="k2-amiga-bracket__hint">Click a tie for leg-by-leg scores. Winner by aggregate goals; penalties shown on leg detail when aggregate is tied.</p>
</section>
    <?php
}
