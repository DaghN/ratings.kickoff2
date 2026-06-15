<?php
/**
 * Profile top-opponents chart + Opponents → H2H pair charts (cumulative H2H, rating compare).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/player_opponents_lib.php';

function player_feast_render_top_opponents_chart(int $playerId): void
{
    $playerId = max(0, $playerId);
    if ($playerId < 1) {
        return;
    }

    $h2hBase = player_opponents_href($playerId, 'h2h');
    ?>
<div class="pm3d-top-opponents" data-h2h-base="<?php echo k2_h($h2hBase); ?>">
	<div
		class="player-top-opponents-chart k2-chart-panel"
		data-player-id="<?php echo $playerId; ?>"
	>
		<p class="k2-chart-block__hint">Click a bar to open head-to-head on the Opponents tab.</p>
		<p class="player-top-opponents-chart-status pm3d-chart__status k2-chart-panel__status">Loading top opponents…</p>
		<canvas class="player-top-opponents-canvas" aria-label="Most played opponents"></canvas>
	</div>
</div>
    <?php
}

function player_opponents_render_h2h_matchup_charts(
    int $playerId,
    bool $includeSearch = false,
    ?string $opponentName = null,
    ?int $opponentId = null,
    ?string $playerName = null
): void {
    $playerId = max(0, $playerId);
    if ($playerId < 1) {
        return;
    }

    $opponentId = $opponentId !== null ? max(0, $opponentId) : 0;
    $opponentName = $opponentName !== null ? trim($opponentName) : '';
    $playerName = $playerName !== null ? trim($playerName) : '';
    if ($playerName === '') {
        $playerName = '#' . $playerId;
    }
    $h2hHeading = 'Head-to-head';
    if ($opponentName !== '') {
        $h2hHeading .= ' vs ' . $opponentName;
    }
    $ratingHeading = 'Rating comparison';
    if ($opponentName !== '') {
        $ratingHeading .= ' vs ' . $opponentName;
    }
    $goalsHeading = 'Goals per game';
    if ($opponentName !== '') {
        $goalsHeading .= ' vs ' . $opponentName;
    }
    $cumulativeGoalsHeading = 'Cumulative goals';
    if ($opponentName !== '') {
        $cumulativeGoalsHeading .= ' vs ' . $opponentName;
    }
    $totalGoalsHeading = 'Combined goals per game';
    if ($opponentName !== '') {
        $totalGoalsHeading .= ' vs ' . $opponentName;
    }
    $scorelineHeading = 'Scoreline heatmap';
    if ($opponentName !== '') {
        $scorelineHeading .= ' vs ' . $opponentName;
    }

    $uid = 'k2-h2h-charts-' . $playerId;
    ?>
<section class="k2-h2h2-charts" aria-label="Matchup charts">
	<div class="pm3d-matchups">
		<h3 class="pm3d-matchups__subtitle player-head-to-head-chart-heading"><?php echo k2_h($h2hHeading); ?></h3>
		<div class="player-head-to-head-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
			<p class="player-head-to-head-meta pm3d-chart__meta"></p>
			<p class="player-head-to-head-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="Head-to-head cumulative wins"></canvas>
			</div>
		</div>
		<h3 class="pm3d-matchups__subtitle player-head-to-head-goals-chart-heading"><?php echo k2_h($cumulativeGoalsHeading); ?></h3>
		<div class="player-head-to-head-goals-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
			<p class="player-head-to-head-goals-meta pm3d-chart__meta"></p>
			<p class="player-head-to-head-goals-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="Head-to-head cumulative goals"></canvas>
			</div>
		</div>
		<h3 class="pm3d-matchups__subtitle player-compare-rating-chart-heading"><?php echo k2_h($ratingHeading); ?></h3>
		<div class="player-compare-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
			<div class="pm3d-chart-toolbar">
				<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating comparison chart view">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By games played</button>
				</div>
				<p class="player-compare-rating-toolbar-meta pm3d-chart__opponent"></p>
			</div>
			<p class="player-compare-rating-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent…</p>
			<div class="player-compare-rating-view player-compare-rating-view--date">
				<div class="k2-chart-frame">
					<canvas class="player-compare-rating-canvas--date" aria-label="Rating comparison by calendar date"></canvas>
				</div>
			</div>
			<div class="player-compare-rating-view player-compare-rating-view--game" hidden>
				<div class="k2-chart-frame">
					<canvas class="player-compare-rating-canvas--game" aria-label="Rating comparison by games played"></canvas>
				</div>
			</div>
		</div>
		<?php if ($opponentId > 0) { ?>
		<h3 class="pm3d-matchups__subtitle player-goals-scored-histogram-heading"><?php echo k2_h($goalsHeading); ?></h3>
		<div
			class="player-goals-scored-histogram k2-chart-panel"
			data-player-id="<?php echo $playerId; ?>"
			data-opponent-id="<?php echo $opponentId; ?>"
			data-h2h-side="subject"
		>
			<p class="k2-chart-block__hint">Goals <?php echo k2_h($playerName); ?> scored in rated games against <?php echo k2_h($opponentName); ?>. Click a bar to filter the games list.</p>
			<p class="player-goals-scored-histogram-status pm3d-chart__status k2-chart-panel__status">Loading goals per game…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="<?php echo k2_h($playerName); ?> goals scored per game against <?php echo k2_h($opponentName); ?>"></canvas>
			</div>
		</div>
		<div
			class="player-goals-scored-histogram player-goals-scored-histogram--rival k2-chart-panel"
			data-player-id="<?php echo $playerId; ?>"
			data-opponent-id="<?php echo $opponentId; ?>"
			data-h2h-side="rival"
		>
			<p class="k2-chart-block__hint">Goals <?php echo k2_h($opponentName); ?> scored in rated games against <?php echo k2_h($playerName); ?>. Click a bar to filter by goals conceded.</p>
			<p class="player-goals-scored-histogram-status pm3d-chart__status k2-chart-panel__status">Loading goals per game…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="<?php echo k2_h($opponentName); ?> goals scored per game against <?php echo k2_h($playerName); ?>"></canvas>
			</div>
		</div>
		<h4 class="pm3d-matchups__chart-label player-goals-scored-histogram-grouped-heading">Side by side</h4>
		<div
			class="player-goals-scored-histogram player-goals-scored-histogram--h2h-grouped k2-chart-panel"
			data-player-id="<?php echo $playerId; ?>"
			data-opponent-id="<?php echo $opponentId; ?>"
			data-h2h-grouped="1"
		>
			<p class="k2-chart-block__hint"><?php echo k2_h($playerName); ?> (chrome) beside <?php echo k2_h($opponentName); ?> (red) at each goal count. Click a bar to filter the games list.</p>
			<p class="player-goals-scored-histogram-status pm3d-chart__status k2-chart-panel__status">Loading goals per game…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="Goals scored per game comparison against opponent"></canvas>
			</div>
		</div>
		<h3 class="pm3d-matchups__subtitle player-h2h-total-goals-chart-heading"><?php echo k2_h($totalGoalsHeading); ?></h3>
		<div
			class="player-h2h-total-goals-chart k2-chart-panel"
			data-player-id="<?php echo $playerId; ?>"
			data-opponent-id="<?php echo $opponentId; ?>"
		>
			<p class="player-h2h-total-goals-meta pm3d-chart__meta"></p>
			<p class="player-h2h-total-goals-chart-status pm3d-chart__status k2-chart-panel__status">Loading combined goals per game…</p>
			<div class="k2-chart-frame">
				<canvas aria-label="Combined goals per game against opponent"></canvas>
			</div>
			<p class="player-h2h-total-goals-chart__x-label">Goal sum</p>
		</div>
		<h3 class="pm3d-matchups__subtitle player-h2h-scoreline-heatmap-heading"><?php echo k2_h($scorelineHeading); ?></h3>
		<div
			class="player-h2h-scoreline-heatmap"
			data-player-id="<?php echo $playerId; ?>"
			data-opponent-id="<?php echo $opponentId; ?>"
		>
			<p class="player-h2h-scoreline-heatmap-meta pm3d-chart__meta">Each square is how many times this scoreline happened.</p>
			<p class="player-h2h-scoreline-heatmap-status pm3d-chart__status k2-chart-panel__status">Loading scoreline heatmap…</p>
			<div class="h2h-scoreline-heatmap-wrap"></div>
		</div>
		<?php } ?>
		<?php if ($includeSearch) { ?>
		<div class="player-h2h-opponent-search player-search pm3d-h2h-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
			<label class="player-search-label" for="<?php echo k2_h($uid); ?>-h2h">Compare someone else</label>
			<p class="k2-chart-block__hint">Search is here for rare matchups outside the top-opponent graph.</p>
			<input id="<?php echo k2_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
			<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
		</div>
		<?php } ?>
	</div>
</section>
    <?php
}
