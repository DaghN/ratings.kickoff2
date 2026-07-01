<?php
/**
 * World Cups hub chapter copy — lede + wing map list above sub-nav.
 *
 * @see docs/amiga-world-cups-hub-policy.md
 */
declare(strict_types=1);

/** First Amiga World Cup calendar year (World Cup I). */
const AMIGA_WORLD_CUP_FIRST_YEAR = 2001;

/** Calendar years with no World Cup (Covid). */
const AMIGA_WORLD_CUP_COVID_GAP_YEARS = [2020, 2021];

/**
 * After this month-day (UTC), a gap year's missing Oct/Nov WC counts as realized for copy.
 * e.g. 2020-12-01 → the 2020 season is treated as a Covid cancellation.
 */
const AMIGA_WORLD_CUP_COVID_GAP_REALIZED_SUFFIX = '-12-01';

function amiga_world_cups_hub_chapter_list_html(): string
{
    return '<ul class="k2-hub-chapter__list">'
        . '<li><strong>Chronology</strong> — a brief overview and a link to every World Cup page.</li>'
        . '<li><strong>Player stats</strong> — a look at players and their World Cup exploits.</li>'
        . '<li><strong>Country stats</strong> — a look at nations.</li>'
        . '<li><strong>Tournament stats</strong> — a look at World Cups.</li>'
        . '</ul>';
}

/**
 * Reference instant for Covid gap copy — snapshot period end when `as=` active, else now (UTC).
 *
 * Year/month wings use calendar period end (Dec 31 / last day of month), not the resolved
 * tournament event_date — e.g. year:2020 has no WC so cutoff tournament is still Nov 2019.
 */
function amiga_world_cups_hub_chapter_as_of(?AmigaSnapshotContext $ctx = null): DateTimeImmutable
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $utc = new DateTimeZone('UTC');
    $ctx ??= amiga_snapshot_context_peek();
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $wing = $ctx->wing();
        $key = $ctx->key();

        if ($wing === 'year' && preg_match('/^\d{4}$/', $key) === 1) {
            return new DateTimeImmutable($key . '-12-31', $utc);
        }

        if ($wing === 'month' && preg_match('/^\d{4}-\d{2}$/', $key) === 1) {
            $monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $key . '-01', $utc);
            if ($monthStart instanceof DateTimeImmutable) {
                return $monthStart->modify('last day of this month');
            }
        }

        $cutoff = $ctx->cutoff();
        if (is_array($cutoff) && ($cutoff['event_date'] ?? '') !== '') {
            return new DateTimeImmutable((string) $cutoff['event_date'], $utc);
        }
    }

    return new DateTimeImmutable('now', $utc);
}

/**
 * How many Covid gap years are in the past relative to $asOf (0, 1, or 2).
 */
function amiga_world_cups_covid_missed_count(?DateTimeInterface $asOf = null): int
{
    $asOf = $asOf instanceof DateTimeInterface
        ? DateTimeImmutable::createFromInterface($asOf)
        : new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $asOf = $asOf->setTimezone(new DateTimeZone('UTC'));

    $missed = 0;
    foreach (AMIGA_WORLD_CUP_COVID_GAP_YEARS as $year) {
        $realized = new DateTimeImmutable($year . AMIGA_WORLD_CUP_COVID_GAP_REALIZED_SUFFIX, new DateTimeZone('UTC'));
        if ($asOf >= $realized) {
            $missed++;
        }
    }

    return $missed;
}

function amiga_world_cups_hub_covid_exception_clause(?DateTimeInterface $asOf = null): string
{
    return amiga_world_cups_covid_missed_count($asOf) >= 1
        ? ' (except for Covid)'
        : '';
}

function amiga_world_cups_hub_chapter_lede_html(
    int $wcCount,
    int $playerCount,
    int $countryCount,
    ?DateTimeInterface $asOf = null
): string {
    $wcHtml = '<span class="blue">' . number_format($wcCount) . '</span>';
    $playersHtml = '<span class="blue">' . number_format($playerCount) . '</span>';
    $countriesHtml = '<span class="blue">' . number_format($countryCount) . '</span>';
    $wcLabel = $wcCount === 1 ? 'World Cup' : 'World Cups';
    $playerLabel = $playerCount === 1 ? 'player' : 'players';
    $countryLabel = $countryCount === 1 ? 'country' : 'countries';

    return 'Christmas comes early in Kick Off 2 with a World Cup in November or October every year since '
        . (string) AMIGA_WORLD_CUP_FIRST_YEAR
        . amiga_world_cups_hub_covid_exception_clause($asOf)
        . ', for a total of '
        . $wcHtml
        . ' ' . $wcLabel . ' so far with '
        . $playersHtml
        . ' ' . $playerLabel . ' from '
        . $countriesHtml
        . ' ' . $countryLabel . '.';
}
