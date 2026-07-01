<?php
/**
 * Player hero H2H-style glow experiment — one-line revert.
 *
 * Revert: set K2_PLAYER_HERO_GLOW_EXPERIMENT to false below.
 * Styles: stylesheets/player-hero-glow-experiment.css (loaded only when enabled).
 * Scope: .k2-player-hero--glow on online + Amiga player-wing heroes — not country/tournament heroes.
 */
declare(strict_types=1);

/** @var bool Flip to false to revert instantly (no CSS loaded, no modifier class). */
const K2_PLAYER_HERO_GLOW_EXPERIMENT = true;

function k2_player_hero_glow_experiment_enabled(): bool
{
    return K2_PLAYER_HERO_GLOW_EXPERIMENT;
}

function k2_player_hero_article_class(): string
{
    $classes = 'k2-player-hero k2-player-hero--feast';
    if (k2_player_hero_glow_experiment_enabled()) {
        $classes .= ' k2-player-hero--glow';
    }
    return $classes;
}
