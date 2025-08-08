<?php
namespace component;

class ToggleSwitch
{
    public static function render(
        string $id,
        string $name,
        string $labelOn = 'An',
        string $labelOff = 'Aus',
        bool $isChecked = false,
        array $extraClasses = [],
        array $attributes = [],
        string $templatePath = ''
    ): string {
        $templatePath = $templatePath ?: $_SERVER['DOCUMENT_ROOT'] . '/templates/component/ToggleSwitch.tpl.php';

        // Output Buffering zur Template-Ausgabe
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
