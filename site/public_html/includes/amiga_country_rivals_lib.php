<?php
/**
 * Amiga country Rivals — nation-pair helpers (URLs, view parsing).
 *
 * @see docs/amiga-country-rivals-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_countries_lib.php';

const K2_AMIGA_COUNTRY_RIVALS_VIEWS = ['h2h', 'wdl', 'goals', 'dds'];

function amiga_country_rivals_parse_view(mixed $raw): string
{
    $view = is_string($raw) ? strtolower(trim($raw)) : 'h2h';

    return in_array($view, K2_AMIGA_COUNTRY_RIVALS_VIEWS, true) ? $view : 'h2h';
}

function amiga_country_rivals_route_key_for_view(string $view): string
{
    return match (amiga_country_rivals_parse_view($view)) {
        'wdl' => 'amiga-country-rivals-wdl',
        'goals' => 'amiga-country-rivals-goals',
        'dds' => 'amiga-country-rivals-dds',
        default => 'amiga-country-rivals-h2h',
    };
}

function k2_amiga_country_rivals_href(string $countryToken, string $view = 'h2h', ?string $rivalToken = null): string
{
    $params = ['country' => $countryToken];
    if ($rivalToken !== null && trim($rivalToken) !== '') {
        $params['rival'] = trim($rivalToken);
    }

    return k2_amiga_route(amiga_country_rivals_route_key_for_view($view), $params);
}

function amiga_country_rivals_games_filtered_href(string $heroCountry, string $rivalCountry): string
{
    return k2_amiga_route('amiga-games-all', [
        'country' => amiga_countries_normalize_country_param($heroCountry),
        'rival' => amiga_countries_normalize_country_param($rivalCountry),
    ]) . '#matching-games';
}

function amiga_country_rivals_games_cell_html(string $heroCountry, string $rivalCountry, int $games): string
{
    if ($games <= 0) {
        return '0';
    }

    $href = amiga_country_rivals_games_filtered_href($heroCountry, $rivalCountry);
    $label = htmlspecialchars((string) $games, ENT_QUOTES, 'UTF-8');

    return '<a class="k2-link-star" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
}