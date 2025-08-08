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

    public static function renderToggleSwitch(
        string $name,
        bool $checked = false,
        string $onLabel = 'An',
        string $offLabel = 'Aus',
        string $id = '',
        string ...$extraClasses
    ): string {
        $id = $id ?: 'toggle_' . uniqid();
        $classes = array_merge(['toggle-switch'], $extraClasses);

        // Template einbinden
        ob_start();
        $templatePath = $_SERVER['DOCUMENT_ROOT'] . '/templates/component/ToggleSwitch.tpl.php';
        include $templatePath;
        return ob_get_clean();
    }

    public static function renderCheckbox(string $name, bool $checked = false, string $label = '', string $id = '', string ...$extraClasses): string {
        $id = $id ?: 'checkbox_' . uniqid();
        $classes = implode(' ', array_merge(['form-checkbox'], $extraClasses));
        return sprintf(
            '<label class="%s">
                <input type="checkbox" id="%s" name="%s" %s>
                %s
            </label>',
            htmlspecialchars($classes),
            htmlspecialchars($id),
            htmlspecialchars($name),
            $checked ? 'checked' : '',
            htmlspecialchars($label)
        );
    }

    public static function renderSwitch(string $name, bool $checked = false, string $id = '', string ...$extraClasses): string {
        $id = $id ?: 'switch_' . uniqid();
        $classes = implode(' ', array_merge(['form-switch'], $extraClasses));
        return sprintf(
            '<label class="%s">
                <input type="checkbox" id="%s" name="%s" %s>
                <span class="slider"></span>
            </label>',
            htmlspecialchars($classes),
            htmlspecialchars($id),
            htmlspecialchars($name),
            $checked ? 'checked' : ''
        );
    }
}