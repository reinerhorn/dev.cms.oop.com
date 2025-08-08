<?php
/** @var string $id */
/** @var string $name */
/** @var string $labelOn */
/** @var string $labelOff */
/** @var bool $isChecked */
/** @var array $extraClasses */
/** @var array $attributes */

$classes = implode(' ', array_merge(['toggle-switch'], $extraClasses ?? []));

// Attribute-Strings vorbereiten
$inputAttrs = '';
$labelAttrs = '';
foreach ($attributes ?? [] as $key => $val) {
    $attr = htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
    if (str_starts_with($key, 'data-')) {
        $inputAttrs .= ' ' . $attr;
    } else {
        $labelAttrs .= ' ' . $attr;
    }
}

$wrapperClass = 'toggle-switch-wrapper ' . $classes;
if ($isChecked) {
    $wrapperClass .= ' checked';
}
?>
<!-- TEST: Wird geladen -->
<label class="<?= htmlspecialchars($wrapperClass) ?>"<?= $labelAttrs ?>>
    <input type="checkbox"
           id="<?= htmlspecialchars($id) ?>"
           name="<?= htmlspecialchars($name) ?>"
           class="toggle-checkbox"
           data-toggle-key="debug_toggle"
           <?= $isChecked ? 'checked ' : '' ?><?= $inputAttrs ?>>
    <span class="toggle-slider">
        <?php if (!empty($labelOn)): ?>
            <span class="toggle-label-on"><?= htmlspecialchars($labelOn) ?></span>
        <?php endif; ?>
        <?php if (!empty($labelOff)): ?>
            <span class="toggle-label-off"><?= htmlspecialchars($labelOff) ?></span>
        <?php endif; ?>
        <span class="toggle-slider-handle"></span>
    </span>
</label>