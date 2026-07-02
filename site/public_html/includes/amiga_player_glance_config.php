<?php
/**
 * Amiga player name hover glance — tier toggle + asset enqueue.
 *
 * Toggle: set K2_AMIGA_PLAYER_GLANCE_TIER to 'A' (compact) or 'B' (full stat strip).
 */
declare(strict_types=1);

/** @var 'A'|'B' */
const K2_AMIGA_PLAYER_GLANCE_TIER = 'B';

function amiga_player_glance_tier(): string
{
    if (isset($_GET['k2_glance'])) {
        $q = strtoupper(trim((string) $_GET['k2_glance']));
        if ($q === 'A' || $q === 'B') {
            return $q;
        }
    }

    $tier = K2_AMIGA_PLAYER_GLANCE_TIER;
    return $tier === 'B' ? 'B' : 'A';
}

function amiga_player_glance_assets_head(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $root = $_SERVER['DOCUMENT_ROOT'];
    $cssPath = $root . '/stylesheets/amiga-player-glance.css';
    $jsPath = $root . '/js/amiga-player-glance.js';
    if (!is_file($cssPath) || !is_file($jsPath)) {
        return;
    }

    $tier = amiga_player_glance_tier();
    echo '<link href="/stylesheets/amiga-player-glance.css?v=' . (int) filemtime($cssPath) . '" rel="stylesheet" type="text/css" />' . "\n";
    echo '<script type="text/javascript">window.K2AmigaPlayerGlance={tier:' . json_encode($tier, JSON_UNESCAPED_UNICODE) . '};</script>' . "\n";
    echo '<script type="text/javascript" src="/js/amiga-player-glance.js?v=' . (int) filemtime($jsPath) . '" defer="defer"></script>' . "\n";
}