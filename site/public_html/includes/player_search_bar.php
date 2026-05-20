<?php

/**
 * Player search widget (realm-ready). Default realm: online.
 *
 * Optional before include:
 *   $playerSearchRealm — string, e.g. 'online' | 'offline'
 *   $playerProfilePage — profile PHP basename, default individual1.php
 *   $playerSearchAsNavItem — if true, wraps markup in <li> for #aboutmenu (legacy)
 *   $playerSearchInHeader — if true, mock-style header search in site chrome
 */

if (!isset($playerSearchRealm)) {
    $playerSearchRealm = 'online';
}

if (!isset($playerProfilePage)) {
    $playerProfilePage = 'individual1.php';
}

if (!isset($playerSearchAsNavItem)) {
    $playerSearchAsNavItem = false;
}

if (!isset($playerSearchInHeader)) {
    $playerSearchInHeader = false;
}

$realmEsc = htmlspecialchars((string) $playerSearchRealm, ENT_QUOTES, 'UTF-8');
$profileEsc = htmlspecialchars((string) $playerProfilePage, ENT_QUOTES, 'UTF-8');
$navClass = $playerSearchAsNavItem ? ' player-search--nav' : '';
$headerClass = $playerSearchInHeader ? ' k2-header-search player-search--header' : '';

if ($playerSearchAsNavItem) {
    echo '<li class="player-search-nav-item">';
}

?>

<div class="player-search<?php echo $navClass . $headerClass; ?>" data-player-search-realm="<?php echo $realmEsc; ?>" data-player-profile-page="<?php echo $profileEsc; ?>" role="search">

    <label class="player-search-label<?php echo ($playerSearchAsNavItem || $playerSearchInHeader) ? ' visually-hidden' : ''; ?>" for="player-search-q">Find player<?php if (!$playerSearchAsNavItem && !$playerSearchInHeader): ?> <span class="player-search-realm-tag">(<?php echo $realmEsc; ?>)</span><?php endif; ?></label>

    <input id="player-search-q" class="player-search-input<?php echo $playerSearchInHeader ? ' k2-header-search__input' : ''; ?>" type="search" name="player_search_q" maxlength="32" autocomplete="off" spellcheck="false"
        aria-autocomplete="list" aria-expanded="false" aria-controls="player-search-listbox"<?php echo ($playerSearchAsNavItem || $playerSearchInHeader) ? ' placeholder="Find player"' : ''; ?> />

    <ul id="player-search-listbox" class="player-search-results" role="listbox" hidden="hidden"></ul>

    <span class="player-search-live visually-hidden" aria-live="polite"></span>

</div>

<?php

if ($playerSearchAsNavItem) {
    echo '</li>';
}
