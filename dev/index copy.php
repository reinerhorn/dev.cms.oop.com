<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php";
// include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/web_besucher.php"; // Unbenutztes Include entfernt

class CMSApp {
    private static $db;
    private static $language;
    private static $role;

    public static function run() {
        try {
            self::init(); // Initialisierung der Datenbankverbindung und Sitzungsvariablen
            if (!self::$db) {
                throw new Exception("Fehler: Die Datenbankverbindung konnte nicht aufgebaut werden.");
            }
            self::render(); // Rendering der Seite
        } catch (Exception $e) {
            // Fehlermeldung bei Fehlern anzeigen
            echo 'Fehler: ' . $e->getMessage();
        }
    }

    private static function init() {
        # include_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php"; // Auskommentiertes Include
        self::$db = getDbConnection(); // Verbindung zur Datenbank herstellen
        if (!isset(self::$db) || !(self::$db instanceof mysqli)) {
            throw new Exception("Ungültige oder fehlende Datenbankverbindung");
        }
        self::$language = $_SESSION['language'] ?? 'de'; // Falls keine Sprache gesetzt ist, default auf 'de'
        self::$role = $_SESSION['admin_a'] ?? null; // Falls kein Admin gesetzt ist, null
    }

    private static function render() {
        ?>
        <!DOCTYPE html>
        <html lang="<?= htmlspecialchars(self::$language) ?>">
        <head>
            <meta charset="UTF-8">
            <title>CMS DEMO</title>
            <?php
            // CSS-Dateien einbinden
            $css_files = ['style', 'language_selector', 'navi', 'services', 'admin', 'login', 'cards'];
            foreach ($css_files as $file) {
                echo '<link rel="stylesheet" href="/css/' . $file . '.css">' . PHP_EOL;
            }
            // JS-Dateien einbinden
            $js_files = ['app', 'functions', 'animations'];
            foreach ($js_files as $file) {
                echo '<script src="/js/' . $file . '.js"></script>' . PHP_EOL;
            }
            ?>
            <link rel="icon" href="/images/icon/favicon.ico" type="image/x-icon">
        </head>
        <body>
            <header><?php self::renderHeader(); ?></header> <!-- Header ausgeben -->
            <main><div class="content"><?php self::renderMain(); ?></div></main> <!-- Hauptinhalt der Seite -->
            <footer><?php self::renderFooter(); ?></footer> <!-- Footer ausgeben -->
        </body>
        </html>
        <?php
    }

