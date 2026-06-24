<?php
/**
 * Amiga time travel — ribbon above hub/player nav (Year · Month · Event + stepper + picker).
 *
 * Include via site_header.php (top of k2-page-nav on Amiga pages).
 * Present | Time travel mode lives in header (amiga_time_mode_nav.php).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/k2_archive_listbox.php';
require_once __DIR__ . '/k2_table_helpers.php';

/** @var bool */
$GLOBALS['_amiga_snapshot_chrome_rendered'] = false;

function amiga_snapshot_chrome_request_path(): string
{
    return amiga_snapshot_request_path();
}

function amiga_snapshot_chrome_should_skip(): bool
{
    global $k2AmigaSnapshotChromeSkip;
    if (!empty($k2AmigaSnapshotChromeSkip)) {
        return true;
    }

    $path = amiga_snapshot_chrome_request_path();

    return str_contains($path, '/amiga/ops/') || str_contains($path, 'run_import_ko2amiga.php');
}

/**
 * @param list<array<string, mixed>> $catalog
 * @param array<string, true>|null $accentKeys event keys to highlight (player participated)
 */
function amiga_snapshot_chrome_render_picker(
    string $path,
    string $wing,
    string $currentAs,
    array $catalog,
    ?array $accentKeys = null
): void {
    $choices = [];
    foreach ($catalog as $item) {
        $key = (string) $item['key'];
        $choice = [
            'value' => amiga_snapshot_format_as_param($wing, $key),
            'label' => $wing === 'event'
                ? (string) ($item['tournament_name'] ?? $item['label'])
                : (string) $item['label'],
        ];
        if ($wing === 'event') {
            $choice['meta'] = (string) ($item['event_date_picker_label'] ?? '');
        }
        if ($accentKeys !== null && isset($accentKeys[$key])) {
            $choice['accent'] = true;
        }
        $choices[] = $choice;
    }
    // Picker shows newest first; catalog stays chrono-asc for stepper prev/next.
    $choices = array_reverse($choices);
    ?>
<form class="k2-player-games-controls k2-amiga-history__picker" method="get" action="<?php echo k2_h($path); ?>" data-k2-carry-scroll>
    <?php
    foreach (amiga_snapshot_chrome_carry_query_params($path) as $carryName => $carryValue) {
        echo '<input type="hidden" name="' . k2_h($carryName) . '" value="' . k2_h((string) $carryValue) . '" />';
    }
    foreach (k2_table_sort_query_params() as $sortName => $sortValue) {
        echo '<input type="hidden" name="' . k2_h($sortName) . '" value="' . k2_h((string) $sortValue) . '" />';
    }
    k2_archive_listbox_render(
        'as',
        'k2-amiga-time-travel-at',
        $currentAs,
        $choices,
        'Choose snapshot',
        '',
        '',
        $wing === 'event',
    ); ?>
</form>
    <?php
}

function amiga_snapshot_chrome_carry_query_params(string $path): array
{
    $targetPathOnly = k2_table_path_only($path);
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($currentPath) || $currentPath === '' || $targetPathOnly !== $currentPath) {
        return [];
    }

    /** @var array<string, scalar> $carry */
    $carry = [];
    foreach ($_GET as $name => $value) {
        if (!is_string($name) || $name === '' || is_array($value)) {
            continue;
        }
        if (in_array($name, ['as', 'wing', 'at'], true)) {
            continue;
        }
        $carry[$name] = $value;
    }

    require_once __DIR__ . '/amiga_tournament_lib.php';
    if (amiga_tournament_page_request_path($path)) {
        $asRaw = isset($_GET['as']) ? trim((string) $_GET['as']) : '';
        $parsed = $asRaw !== '' ? amiga_snapshot_parse_as_param($asRaw) : null;
        if ($parsed !== null && $parsed['wing'] === 'event') {
            $carry['id'] = $parsed['key'];
        }
    }

    return $carry;
}

