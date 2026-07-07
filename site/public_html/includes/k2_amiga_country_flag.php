<?php
/**
 * Amiga country flags → country roster links.
 *
 * Flag img → roster: k2_amiga_country_flag_link() (k2-country-roster-link on img).
 * Inline table cells: k2_amiga_lb_*_cell() compositors — see docs/k2-table-entity-links-policy.md
 *
 * @see docs/k2-table-entity-links-policy.md
 * @see docs/amiga-countries-hub-policy.md CH9
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_registry.php';

/**
 * @return array{code: string, label: string}|null
 */
function k2_amiga_country_flag_meta(string $country): ?array
{
    $country = trim($country);
    if ($country === '') {
        return null;
    }

    $row = k2_amiga_country_resolve($country);
    if ($row === null) {
        return null;
    }

    $code = trim((string) ($row['flag_code'] ?? ''));
    if ($code === '') {
        return null;
    }

    $svgPath = dirname(__DIR__) . '/img/flags/amiga/' . $code . '.svg';
    if (!is_file($svgPath)) {
        return null;
    }

    return [
        'code' => $code,
        'label' => k2_amiga_country_display_name($country),
    ];
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

    $customClass = isset($opts['class']) ? trim((string) $opts['class']) : '';
    $decorative = (bool) ($opts['decorative'] ?? false);
    $src = k2_amiga_country_flag_src($meta['code']);
    $smallFlag = $customClass === '';
    $imgClass = $smallFlag ? 'k2-amiga-country-flag-img' : $customClass;
    $width = $smallFlag ? 20 : 28;
    $height = $smallFlag ? 15 : 21;
    $classAttr = ' class="' . htmlspecialchars($imgClass, ENT_QUOTES, 'UTF-8') . '"';

    if ($decorative) {
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
            . ' width="' . $width . '" height="' . $height . '" decoding="async" loading="lazy" alt="" aria-hidden="true"' . $classAttr . '>';
    }

    return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
        . ' width="' . $width . '" height="' . $height . '" decoding="async" loading="lazy"'
        . ' alt="' . htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') . '"' . $classAttr . '>';
}

/**
 * Linked country flag → roster (#k2-country-roster). Default img: k2-amiga-country-flag-img (20×15).
 *
 * @param array{class?: string, decorative?: bool} $opts passed to k2_amiga_country_flag_img();
 *        decorative defaults false (alt text); caption passes true + custom class.
 */
function k2_amiga_country_flag_link(string $country, array $opts = []): string
{
    $country = trim($country);
    if ($country === '') {
        return '';
    }
    $flag = k2_amiga_country_flag_img($country, $opts);
    if ($flag === '') {
        return '';
    }
    require_once __DIR__ . '/amiga_countries_lib.php';
    $meta = k2_amiga_country_flag_meta($country);
    $label = $meta !== null ? $meta['label'] : $country;
    $href = k2_amiga_country_roster_href($country);

    return '<a class="k2-country-roster-link" href="' . k2_h($href) . '" aria-label="Players from ' . k2_h($label) . '">' . $flag . '</a>';
}

/** 16×12 inline-prose flag img (chart peak copy, TT ribbon stepper). */
function k2_amiga_country_flag_img_inline_text(string $country): string
{
    $meta = k2_amiga_country_flag_meta($country);
    if ($meta === null) {
        return '';
    }
    $src = k2_amiga_country_flag_src($meta['code']);

    return '<img src="' . k2_h($src) . '" width="16" height="12"'
        . ' class="k2-amiga-country-flag-img k2-amiga-country-flag-img--text"'
        . ' decoding="async" loading="lazy" alt="" aria-hidden="true">';
}

/** 16×12 flag link for inline prose — roster destination. */
function k2_amiga_country_flag_link_inline_text(string $country): string
{
    $country = trim($country);
    if ($country === '') {
        return '';
    }
    $flag = k2_amiga_country_flag_img_inline_text($country);
    if ($flag === '') {
        return '';
    }
    require_once __DIR__ . '/amiga_countries_lib.php';
    $meta = k2_amiga_country_flag_meta($country);
    $label = $meta !== null ? $meta['label'] : $country;
    $href = k2_amiga_country_roster_href($country);

    return '<a class="k2-country-roster-link" href="' . k2_h($href) . '" aria-label="Players from ' . k2_h($label) . '">' . $flag . '</a>';
}

/** Inline prose — host flag (16×12) before arbitrary link HTML (`k2-amiga-inline-flag-text`). */
function k2_amiga_inline_flag_text_and_link(string $country, string $linkHtml): string
{
    $flag = k2_amiga_country_flag_link_inline_text($country);
    if ($flag === '') {
        return $linkHtml;
    }

    return '<span class="k2-amiga-inline-flag-text">' . $flag . $linkHtml . '</span>';
}

/** Country roster name link — entity name link (k2-link-star), not flag wrapper. */
function k2_amiga_country_roster_link(string $countryToken): string
{
    $countryToken = trim($countryToken);
    if ($countryToken === '') {
        return '';
    }
    require_once __DIR__ . '/amiga_countries_lib.php';
    $href = k2_amiga_country_roster_href($countryToken);

    return '<a class="k2-link-star" href="' . k2_h($href) . '">' . k2_h($countryToken) . '</a>';
}

/**
 * @deprecated Flag-only table cells retired — use k2_amiga_lb_*_cell() compositors.
 */
function k2_amiga_country_table_cell(string $country, bool $link = true): string
{
    if (!$link) {
        $country = trim($country);
        $flag = k2_amiga_country_flag_img($country);

        return $flag !== '' ? $flag : '—';
    }

    $linked = k2_amiga_country_flag_link($country);

    return $linked !== '' ? $linked : '—';
}

/** Inline table row — optional nationality flag before/after a link (omit flag when unmapped). */
function k2_amiga_inline_flag_and_link(string $country, string $linkHtml): string
{
    $flag = k2_amiga_country_flag_link($country);
    if ($flag === '') {
        return $linkHtml;
    }

    return '<span class="k2-amiga-wc-podium-player">' . $flag . $linkHtml . '</span>';
}

/** Leaderboard Player column — nationality flag + profile link (no separate Country column). */
function k2_amiga_lb_player_cell(int $playerId, string $name, string $country = ''): string
{
    require_once __DIR__ . '/amiga_player_load.php';

    return k2_amiga_inline_flag_and_link($country, k2_amiga_player_link($playerId, $name));
}

/** Leaderboard Event column — host-country flag + tournament link (no separate Country column). */
function k2_amiga_lb_tournament_cell(int $tournamentId, string $name, string $hostCountry = ''): string
{
    require_once __DIR__ . '/amiga_tournament_lib.php';

    return k2_amiga_inline_flag_and_link($hostCountry, amiga_tournament_link($tournamentId, $name));
}

/** Leaderboard Country column — flag + country name roster link (dual link). */
function k2_amiga_lb_country_cell(string $countryToken): string
{
    $nameLink = k2_amiga_country_roster_link($countryToken);
    if ($nameLink === '') {
        return '—';
    }

    return k2_amiga_inline_flag_and_link($countryToken, $nameLink);
}