    private static function renderHeader() {
        // Header für öffentliche Seiten und mit angemeldeten Benutzern
        if (is_null(self::$role)) {
            $stmt = self::$db->prepare("SELECT * FROM header WHERE role IS NULL AND language = ? LIMIT 1");
            $stmt->bind_param("s", self::$language);
        } else {
            $stmt = self::$db->prepare("SELECT * FROM header WHERE role = ? AND language = ? LIMIT 1");
            $stmt->bind_param("is", self::$role, self::$language);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($rec = $result->fetch_assoc()) {
            $headerId = $rec['id'];
            echo '<div class="' . htmlspecialchars($rec['css']) . '">';
            // Lade und gebe alle zugehörigen Bilder aus header_images aus, sortiert nach sort_order
            $stmtImgs = self::$db->prepare("SELECT image_url, link_url, alt_text FROM header_images WHERE header_id = ? ORDER BY sort_order ASC");
            $stmtImgs->bind_param("s", $headerId);
            $stmtImgs->execute();
            $images = $stmtImgs->get_result();
            while ($img = $images->fetch_assoc()) {
                $imgTag = '<img class="logo" src="' . htmlspecialchars(trim($img['image_url'])) . '" alt="' . htmlspecialchars($img['alt_text'] ?? 'logo') . '">';
                if (!empty($img['link_url'])) {
                    echo '<a href="' . htmlspecialchars($img['link_url']) . '">' . $imgTag . '</a>';
                } else {
                    echo $imgTag;
                }
            }
            echo '<a class="companyname" href="' . htmlspecialchars($rec['link']) . '">' . htmlspecialchars($rec['text']) . '</a>';
            echo '</div>';
        }

        include $_SERVER['DOCUMENT_ROOT'] . '/function/language_selector.inc.php'; // Sprachwechsler einbinden
        include $_SERVER['DOCUMENT_ROOT'] . '/function/navi.inc.php'; // Navigation einbinden
    }

    private static function renderMain() {
        // Standard-Startseite ermitteln, wenn keine Seite angegeben ist
        if (!isset($_REQUEST['page'])) {
            $result = self::$db->query("SELECT UNIX_TIMESTAMP(id) AS ts FROM page WHERE fk_translation_placeholder='PAGE_START_LABEL' LIMIT 1");
            $row = $result->fetch_assoc();
            if (!isset($row['ts'])) {
                die("⚠️ Keine Startseite gefunden!");
            }
            $_REQUEST['page'] = $row['ts'];
        }

        $pageId = filter_var($_REQUEST['page'], FILTER_VALIDATE_INT);
        if (!$pageId) {
            die("⚠️ Ungültige Seiten-ID.");
        }

        $stmt = self::$db->prepare("
            SELECT *, plugin.name AS plugin_label, UNIX_TIMESTAMP(page.id) AS page_id
            FROM page_config
            JOIN page ON page_config.fk_page_id = page.id
            JOIN plugin ON page_config.fk_plugin_id = plugin.id
            WHERE UNIX_TIMESTAMP(page.id) = ?
            ORDER BY page_config.idx ASC
        ");
        $stmt->bind_param('i', $_REQUEST['page']);
        $stmt->execute();
        $result = $stmt->get_result();

        $page_output_all = [];
        while ($record = $result->fetch_assoc()) {
            $print_all = $record['print_all'] == 1;
            $page_plugin = $record['page_id'] . '_' . $record['plugin_label'];

            if ($print_all && in_array($page_plugin, $page_output_all)) continue;
            if ($print_all) $page_output_all[] = $page_plugin;

            $paths = [
                '/plugin/', '/plugin/admin_plugin/', '/plugin/plugin_login/',
                '/plugin/plugin_member/', '/plugin/plugin_cards/', '/plugin/extra_plugin/'
            ];
            $plugin_found = false;

            // Überprüfen, ob die Plugin-Datei existiert
            foreach ($paths as $path) {
                $pluginLabel = preg_replace('/[^a-zA-Z0-9_]/', '', $record['plugin_label']);
                $plugin_file = $_SERVER['DOCUMENT_ROOT'] . $path . 'plugin_' . $pluginLabel . '.php';
                if (file_exists($plugin_file)) {
                    include $plugin_file;
                    $plugin_found = true;
                    break;
                }
            }

            if (!$plugin_found) {
                echo "<p class='error'>Fehler: Plugin '" . htmlspecialchars($record['plugin_label'], ENT_QUOTES) . "' nicht gefunden!</p>";
            }
        }
    }

    private static function renderFooter() {
        // Footer aus der Datenbank holen
        $stmt = self::$db->prepare("SELECT id, headline, link, label, css FROM footer LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($footer = $result->fetch_assoc()) {
            echo '<div class="' . htmlspecialchars($footer['css']) . '">';
            echo '© 2020 - ' . date("Y") . ' <a href="' . htmlspecialchars($footer['link'], ENT_QUOTES) . '">' . htmlspecialchars($footer['label'], ENT_QUOTES) . '</a>';

            // Bilder aus der Tabelle footer_images laden
            $footerId = $footer['id'];
            $stmtImgs = self::$db->prepare("SELECT image_url, link_url, alt_text FROM footer_images WHERE footer_id = ? ORDER BY sort_order ASC");
            $stmtImgs->bind_param("s", $footerId);
            $stmtImgs->execute();
            $images = $stmtImgs->get_result();
            while ($img = $images->fetch_assoc()) {
                $imgTag = '<img class="footer-icon" src="' . htmlspecialchars(trim($img['image_url'])) . '" alt="' . htmlspecialchars($img['alt_text'] ?? 'footer icon') . '">';
                if (!empty($img['link_url'])) {
                    echo '<a href="' . htmlspecialchars($img['link_url']) . '">' . $imgTag . '</a>';
                } else {
                    echo $imgTag;
                }
            }
            echo '</div>';
        }
    }

    public static function getDb() {
        return self::$db;
    }
}

// CMSApp ausführen
CMSApp::run();
?>