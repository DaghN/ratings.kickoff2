<?php
/**
 * World Cups podium column label — gradient metal text (Chronology + country hero).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/**
 * @return array{variant: string, rank: string, label: string, aria: string}|null
 */
function amiga_wc_podium_meta(int $place): ?array
{
    static $meta = [
        1 => ['variant' => 'gold', 'rank' => '1st', 'label' => 'Gold', 'aria' => 'Gold medalist'],
        2 => ['variant' => 'silver', 'rank' => '2nd', 'label' => 'Silver', 'aria' => 'Silver medalist'],
        3 => ['variant' => 'bronze', 'rank' => '3rd', 'label' => 'Bronze', 'aria' => 'Bronze medalist'],
    ];

    return $meta[$place] ?? null;
}

function amiga_wc_podium_th_markup(int $place): string
{
    $m = amiga_wc_podium_meta($place);
    if ($m === null) {
        return '';
    }

    return '<span class="k2-amiga-wc-podium-th k2-amiga-wc-podium-th--' . k2_h($m['variant']) . '" role="img" aria-label="' . k2_h($m['aria']) . '">'
        . '<span class="k2-amiga-wc-podium-th__rank" aria-hidden="true">' . k2_h($m['rank']) . '</span>'
        . '<span class="k2-amiga-wc-podium-th__metal">' . k2_h($m['label']) . '</span>'
        . '</span>';
}

/** Gradient metal label only — no 1st/2nd/3rd rank line (country hero). */
function amiga_wc_podium_metal_label_markup(int $place): string
{
    $m = amiga_wc_podium_meta($place);
    if ($m === null) {
        return '';
    }

    return '<span class="k2-amiga-wc-podium-th k2-amiga-wc-podium-th--' . k2_h($m['variant']) . ' k2-amiga-wc-podium-th--metal-only" role="img" aria-label="' . k2_h($m['aria']) . '">'
        . '<span class="k2-amiga-wc-podium-th__metal">' . k2_h($m['label']) . '</span>'
        . '</span>';
}
