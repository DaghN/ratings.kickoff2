<?php
/**
 * Amiga time travel - temporal stamp in snapshot chrome (v1 static).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/** DSEG7 LED field separator between date parts — try `:` then `.` (policy §5 LED date). */
const AMIGA_TT_STAMP_LED_FIELD_SEP = '.';

/**
 * @return array{text: string, sep: true}
 */
function amiga_time_travel_stamp_led_sep_part(): array
{
    return ['text' => AMIGA_TT_STAMP_LED_FIELD_SEP, 'sep' => true];
}

function amiga_time_travel_stamp_context(): ?AmigaSnapshotContext
{
    $existing = $GLOBALS['_amiga_snapshot_context'] ?? null;
    if ($existing instanceof AmigaSnapshotContext && $existing->isActive()) {
        return $existing;
    }

    if (!amiga_snapshot_time_travel_active_from_request()) {
        return null;
    }

    if ($existing instanceof AmigaSnapshotContext) {
        return null;
    }

    require_once __DIR__ . '/amiga_db.php';
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';

    $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
    $ctx = amiga_snapshot_context_from_request($con);
    mysqli_close($con);

    return $ctx->isActive() ? $ctx : null;
}

/**
 * @return list<array{text: string, sep: bool}>
 */
function amiga_time_travel_stamp_led_parts(string $eventDateYmd, string $wing): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDateYmd);
    if (!$date instanceof DateTimeImmutable) {
        return [['text' => $eventDateYmd, 'sep' => false]];
    }

    if ($wing === 'year') {
        return [['text' => $date->format('Y'), 'sep' => false]];
    }

    if ($wing === 'month') {
        return [
            ['text' => $date->format('m'), 'sep' => false],
            amiga_time_travel_stamp_led_sep_part(),
            ['text' => $date->format('Y'), 'sep' => false],
        ];
    }

    return [
        ['text' => $date->format('d'), 'sep' => false],
        amiga_time_travel_stamp_led_sep_part(),
        ['text' => $date->format('m'), 'sep' => false],
        amiga_time_travel_stamp_led_sep_part(),
        ['text' => $date->format('Y'), 'sep' => false],
    ];
}

function amiga_time_travel_stamp_kicker(string $wing): string
{
    return match ($wing) {
        'month' => 'MONTH END REACHED',
        'year' => 'YEAR END REACHED',
        default => 'TEMPORAL LINK ESTABLISHED',
    };
}

function amiga_time_travel_stamp_a11y_label(string $eventDateYmd): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDateYmd);
    if (!$date instanceof DateTimeImmutable) {
        return 'As of ' . $eventDateYmd;
    }

    return 'As of ' . $date->format('j F Y');
}

function amiga_time_travel_stamp_cursor_help_blinking(): string
{
    return 'Click to pause cursor blink';
}

function amiga_time_travel_stamp_cursor_help_static(): string
{
    return 'Click to resume cursor blink';
}

/**
 * @return array{
 *   wing: string,
 *   kicker: string,
 *   led: list<array{text: string, sep: bool}>,
 *   a11y: string
 * }|null
 */
function amiga_time_travel_stamp_view(?AmigaSnapshotContext $ctx = null): ?array
{
    $ctx ??= amiga_time_travel_stamp_context();
    if ($ctx === null) {
        return null;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null || ($cutoff['event_date'] ?? '') === '') {
        return null;
    }

    $wing = $ctx->wing();
    $eventDate = (string) $cutoff['event_date'];

    return [
        'wing' => $wing,
        'kicker' => amiga_time_travel_stamp_kicker($wing),
        'led' => amiga_time_travel_stamp_led_parts($eventDate, $wing),
        'a11y' => amiga_time_travel_stamp_a11y_label($eventDate),
    ];
}

