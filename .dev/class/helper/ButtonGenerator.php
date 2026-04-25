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
}
?>
