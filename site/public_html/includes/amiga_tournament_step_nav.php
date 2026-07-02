<?php
/**
 * Tournament entity page — prev/next chevrons + WC-only pill + with-player / host-country listboxes.
 *
 * @see docs/with-player-stepper-policy.md §3.1
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_tournament_step_catalog.php';
require_once __DIR__ . '/amiga_tournament_step_href.php';
require_once __DIR__ . '/amiga_id_with_url.php';
require_once __DIR__ . '/amiga_id_country_url.php';
require_once __DIR__ . '/amiga_id_wc_url.php';

/**
 * @return array<string, scalar>
 */
function amiga_tournament_step_carry_query_params(int $tournamentId): array
{
    /** @var array<string, scalar> $carry */
    $carry = ['id' => $tournamentId];
    foreach ($_GET as $name => $value) {
        if (!is_string($name) || $name === '' || is_array($value)) {
            continue;
        }
        if (in_array($name, ['id', 'id_with', 'id_country', 'id_wc'], true)) {
            continue;
        }
        $carry[$name] = $value;
    }

    return $carry;
}

/**
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 */
function amiga_tournament_step_nav_render(
    mysqli $con,
    int $tournamentId,
    array $intent,
): void {
    if ($tournamentId < 1) {
        return;
    }

    $filterBag = amiga_tournament_step_filter_bag_from_request($con);
    $catalog = amiga_tournament_step_catalog($con);
    $steps = amiga_tournament_step_keys($con, $catalog, $tournamentId, $filterBag);

    $prevHref = null;
    if ($steps['prev_key'] !== null && $steps['prev_key'] !== '') {
        $prevHref = amiga_tournament_step_target_href($con, (int) $steps['prev_key'], $intent);
    }
    $nextHref = null;
    if ($steps['next_key'] !== null && $steps['next_key'] !== '') {
        $nextHref = amiga_tournament_step_target_href($con, (int) $steps['next_key'], $intent);
    }

    $requestPath = amiga_snapshot_request_path();
    $playerId = amiga_id_with_from_request();
    $selectedPlayer = $playerId > 0 ? (string) $playerId : '';
    $playerChoices = amiga_tournament_step_player_choices($con);

    $countryFilter = amiga_id_country_active($con);
    $selectedCountry = $countryFilter !== '' ? $countryFilter : '';
    $countryChoices = amiga_tournament_step_country_choices($con);

    $wcOnly = amiga_id_wc_active($con);
    $wcToggleHref = amiga_id_wc_toggle_href($requestPath, $tournamentId, !$wcOnly);
    ?>
<div class="k2-amiga-tournament-step-nav" data-k2-carry-scroll>
    <nav class="k2-player-games-day-steps k2-amiga-tournament-step-nav__steps" aria-label="Tournament sequence">
<?php if ($prevHref !== null) { ?>
        <a class="k2-player-games-day-step k2-player-games-day-step--prev" href="<?php echo k2_h($prevHref); ?>" aria-label="Previous tournament">
            <span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
        </a>
<?php } else { ?>
        <span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous tournament">
            <span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
        </span>
<?php } ?>
<?php if ($nextHref !== null) { ?>
        <a class="k2-player-games-day-step k2-player-games-day-step--next" href="<?php echo k2_h($nextHref); ?>" aria-label="Next tournament">
            <span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
        </a>
<?php } else { ?>
        <span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next tournament">
            <span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
        </span>
<?php } ?>
    </nav>
    <form class="k2-player-games-controls k2-amiga-tournament-step-nav__filters" method="get" action="<?php echo k2_h($requestPath); ?>" data-k2-carry-scroll>
<?php
    foreach (amiga_tournament_step_carry_query_params($tournamentId) as $carryName => $carryValue) {
        echo '<input type="hidden" name="' . k2_h($carryName) . '" value="' . k2_h((string) $carryValue) . '" />';
    }
    foreach (k2_table_sort_query_params() as $sortName => $sortValue) {
        echo '<input type="hidden" name="' . k2_h($sortName) . '" value="' . k2_h((string) $sortValue) . '" />';
    }
    if ($wcOnly) {
        echo '<input type="hidden" name="id_wc" value="world-cup" />';
    }
?>
        <div class="k2-player-games-controls__fields k2-amiga-tournament-step-nav__filter-fields">
            <div class="k2-player-games-controls__field k2-amiga-tournament-step-nav__wc-field">
                <a href="<?php echo k2_h($wcToggleHref); ?>" class="k2-amiga-tournament-step-nav__wc-pill<?php echo $wcOnly ? ' is-active' : ''; ?>" aria-pressed="<?php echo $wcOnly ? 'true' : 'false'; ?>" data-k2-carry-scroll>WC only</a>
            </div>
            <div class="k2-player-games-controls__field">
<?php
    k2_archive_listbox_render(
        'id_with',
        'k2-amiga-tournament-id-with',
        $selectedPlayer,
        $playerChoices,
        'With player',
        'k2-amiga-tournament-step-nav__player-pick',
        'With player...',
        false,
        '',
    ); ?>
            </div>
            <div class="k2-player-games-controls__field">
<?php
    k2_archive_listbox_render(
        'id_country',
        'k2-amiga-tournament-id-country',
        $selectedCountry,
        $countryChoices,
        'Host country',
        'k2-amiga-tournament-step-nav__country-pick',
        'Host country...',
        false,
        '',
    ); ?>
            </div>
        </div>
    </form>
</div>
<?php
}