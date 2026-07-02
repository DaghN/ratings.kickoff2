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