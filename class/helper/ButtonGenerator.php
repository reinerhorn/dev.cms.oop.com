<?php

class ButtonGenerator {
    public static function render(string $name, string $value, string $label, string ...$extraClasses): string {
        $classes = array_merge([$value === 'save' ? 'button-save' : 'button-delete'], $extraClasses);
        return sprintf(
            '<button class="%s" name="%s" value="%s">%s</button>',
            htmlspecialchars(implode(' ', $classes)),
            htmlspecialchars($name),
            htmlspecialchars($value),
            htmlspecialchars($label)
        );
    }
    public static function renderToggleSwitch(string $name, bool $checked = false, string $onLabel = 'ON', string $offLabel = 'OFF', string $id = '', string ...$extraClasses): string {
        $id = $id ?: 'toggle_' . uniqid();
        $classes = implode(' ', array_merge(['toggle-switch'], $extraClasses));
        return sprintf(
            '<div class="%s">
                <input type="checkbox" id="%s" name="%s" %s>
                <label for="%s" class="switch-label">
                    <span class="switch-inner" data-on="%s" data-off="%s"></span>
                    <span class="switch-switch"></span>
                </label>
            </div>',
            htmlspecialchars($classes),
            htmlspecialchars($id),
            htmlspecialchars($name),
            $checked ? 'checked' : '',
            htmlspecialchars($id),
            htmlspecialchars($onLabel),
            htmlspecialchars($offLabel)
        );
    }
}
?>
