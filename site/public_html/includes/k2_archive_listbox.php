<?php
/**
 * Shared KOOL archive listbox markup (Status Leagues period pickers, form filters, …).
 *
 * @see js/k2-archive-listbox.js
 */

function k2_archive_listbox_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array{value?: string, label?: string, meta?: string} $choice
 */
function k2_archive_listbox_choice_trigger_label(array $choice, string $fixedTriggerLabel): string
{
    $value = (string) ($choice['value'] ?? '');
    if ($fixedTriggerLabel !== '' && $value === '-1') {
        return $fixedTriggerLabel;
    }

    return (string) ($choice['label'] ?? '');
}

/**
 * Pick the row whose text should set first-paint width (ghost sizer).
 *
 * @param array<int, array{value: string, label: string, meta?: string, accent?: bool}> $choices
 * @return array{label: string, meta: string, split: bool}
 */
function k2_archive_listbox_pick_sizer_row(
    array $choices,
    string $selectedLabel,
    string $selectedMeta,
    bool $split,
    bool $triggerShowsMeta,
    string $fixedTriggerLabel
): array {
    $bestLabel = '';
    $bestMeta = '';
    $bestScore = -1;

    /** @var list<array{label: string, meta: string}> $candidates */
    $candidates = [];
    foreach ($choices as $choice) {
        $candidates[] = [
            'label' => k2_archive_listbox_choice_trigger_label($choice, $fixedTriggerLabel),
            'meta' => array_key_exists('meta', $choice) ? (string) $choice['meta'] : '',
        ];
    }
    if ($selectedLabel !== '' || ($triggerShowsMeta && $selectedMeta !== '')) {
        $candidates[] = [
            'label' => $selectedLabel,
            'meta' => $triggerShowsMeta ? $selectedMeta : '',
        ];
    }

    foreach ($candidates as $row) {
        if ($split) {
            $score = mb_strlen($row['label']) + mb_strlen($row['meta']);
        } else {
            $score = mb_strlen($row['label']);
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestLabel = $row['label'];
            $bestMeta = $row['meta'];
        }
    }

    if ($bestScore <= 0) {
        $bestLabel = "\xc2\xa0";
        $bestMeta = '';
    }

    return [
        'label' => $bestLabel,
        'meta' => $bestMeta,
        'split' => $split,
    ];
}

/**
 * @param array<int, array{value: string, label: string, meta?: string, accent?: bool}> $choices
 * @param ?string $idleValue When set, closed trigger is empty at this value and uses link-star when selected differs.
 * @param bool $accentActive When true, closed trigger always uses link-star (e.g. coupled year-mode picker).
 */
