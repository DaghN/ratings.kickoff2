<?php
/**
 * Amiga World Cups country stats read path.
 *
 * @see docs/amiga-world-cups-country-slice-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_country_slice_snapshot_lib.php';
require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

/**
 * @return list<array<string, mixed>>
 */
function amiga_wc_country_rows_for_view(mysqli $con, AmigaSnapshotContext $ctx, string $view): array
{
    $allowed = ['honours', 'results', 'goals', 'dds', 'opponents'];
    if (!in_array($view, $allowed, true)) {
        $view = 'honours';
    }

    if ($ctx->isActive()) {
        return amiga_lb_wc_country_rows_at_cutoff($con, $ctx, $view);
    }

    return amiga_lb_wc_country_rows_present($con, $view);
}

function amiga_wc_country_count(mysqli $con, AmigaSnapshotContext $ctx): int
{
    return amiga_lb_wc_country_count($con, $ctx);
}

function amiga_wc_country_points_per_game(int $points, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return $points / $games;
}

function amiga_wc_country_goals_per_game(int $value, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return $value / $games;
}