function amiga_time_travel_stamp_arrival_mode_from_request(): ?string
{
    if (!isset($_GET['k2_tt_entry'])) {
        return null;
    }
    $raw = (string) $_GET['k2_tt_entry'];
    if ($raw === '1') {
        return 'toggle';
    }
    if ($raw === 'wing') {
        return 'wing';
    }
    return null;
}

function amiga_time_travel_stamp_arrival_pending_from_request(): bool
{
    return amiga_time_travel_stamp_arrival_mode_from_request() === 'toggle';
}

/**
 * One-shot stamp arrival on Present to Time travel toggle (fade + typewriter).
 *
 * @return array{k2_tt_entry: string}
 */
function amiga_time_travel_stamp_arrival_entry_query(): array
{
    return ['k2_tt_entry' => '1'];
}

/**
 * One-shot kicker typewriter on ribbon wing tab change (no panel fade).
 *
 * @return array{k2_tt_entry: string}
 */
function amiga_time_travel_stamp_wing_arrival_entry_query(): array
{
    return ['k2_tt_entry' => 'wing'];
}

function amiga_time_travel_stamp_js_enqueue(bool $defer = true): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $path = $_SERVER['DOCUMENT_ROOT'] . '/js/k2-amiga-tt-stamp.js';
    $v = is_file($path) ? (int) filemtime($path) : 0;
    $deferAttr = $defer ? ' defer="defer"' : '';
    echo '<script type="text/javascript" src="/js/k2-amiga-tt-stamp.js?v=' . $v . '"' . $deferAttr . '></script>' . "\n";
}

function amiga_time_travel_stamp_render(?AmigaSnapshotContext $ctx = null): void
{
    $view = amiga_time_travel_stamp_view($ctx);
    if ($view === null) {
        return;
    }

    $wingClass = ' k2-amiga-tt-stamp--' . preg_replace('/[^a-z0-9-]/', '', $view['wing']);
    $arrivalMode = amiga_time_travel_stamp_arrival_mode_from_request();
    $arrivalPending = $arrivalMode === 'toggle';
    $ledFadePending = $arrivalMode === 'wing';
    $kickerTypewriter = $arrivalMode !== null;
    $pendingClass = $arrivalPending ? ' k2-amiga-tt-stamp--arrival-pending' : '';
    $ledFadeClass = $ledFadePending ? ' k2-amiga-tt-stamp--led-fade-pending' : '';
    $sepPeriodClass = AMIGA_TT_STAMP_LED_FIELD_SEP === '.' ? ' k2-amiga-tt-stamp--sep-period' : '';
    $kickerText = $kickerTypewriter ? '' : $view['kicker'];
    $cursorHelp = amiga_time_travel_stamp_cursor_help_blinking();
    ?>
<aside class="k2-amiga-tt-stamp<?php echo k2_h($wingClass . $pendingClass . $ledFadeClass . $sepPeriodClass); ?>" aria-label="<?php echo k2_h($view['a11y']); ?>">
	<p class="k2-amiga-tt-stamp__kicker" aria-hidden="true"><span class="k2-amiga-tt-stamp__prompt">&rsaquo;&rsaquo;</span> <span class="k2-amiga-tt-stamp__kicker-text" data-k2-tt-kicker-text="<?php echo k2_h($view['kicker']); ?>"><?php echo k2_h($kickerText); ?></span></p>
	<p class="k2-amiga-tt-stamp__clock" aria-hidden="true"><?php
    foreach ($view['led'] as $part) {
        if ($part['sep']) {
            echo '<span class="k2-amiga-tt-stamp__sep">' . k2_h($part['text']) . '</span>';
        } else {
            echo '<span class="k2-amiga-tt-stamp__segment">' . k2_h($part['text']) . '</span>';
        }
    }
    ?><button type="button" class="k2-amiga-tt-stamp__cursor" aria-label="<?php echo k2_h($cursorHelp); ?>" aria-pressed="true">_</button></p>
</aside>
    <?php
    amiga_time_travel_stamp_js_enqueue(false);
}