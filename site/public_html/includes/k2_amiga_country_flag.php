<?php
/**
 * Amiga player country → bundled flag SVG (flag-icons 4x3, MIT).
 * Keys match amiga_players.country display strings from Access import.
 */
require_once __DIR__ . '/k2_safety.php';

/**
 * @return array{code: string, label: string}|null
 */
function k2_amiga_country_flag_meta(string $country): ?array
{
    static $map = [
        'Germany' => ['code' => 'de', 'label' => 'Germany'],
        'England' => ['code' => 'gb-eng', 'label' => 'England'],
        'Italy' => ['code' => 'it', 'label' => 'Italy'],
        'Norway' => ['code' => 'no', 'label' => 'Norway'],
        'Greece' => ['code' => 'gr', 'label' => 'Greece'],
        'Netherlands' => ['code' => 'nl', 'label' => 'Netherlands'],
        'Sweden' => ['code' => 'se', 'label' => 'Sweden'],
        'Denmark' => ['code' => 'dk', 'label' => 'Denmark'],
        'Spain' => ['code' => 'es', 'label' => 'Spain'],
        'Austria' => ['code' => 'at', 'label' => 'Austria'],
        'Ireland' => ['code' => 'ie', 'label' => 'Ireland'],
        'France' => ['code' => 'fr', 'label' => 'France'],
        'Poland' => ['code' => 'pl', 'label' => 'Poland'],
        'Switzerland' => ['code' => 'ch', 'label' => 'Switzerland'],
        'Turkey' => ['code' => 'tr', 'label' => 'Turkey'],
        'Scotland' => ['code' => 'gb-sct', 'label' => 'Scotland'],
        'Belgium' => ['code' => 'be', 'label' => 'Belgium'],
        'Wales' => ['code' => 'gb-wls', 'label' => 'Wales'],
        'Portugal' => ['code' => 'pt', 'label' => 'Portugal'],
        'N. Ireland' => ['code' => 'gb-nir', 'label' => 'Northern Ireland'],
        'Hong Kong' => ['code' => 'hk', 'label' => 'Hong Kong'],
        'UAE' => ['code' => 'ae', 'label' => 'United Arab Emirates'],
    ];

    $country = trim($country);
    if ($country === '') {
        return null;
    }

    return $map[$country] ?? null;
}

function k2_amiga_country_flag_src(string $code): string
{
    return '/img/flags/amiga/' . rawurlencode($code) . '.svg';
}

/**
 * @param array{class?: string, decorative?: bool} $opts
 */
function k2_amiga_country_flag_img(string $country, array $opts = []): string
{
    $meta = k2_amiga_country_flag_meta($country);
    if ($meta === null) {
        return '';
    }

    $class = isset($opts['class']) ? trim((string) $opts['class']) : '';
    $decorative = !isset($opts['decorative']) || (bool) $opts['decorative'];
    $src = k2_amiga_country_flag_src($meta['code']);
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

    if ($decorative) {
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
            . ' width="28" height="21" decoding="async" loading="lazy" alt="" aria-hidden="true"' . $classAttr . '>';
    }

    return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
        . ' width="28" height="21" decoding="async" loading="lazy"'
        . ' alt="' . htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') . '"' . $classAttr . '>';
}

/** Flag image for table cells; falls back to escaped country name when unmapped. */
function k2_amiga_country_table_cell(string $country, bool $link = false): string
{
    $country = trim($country);
    $flag = k2_amiga_country_flag_img($country, ['decorative' => false]);
    if ($flag !== '') {
        $inner = $flag;
    } else {
        $inner = $country !== '' ? k2_h($country) : '';
    }

    if ($inner === '') {
        return '';
    }

    if ($link && $country !== '') {
        require_once __DIR__ . '/amiga_countries_lib.php';
        $meta = k2_amiga_country_flag_meta($country);
        $label = $meta !== null ? $meta['label'] : $country;
        $href = k2_amiga_country_roster_href($country);

        return '<a class="k2-country-roster-link" href="' . k2_h($href) . '" aria-label="Players from ' . k2_h($label) . '">' . $inner . '</a>';
    }

    return $inner;
}

/** Flag cell; em dash when country is empty (e.g. tournament host unknown). */
function k2_amiga_country_table_cell_or_dash(string $country): string
{
    $country = trim($country);
    if ($country === '') {
        return '—';
    }

    return k2_amiga_country_table_cell($country);
}

/**
 * Player country column body — centered flag + sort value for k2-table text sort.
 *
 * @return string opening <td ...> through cell contents (caller closes </td>)
 */
function k2_lb_td_country_open(int $colIndex, array $sort, string $country, bool $link = true): string
{
    require_once __DIR__ . '/k2_table_helpers.php';

    return '<td' . k2_lb_td($colIndex, $sort, 'k2-table-cell--center')
        . ' data-k2-sort-value="' . k2_h($country) . '">'
        . k2_amiga_country_table_cell($country, $link);
}
