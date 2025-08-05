<?php
class DebugHelper {
    public static bool $enabled = false; // Nur aktivieren, wenn gewünscht

    public static function log(string $message, string $color = 'lime'): void {
        if (!self::$enabled) return;

        echo "<div style='background:$color;color:#000;padding:5px;font-family:monospace;
            border:1px solid #333;margin:5px 0;white-space:pre;'>🛠️ DEBUG: " . htmlspecialchars($message) . "</div>";
    }

    public static function enable(): void {
        self::$enabled = true;
    }

    public static function disable(): void {
        self::$enabled = false;
    }
}
