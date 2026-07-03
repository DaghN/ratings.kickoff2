<?php
/**
 * Player hero glow fade — warm chain via sessionStorage.
 *
 * Fade in when landing on a profile hero from a non-hero page. Skip fade when the
 * previous page also had a profile hero. Leaving the hero chain clears warm on the
 * next page load (head clears after read; only hero markup re-sets warm).
 */
declare(strict_types=1);

function k2_player_hero_glow_session_head(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<script type="text/javascript">(function(){try{var k="k2PlayerHeroGlowWarm";if(sessionStorage.getItem(k)==="1"){document.documentElement.classList.add("k2-player-hero-glow-ready");}sessionStorage.removeItem(k);}catch(e){}})();</script>' . "\n";
}

function k2_player_hero_glow_session_mark(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<script type="text/javascript">try{sessionStorage.setItem("k2PlayerHeroGlowWarm","1");}catch(e){}</script>' . "\n";
}

/**
 * Atomic hero paint — emit immediately BEFORE the feast hero <article>.
 *
 * The hero is shrink-wrapped (width: fit-content), so a browser paint that lands
 * mid-parse inside the hero flashes a narrow glowing border (parser yields at
 * network-chunk/time-slice boundaries during page load and refresh — carry-scroll
 * only covers pill navigation). While html.k2-player-hero-parsing is set, the
 * feast hero is visibility:hidden (player-hero-rank.css); the close call right
 * after </article> removes it parser-synchronously, so the hero paints fully
 * laid out or not at all. DOMContentLoaded fallback keeps the hero from staying
 * hidden if the page truncates mid-hero.
 */
function k2_player_hero_atomic_paint_open(): void
{
    echo '<script type="text/javascript">(function(){var c=document.documentElement.classList;c.add("k2-player-hero-parsing");document.addEventListener("DOMContentLoaded",function(){c.remove("k2-player-hero-parsing");});})();</script>' . "\n";
}

/** Atomic hero paint — emit immediately AFTER the feast hero </article>. */
function k2_player_hero_atomic_paint_close(): void
{
    echo '<script type="text/javascript">document.documentElement.classList.remove("k2-player-hero-parsing");</script>' . "\n";
}