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
 * @param array<int, array{value: string, label: string, meta?: string}> $choices
 */
function k2_archive_listbox_render(
    string $inputName,
    string $inputId,
    string $selectedValue,
    array $choices,
    string $ariaLabel,
    string $triggerExtraClass = '',
    string $fixedTriggerLabel = ''
): void {
    $hasMetaOptions = false;
    foreach ($choices as $choice) {
        if (array_key_exists('meta', $choice) && (string) $choice['meta'] !== '') {
            $hasMetaOptions = true;
            break;
        }
    }

    $selectedLabel = '';
    if ($fixedTriggerLabel !== '') {
        $selectedLabel = $fixedTriggerLabel;
    } else {
        foreach ($choices as $choice) {
            if ((string) $choice['value'] === $selectedValue) {
                $selectedLabel = (string) $choice['label'];
                break;
            }
        }
        if ($selectedLabel === '' && $selectedValue !== '') {
            $selectedLabel = $selectedValue;
        }
    }

    $listboxId = $inputId . '-listbox';
    $triggerClass = 'k2-archive-listbox__trigger server-period-activity-leaderboard__input';
    if ($triggerExtraClass !== '') {
        $triggerClass .= ' ' . $triggerExtraClass;
    }
    $boxClass = 'k2-archive-listbox';
    if ($hasMetaOptions || $fixedTriggerLabel !== '') {
        $boxClass .= ' k2-archive-listbox--meta-options';
    }
    ?>
<div class="<?php echo k2_archive_listbox_h($boxClass); ?>" data-k2-archive-listbox>
    <input type="hidden" id="<?php echo k2_archive_listbox_h($inputId); ?>" name="<?php echo k2_archive_listbox_h($inputName); ?>" class="k2-archive-listbox__value" value="<?php echo k2_archive_listbox_h($selectedValue); ?>" />
    <button type="button" class="<?php echo k2_archive_listbox_h($triggerClass); ?>" aria-label="<?php echo k2_archive_listbox_h($ariaLabel); ?>" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo k2_archive_listbox_h($listboxId); ?>">
        <span class="k2-archive-listbox__label"><?php echo k2_archive_listbox_h($selectedLabel); ?></span>
        <span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
    </button>
    <ul id="<?php echo k2_archive_listbox_h($listboxId); ?>" class="k2-archive-listbox__panel" role="listbox" tabindex="-1" hidden="hidden">
<?php foreach ($choices as $choice) {
    $value = (string) $choice['value'];
    $label = (string) $choice['label'];
    $meta = array_key_exists('meta', $choice) ? (string) $choice['meta'] : '';
    $sel = $value === $selectedValue;
    $optClass = 'k2-archive-listbox__option';
    if ($hasMetaOptions || $fixedTriggerLabel !== '') {
        $optClass .= ' k2-archive-listbox__option--split';
    }
    if ($sel) {
        $optClass .= ' is-selected';
    }
    $triggerLabelAttr = $fixedTriggerLabel !== '' ? ' data-trigger-label="' . k2_archive_listbox_h($fixedTriggerLabel) . '"' : '';
    ?>
        <li class="<?php echo k2_archive_listbox_h($optClass); ?>" role="option" data-value="<?php echo k2_archive_listbox_h($value); ?>" aria-selected="<?php echo $sel ? 'true' : 'false'; ?>"<?php echo $triggerLabelAttr; ?>>
<?php if ($hasMetaOptions || $fixedTriggerLabel !== '') { ?>
            <span class="k2-archive-listbox__option-label"><?php echo k2_archive_listbox_h($label); ?></span>
<?php if ($meta !== '') { ?>
            <span class="k2-archive-listbox__option-meta"><?php echo k2_archive_listbox_h($meta); ?></span>
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
