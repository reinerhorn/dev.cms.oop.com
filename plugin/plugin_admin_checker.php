<?php
class PluginValidator {
    private string $pluginBaseDir;
    private array $pluginPaths = [
        '/plugin/',
        '/plugin/admin_plugin/',
        '/plugin/plugin_login/',
        '/plugin/plugin_member/',
        '/plugin/plugin_cards/',
        '/plugin/extra_plugin/',
        '/plugin/plugin_shop/',
    ];

    public function __construct(string $pluginBaseDir) {
        $this->pluginBaseDir = $pluginBaseDir;
    }

    /**
     * Überprüft, ob die Plugin-Dateien existieren, die in page_config referenziert werden.
     * Gibt eine Liste fehlender Plugins zurück.
     *
     * @param mysqli $db
     * @return array Fehlende Plugin-Dateien
     */
    public function checkMissingPlugins(mysqli $db): array {
        $missingPlugins = [];
        $query = "SELECT DISTINCT plugin.name FROM page_config JOIN plugin ON page_config.fk_plugin_id = plugin.id";
        $result = $db->query($query);

        while ($row = $result->fetch_assoc()) {
            $pluginName = $row['name'];
            if (!$this->pluginFileExists($pluginName)) {
                $missingPlugins[] = $pluginName;
            }
        }

        return $missingPlugins;
    }

    private function pluginFileExists(string $pluginName): bool {
        foreach ($this->pluginPaths as $relativePath) {
            $filePath = $this->pluginBaseDir . $relativePath . 'plugin_' . $pluginName . '.php';
            if (file_exists($filePath)) {
                return true;
            }
        }
        return false;
    }
}

// Beispielnutzung:
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
$db = getDbConnection();
$validator = new PluginValidator($_SERVER['DOCUMENT_ROOT']);
$missing = $validator->checkMissingPlugins($db);

if (!empty($missing)) {
    echo "<div style='color:red;'><strong>Fehlende Plugin-Dateien:</strong><ul>";
    foreach ($missing as $plugin) {
        echo "<li>plugin_{$plugin}.php</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div style='color:green;'>✅ Alle Plugins vorhanden.</div>";
}

