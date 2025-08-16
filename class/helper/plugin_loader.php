<?php
/**
 * Plugin Loader
 * Lädt alle Plugins im /plugin Verzeichnis automatisch.
 * Unterstützt:
 *  - einzelne PHP-Dateien (z. B. plugin_debug_switch.php)
 *  - Plugin-Ordner (z. B. plugin_shop/, plugin_member/)
 */

$pluginDir = __DIR__;

// Alle Einträge im plugin-Verzeichnis durchgehen
foreach (scandir($pluginDir) as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === 'plugin_loader.php') {
        continue;
    }

    $path = $pluginDir . DIRECTORY_SEPARATOR . $entry;

    if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        // Einzelne Plugin-Datei direkt einbinden
        require_once $path;
    } elseif (is_dir($path)) {
        // Plugin-Ordner -> nach plugin_[name].php suchen
        $mainFile = $path . DIRECTORY_SEPARATOR . $entry . '.php';
        if (file_exists($mainFile)) {
            require_once $mainFile;
        }
    }
}
