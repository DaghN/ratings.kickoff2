<?php

/**
 * Player search widget (realm-ready).
 *
 * Optional before include:
 *   $playerSearchRealm — 'online' | 'amiga' | 'all' (header defaults to all)
 *   $playerProfilePage — profile path for single-realm widgets, default player/profile.php
 *   $playerSearchInHeader — if true, header chrome search (cross-realm)
 */

if (!isset($playerSearchInHeader)) {
    $playerSearchInHeader = false;
}

if (!isset($playerSearchRealm)) {
    $playerSearchRealm = $playerSearchInHeader ? 'all' : 'online';
}

if (!isset($playerProfilePage)) {
    require_once __DIR__ . '/k2_routes.php';
    $playerProfilePage = k2_route('player-profile');
}

$realmEsc = htmlspecialchars((string) $playerSearchRealm, ENT_QUOTES, 'UTF-8');
$profileEsc = htmlspecialchars((string) $playerProfilePage, ENT_QUOTES, 'UTF-8');
$crossRealm = $playerSearchRealm === 'all';
$headerClass = $playerSearchInHeader ? ' k2-header-search player-search--header' : '';
$compactChrome = $playerSearchInHeader;

?>

<div class="player-search<?php echo $headerClass; ?>" data-player-search-realm="<?php echo $realmEsc; ?>" data-player-profile-page="<?php echo $profileEsc; ?>" data-player-search-cross-realm="<?php echo $crossRealm ? '1' : '0'; ?>" role="search">

    <label class="player-search-label<?php echo $compactChrome ? ' visually-hidden' : ''; ?>" for="player-search-q">Find player<?php if (!$compactChrome): ?> <span class="player-search-realm-tag">(<?php echo $realmEsc; ?>)</span><?php endif; ?></label>

    <input id="player-search-q" class="player-search-input<?php echo $playerSearchInHeader ? ' k2-header-search__input' : ''; ?>" type="search" name="player_search_q" maxlength="32" autocomplete="off" spellcheck="false"
        aria-autocomplete="list" aria-expanded="false" aria-controls="player-search-listbox"<?php echo $compactChrome ? ' placeholder="Find player"' : ''; ?> />

    <ul id="player-search-listbox" class="player-search-results" role="listbox" hidden="hidden"></ul>

    <span class="player-search-live visually-hidden" aria-live="polite"></span>

</div>