function k2_archive_listbox_render(
    string $inputName,
    string $inputId,
    string $selectedValue,
    array $choices,
    string $ariaLabel,
    string $triggerExtraClass = '',
    string $fixedTriggerLabel = '',
    bool $triggerShowsMeta = false,
    ?string $idleValue = null,
    bool $accentActive = false
): void {
    $hasMetaOptions = false;
    $hasAccentOptions = false;
    foreach ($choices as $choice) {
        if (array_key_exists('meta', $choice) && (string) $choice['meta'] !== '') {
            $hasMetaOptions = true;
        }
        if (!empty($choice['accent'])) {
            $hasAccentOptions = true;
        }
    }

    $selectedLabel = '';
    $selectedMeta = '';
    $selectedAccent = false;
    if ($fixedTriggerLabel !== '' && ($selectedValue === '-1' || $selectedValue === '')) {
        $selectedLabel = $fixedTriggerLabel;
    } else {
        $foundMatch = false;
        foreach ($choices as $choice) {
            if ((string) $choice['value'] === $selectedValue) {
                $selectedLabel = (string) $choice['label'];
                $selectedMeta = array_key_exists('meta', $choice) ? (string) $choice['meta'] : '';
                $selectedAccent = !empty($choice['accent']);
                $foundMatch = true;
                break;
            }
        }
        if (!$foundMatch && $selectedLabel === '' && $selectedValue !== '') {
            $selectedLabel = $selectedValue;
        }
    }

    $listboxId = $inputId . '-listbox';
    $triggerClass = 'k2-archive-listbox__trigger server-period-activity-leaderboard__input';
    if ($triggerExtraClass !== '') {
        $triggerClass .= ' ' . $triggerExtraClass;
    }
    if ($triggerShowsMeta && $hasMetaOptions) {
        $triggerClass .= ' k2-archive-listbox__trigger--split';
    }
    $filterPickAccent = $idleValue !== null && (string) $selectedValue !== (string) $idleValue;
    if ($filterPickAccent || $accentActive || ($idleValue === null && $selectedAccent)) {
        $triggerClass .= ' k2-link-star';
    }
    $boxClass = 'k2-archive-listbox';
    if ($hasMetaOptions || $hasAccentOptions || $fixedTriggerLabel !== '') {
        $boxClass .= ' k2-archive-listbox--meta-options';
    }
    $boxAttrs = 'data-k2-archive-listbox';
    if ($idleValue !== null) {
        $boxAttrs .= ' data-k2-listbox-idle-value="' . k2_archive_listbox_h($idleValue) . '"';
    }
    if ($accentActive) {
        $boxAttrs .= ' data-k2-listbox-accent-active="1"';
    }
    $useSplitOptions = $hasMetaOptions || $hasAccentOptions || $fixedTriggerLabel !== '';
    $sizerRow = k2_archive_listbox_pick_sizer_row(
        $choices,
        $selectedLabel,
        $selectedMeta,
        $useSplitOptions,
        $triggerShowsMeta && $hasMetaOptions,
        $fixedTriggerLabel
    );
    $useGhostSizer = $choices !== [];
    if ($useGhostSizer) {
        $boxClass .= ' k2-archive-listbox--ghost-sized';
    }
    $sizerClass = 'k2-archive-listbox__width-sizer';
    if ($sizerRow['split']) {
        $sizerClass .= ' k2-archive-listbox__width-sizer--split';
    }
    if ($filterPickAccent || $accentActive) {
        $sizerClass .= ' k2-archive-listbox__width-sizer--accent';
    }
    ?>
<div class="<?php echo k2_archive_listbox_h($boxClass); ?>" <?php echo $boxAttrs; ?>>
    <input type="hidden" id="<?php echo k2_archive_listbox_h($inputId); ?>" name="<?php echo k2_archive_listbox_h($inputName); ?>" class="k2-archive-listbox__value" value="<?php echo k2_archive_listbox_h($selectedValue); ?>" />
<?php if ($useGhostSizer) { ?>
    <div class="k2-archive-listbox__size-anchor">
        <span class="<?php echo k2_archive_listbox_h($sizerClass); ?>" aria-hidden="true">
<?php if ($sizerRow['split']) { ?>
            <span class="k2-archive-listbox__option-label"><?php echo k2_archive_listbox_h($sizerRow['label']); ?></span>
<?php if ($sizerRow['meta'] !== '') { ?>
            <span class="k2-archive-listbox__option-meta"><?php echo k2_archive_listbox_h($sizerRow['meta']); ?></span>
<?php } ?>
<?php } else { ?>
            <?php echo k2_archive_listbox_h($sizerRow['label']); ?>
<?php } ?>
        </span>
<?php } ?>
    <button type="button" class="<?php echo k2_archive_listbox_h($triggerClass); ?>" aria-label="<?php echo k2_archive_listbox_h($ariaLabel); ?>" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo k2_archive_listbox_h($listboxId); ?>">
        <span class="k2-archive-listbox__label"><?php echo k2_archive_listbox_h($selectedLabel); ?></span>
<?php if ($triggerShowsMeta && $hasMetaOptions) { ?>
        <span class="k2-archive-listbox__trigger-meta<?php echo $selectedAccent ? ' k2-link-star' : ''; ?>"><?php echo k2_archive_listbox_h($selectedMeta); ?></span>
<?php } ?>
        <span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
    </button>
<?php if ($useGhostSizer) { ?>
    </div>
<?php } ?>
    <ul id="<?php echo k2_archive_listbox_h($listboxId); ?>" class="k2-archive-listbox__panel" role="listbox" tabindex="-1" hidden="hidden">
<?php foreach ($choices as $choice) {
    $value = (string) $choice['value'];
    $label = (string) $choice['label'];
    $meta = array_key_exists('meta', $choice) ? (string) $choice['meta'] : '';
    $sel = $value === $selectedValue;
    $optClass = 'k2-archive-listbox__option';
    if ($hasMetaOptions || $hasAccentOptions || $fixedTriggerLabel !== '') {
        $optClass .= ' k2-archive-listbox__option--split';
    }
    if ($sel) {
        $optClass .= ' is-selected';
    }
    $labelClass = 'k2-archive-listbox__option-label';
    if (!empty($choice['accent'])) {
        $labelClass .= ' k2-link-star';
    }
    $triggerLabelAttr = '';
    if ($fixedTriggerLabel !== '' && $value === '-1') {
        $triggerLabelAttr = ' data-trigger-label="' . k2_archive_listbox_h($fixedTriggerLabel) . '"';
    }
    $metaAttr = $meta !== '' ? ' data-option-meta="' . k2_archive_listbox_h($meta) . '"' : '';
    $accentAttr = !empty($choice['accent']) ? ' data-option-accent="1"' : '';
    ?>
        <li class="<?php echo k2_archive_listbox_h($optClass); ?>" role="option" data-value="<?php echo k2_archive_listbox_h($value); ?>" aria-selected="<?php echo $sel ? 'true' : 'false'; ?>"<?php echo $triggerLabelAttr . $metaAttr . $accentAttr; ?>>
<?php if ($hasMetaOptions || $hasAccentOptions || $fixedTriggerLabel !== '') { ?>
            <span class="<?php echo k2_archive_listbox_h($labelClass); ?>"><?php echo k2_archive_listbox_h($label); ?></span>
<?php if ($meta !== '') {
    $metaClass = 'k2-archive-listbox__option-meta';
    if (!empty($choice['accent'])) {
        $metaClass .= ' k2-link-star';
    }
    ?>
            <span class="<?php echo k2_archive_listbox_h($metaClass); ?>"><?php echo k2_archive_listbox_h($meta); ?></span>
<?php } ?>
<?php } else { ?>
            <?php echo k2_archive_listbox_h($label); ?>
<?php } ?>
        </li>
<?php } ?>
    </ul>
</div>
<?php
}
