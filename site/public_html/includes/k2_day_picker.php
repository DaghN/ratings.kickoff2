<?php
/**
 * Reusable day picker — same flatpickr trigger as Status league panel.
 *
 * @see js/k2-day-picker.js
 * @see includes/status_period_competitions_section.php (day picker markup source)
 */
declare(strict_types=1);

function k2_render_day_picker(
    string $inputId,
    string $inputName,
    string $valueYmd,
    string $ariaLabel = 'Select date',
    ?string $minYmd = null,
    ?string $maxYmd = null
): void {
    if (!function_exists('k2_format_calendar_day_picker_label')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
    }

    $dayLabel = $valueYmd !== '' ? k2_format_calendar_day_picker_label($valueYmd) : '';
    $idEsc = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');
    $nameEsc = htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8');
    $valueEsc = htmlspecialchars($valueYmd, ENT_QUOTES, 'UTF-8');
    $labelEsc = htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8');
    $dayLabelEsc = htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8');
    $minAttr = $minYmd !== null && $minYmd !== ''
        ? ' data-min="' . htmlspecialchars($minYmd, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $maxAttr = $maxYmd !== null && $maxYmd !== ''
        ? ' data-max="' . htmlspecialchars($maxYmd, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    ?>
<span class="k2-status-day-picker k2-archive-listbox k2-archive-listbox--day" data-k2-day-picker>
  <span class="server-period-activity-leaderboard__date-control">
    <input type="hidden" id="<?php echo $idEsc; ?>" name="<?php echo $nameEsc; ?>" class="k2-day-picker__value k2-status-day-picker__value" value="<?php echo $valueEsc; ?>"<?php echo $minAttr . $maxAttr; ?> />
    <input type="text" class="k2-status-day-picker__fp-anchor" value="<?php echo $valueEsc; ?>" aria-hidden="true" tabindex="-1" autocomplete="off" readonly="readonly" />
    <button type="button" class="k2-archive-listbox__trigger k2-status-day-picker__trigger server-period-activity-leaderboard__input server-period-activity-leaderboard__input--day" aria-label="<?php echo $labelEsc; ?>" aria-expanded="false" aria-controls="<?php echo $idEsc; ?>">
      <span class="k2-archive-listbox__label" data-day-picker-label><?php echo $dayLabelEsc; ?></span>
      <span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
    </button>
  </span>
</span>
    <?php
}

function k2_render_day_picker_assets(): void
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    ?>
<link href="/stylesheets/flatpickr.min.css?v=<?php echo (int) @filemtime($docRoot . '/stylesheets/flatpickr.min.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/flatpickr.min.js?v=<?php echo (int) @filemtime($docRoot . '/js/flatpickr.min.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($docRoot . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/k2-day-picker.js?v=<?php echo (int) @filemtime($docRoot . '/js/k2-day-picker.js'); ?>" defer="defer"></script>
    <?php
}
