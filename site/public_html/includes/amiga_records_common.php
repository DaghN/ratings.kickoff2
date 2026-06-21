<?php
/**
 * Shared Hall of Fame render helpers for the Amiga realm.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/records_hof_table.php';

function amiga_records_has_value($value): bool
{
    return (float) $value != 0.0;
}

function amiga_records_value_or_dash($value): string
{
    return amiga_records_has_value($value) ? (string) $value : '-';
}

function amiga_records_fixed_or_dash($value, int $decimals): string
{
    return amiga_records_has_value($value) ? number_format((float) $value, $decimals) : '-';
}

function amiga_records_percent_or_dash($value): string
{
    return amiga_records_has_value($value) ? number_format(100 * (float) $value, 1) . '%' : '-';
}

function amiga_records_add_age_marker(string $text, $dateValue, int $newRecordCutoff, int $legendaryRecordCutoff): string
{
    $timestamp = strtotime((string) $dateValue);
    if ($timestamp === false) {
        return $text;
    }
    if ($timestamp >= $newRecordCutoff) {
        return $text . "<span class='blue'> (New!)</span>";
    }
    if ($timestamp < $legendaryRecordCutoff) {
        return $text . "<span class='holo'> (Legendary)</span>";
    }

    return $text;
}

function amiga_records_date_or_dash($dateValue, bool $showDate, int $newRecordCutoff, int $legendaryRecordCutoff): string
{
    if (!$showDate || $dateValue === null || $dateValue === '') {
        return '-';
    }
    $timestamp = strtotime((string) $dateValue);
    if ($timestamp === false) {
        return '-';
    }
    $text = date('M j, Y', $timestamp);

    return amiga_records_add_age_marker($text, $dateValue, $newRecordCutoff, $legendaryRecordCutoff);
}

function amiga_records_holder_html(string $html): string
{
    return '<span class="k2-table-cell--pad-x-md">' . $html . '</span>';
}

function amiga_records_profile_link(int $playerId, string $name): string
{
    if ($playerId < 1 || $name === '') {
        return '-';
    }

    return k2_amiga_player_link($playerId, $name);
}

function amiga_records_render_row(
    string $label,
    string $valueHtml,
    string $holderHtml,
    string $dateHtml,
    ?string $valueLbHref = null,
): void {
    if ($valueLbHref !== null && $valueLbHref !== '' && $valueHtml !== '-') {
        $valueCell = '<a class="k2-link-star" href="'
            . htmlspecialchars($valueLbHref, ENT_QUOTES, 'UTF-8') . '">' . $valueHtml . '</a>';
    } else {
        $valueCell = $valueHtml;
    }

    echo "    <tr>\n";
    echo '        <td>' . $label . "</td>\n";
    echo '        <td class="k2-table-cell--right">' . $valueCell . "</td>\n";
    echo '        <td>' . $holderHtml . "</td>\n";
    echo '        <td class="k2-table-cell--right">' . $dateHtml . "</td>\n";
    echo "    </tr>\n";

    records_hof_sync_track($valueCell, $holderHtml, $dateHtml);
}

function amiga_records_peak_year_or_dash($dateValue, bool $showDate, int $newRecordCutoff, int $legendaryRecordCutoff): string
{
    if (!$showDate || $dateValue === null || $dateValue === '') {
        return '-';
    }
    $text = (string) $dateValue;
    if (preg_match('/^(\d{4})-12-31$/', $text, $m)) {
        $text = $m[1];
    } else {
        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return '-';
        }
        $text = date('Y', $timestamp);
    }

    return amiga_records_add_age_marker($text, $dateValue, $newRecordCutoff, $legendaryRecordCutoff);
}

function amiga_records_render_spacer_row(): void
{
    echo "    <tr class=\"k2-table-row--spacer\">\n";
    echo "        <td></td><td></td><td></td><td></td>\n";
    echo "    </tr>\n";
}
