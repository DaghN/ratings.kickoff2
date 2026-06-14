<?php
/**
 * Player wing context link — « Leaderboards above hero (online + Amiga).
 */
declare(strict_types=1);

function k2_player_wing_render_leaderboards_up_link(string $href): void
{
    if ($href === '') {
        return;
    }
    ?>
<p class="k2-page-nav__up k2-status-panel__meta">
	<a class="k2-link-star k2-status-panel__more" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">&larr; Leaderboards</a>
</p>
    <?php
}

function k2_player_wing_leaderboards_href_online(): string
{
    require_once __DIR__ . '/k2_routes.php';

    return k2_route('lb-rating');
}

function k2_player_wing_leaderboards_href_amiga(): string
{
    return '/amiga/leaderboards/rating.php';
}