function amiga_snapshot_chrome_render_stepper(
    string $path,
    string $wing,
    ?string $prevAs,
    ?string $nextAs,
    ?array $entry,
    ?string $currentAs = null,
): void {
    $label = $entry !== null ? (string) ($entry['label'] ?? '—') : '—';
    $stepperClass = 'k2-amiga-history__stepper k2-player-games-day-steps';
    if ($wing === 'event') {
        $stepperClass .= ' k2-amiga-history__stepper--fixed-label';
    }
    echo '<nav class="' . $stepperClass . '" data-k2-carry-scroll aria-label="Time travel snapshot">';
    if ($prevAs !== null) {
        $href = amiga_snapshot_chrome_nav_href($path, $prevAs, $wing);
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="'
            . k2_h($href) . '" aria-label="Previous snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    $tournamentId = ($wing === 'event' && $entry !== null && $entry['cutoff_tournament_id'] !== null)
        ? (int) $entry['cutoff_tournament_id']
        : 0;
    if ($tournamentId > 0) {
        require_once __DIR__ . '/amiga_tournament_lib.php';
        $tournamentPath = amiga_tournament_url($tournamentId);
        if ($currentAs !== null && $currentAs !== '') {
            $tournamentHref = amiga_url_with_as_param($tournamentPath, $currentAs);
        } else {
            $tournamentHref = amiga_url_with_context($tournamentPath);
        }
        $tournamentHref .= '#' . AMIGA_TOURNAMENT_PAGE_FRAGMENT;
        echo '<a class="k2-amiga-history__label k2-amiga-history__label--link" href="' . k2_h($tournamentHref) . '">' . k2_h($label) . '</a>';
    } else {
        echo '<span class="k2-amiga-history__label">' . k2_h($label) . '</span>';
    }
    if ($nextAs !== null) {
        $href = amiga_snapshot_chrome_nav_href($path, $nextAs, $wing);
        echo '<a class="k2-player-games-day-step k2-player-games-day-step--next" href="'
            . k2_h($href) . '" aria-label="Next snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></a>';
    } else {
        echo '<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next snapshot">';
        echo '<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span></span>';
    }
    echo '</nav>';
}

/**
 * @param list<array{wing: string, label: string}> $wings
 */
function amiga_snapshot_chrome_render_wing_tabs(
    string $path,
    string $activeWing,
    array $wings,
    ?array $cutoff
): void {
    echo '<nav class="k2-realm-switch k2-amiga-time-travel__wings" data-k2-carry-scroll aria-label="Time travel granularity">';
    echo '<div class="k2-realm-switch__track" role="group">';
    foreach ($wings as $tab) {
        $wingId = $tab['wing'];
        $key = amiga_snapshot_wing_key_from_cutoff($cutoff, $wingId);
        if ($key === null) {
            continue;
        }
        $href = amiga_url_with_as($path, $wingId, $key);
        $activeClass = $activeWing === $wingId ? ' is-active' : '';
        $ariaCurrent = $activeWing === $wingId ? ' aria-current="page"' : '';
        echo '<a href="' . k2_h($href) . '" class="k2-realm-switch__btn' . $activeClass . '"' . $ariaCurrent . '>'
            . k2_h($tab['label']) . '</a>';
    }
    echo '</div></nav>';
}

function amiga_snapshot_chrome_event_layout_style(array $catalog): string
{
    $maxStepperChars = 20;
    $maxNameChars = 12;
    foreach ($catalog as $item) {
        $maxStepperChars = max($maxStepperChars, mb_strlen((string) ($item['label'] ?? '')));
        $maxNameChars = max($maxNameChars, mb_strlen((string) ($item['tournament_name'] ?? '')));
    }

    $stepperRem = min(28.0, max(16.0, $maxStepperChars * 0.5 + 3.0));
    // Picker: name column + fixed date column — do not size meta as if it were another full name.
    $nameRem = min(14.0, max(6.5, $maxNameChars * 0.4));
    $pickerRem = min(19.0, max(13.0, $nameRem + 4.5 + 0.25));

    return sprintf(
        '--k2-amiga-tt-stepper-width:%.1frem;--k2-amiga-tt-picker-width:%.1frem',
        $stepperRem,
        $pickerRem
    );
}

function amiga_snapshot_chrome_render_active(mysqli $con, AmigaSnapshotContext $ctx, string $path): void
{
    $wing = $ctx->wing();
    $currentAs = (string) $ctx->asParam();
    $catalog = $ctx->catalog();
    $cutoff = $ctx->cutoff();

    $prevAs = null;
    if ($ctx->prevKey() !== null && $ctx->prevKey() !== '') {
        $prevAs = amiga_snapshot_format_as_param($wing, $ctx->prevKey());
    }
    $nextAs = null;
    if ($ctx->nextKey() !== null && $ctx->nextKey() !== '') {
        $nextAs = amiga_snapshot_format_as_param($wing, $ctx->nextKey());
    }

    $pickerAccentKeys = null;
    if ($wing === 'event') {
        require_once __DIR__ . '/amiga_player_event_stepper_lib.php';
        if (amiga_player_event_stepper_applies($path)) {
            $pickerPlayerId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
            if ($pickerPlayerId < 1) {
                global $playerId;
                if (isset($playerId) && (int) $playerId > 0) {
                    $pickerPlayerId = (int) $playerId;
                }
            }
            if ($pickerPlayerId > 0) {
                $pickerAccentKeys = amiga_player_participated_event_key_set($con, $pickerPlayerId);
            }
        }
    }

    $wings = [
        ['wing' => 'year', 'label' => 'Year'],
        ['wing' => 'month', 'label' => 'Month'],
        ['wing' => 'event', 'label' => 'Event'],
    ];
    $sectionClass = 'k2-amiga-time-travel k2-amiga-time-travel--active';
    $sectionStyle = '';
    if ($wing === 'event') {
        $sectionClass .= ' k2-amiga-time-travel--event-wing';
        $sectionStyle = amiga_snapshot_chrome_event_layout_style($catalog);
    }

    require_once __DIR__ . '/amiga_time_travel_stamp.php';
    amiga_time_travel_stamp_render($ctx);
    ?>
<section class="<?php echo k2_h($sectionClass); ?>"<?php echo $sectionStyle !== '' ? ' style="' . k2_h($sectionStyle) . '"' : ''; ?> aria-label="Time travel controls" data-k2-preserve-table-sort="1">
    <div class="k2-amiga-time-travel__bar">
        <?php amiga_snapshot_chrome_render_wing_tabs($path, $wing, $wings, $cutoff); ?>
        <div class="k2-amiga-history__controls k2-amiga-time-travel__controls">
            <?php
            amiga_snapshot_chrome_render_stepper(
                $path,
                $wing,
                $prevAs,
                $nextAs,
                $ctx->entry(),
                $currentAs,
            );
            if ($catalog !== [] && $currentAs !== '') {
                amiga_snapshot_chrome_render_picker($path, $wing, $currentAs, $catalog, $pickerAccentKeys);
            }
            ?>
        </div>
    </div>
    <?php amiga_snapshot_chrome_render_unwired_note(); ?>
</section>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/individual3-filters.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/individual3-filters.js'); ?>" defer="defer"></script>
    <?php
}

function amiga_snapshot_chrome_render_unwired_note(): void
{
    global $k2AmigaPlayerTabActive;
    if (!isset($k2AmigaPlayerTabActive) || $k2AmigaPlayerTabActive === '') {
        return;
    }
    echo '<p class="k2-amiga-time-travel__unwired">This page still shows present-day data.</p>';
}

function amiga_snapshot_chrome_render(): void
{
    if (!empty($GLOBALS['_amiga_snapshot_chrome_rendered'])) {
        return;
    }
    if (amiga_snapshot_chrome_should_skip()) {
        return;
    }

    $path = amiga_snapshot_chrome_request_path();
    $configPath = __DIR__ . '/../../config/ko2amiga_config.php';
    if (!is_file($configPath)) {
        return;
    }

    include $configPath;
    if (!isset($dbhost, $username, $password, $database)) {
        return;
    }

    $port = isset($dbportnum) ? (int) $dbportnum : ini_get('mysqli.default_port');
    $con = @new mysqli($dbhost, $username, $password, $database, (int) $port);
    if ($con->connect_errno) {
        return;
    }
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    try {
        $ctx = amiga_snapshot_context_from_request($con);
        if ($ctx->isActive()) {
            amiga_snapshot_chrome_render_active($con, $ctx, $path);
        }
    } catch (Throwable) {
        // Public pages must not break when DB unavailable.
    }

    $con->close();
    $GLOBALS['_amiga_snapshot_chrome_rendered'] = true;
}

