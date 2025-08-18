<?php
include_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php";
include_once $_SERVER['DOCUMENT_ROOT'] . "/class/navi/navi.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserRoleManager.php';
// Sicherstellen, dass keine Ausgabe vor der Umleitung erfolgt
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/inc/session.php';

    if (class_exists('CMSLoginSession') && method_exists('CMSLoginSession', 'logout')) {
        CMSLoginSession::logout();
    } elseif (class_exists('CMSAdminSession') && method_exists('CMSAdminSession', 'logout')) {
        CMSAdminSession::logout();
    } else {
        session_start();
        session_destroy();
        header('Location: /index.php');
        exit;
    }
    exit;
}
// Neue Sprache übernehmen
if (isset($_GET['language'])) {
    $_SESSION['language'] = $_GET['language'];
}
class CMSAppFrontend {
    private static $db;
    private static $language = 'de';
    private static $role = null;
    public static function setRole(?int $role): void {
        self::$role = $role;
    }
    public static function getRole(): ?int {
        return self::$role ?? 0;
    }
    public static function getPageTitle(): string {
        return "HD Staffing Services";
    }
    public static function getHeaderHtml(): ?string {
        $role = self::getRole();
        $sql = "SELECT * FROM header WHERE language = ? AND (role IS NULL OR role = ?) ORDER BY role DESC LIMIT 1";
        if (!self::$db) {
            self::$db = self::getDb();
        }
        $db = self::$db;
        $stmt = $db->prepare($sql);
        $language = $_SESSION['language'] ?? self::$language;
        $stmt->bind_param("si", $language, $role);
        error_log("🔍 HEADER SQL ROLE=$role LANGUAGE=$language");
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rec = $result->fetch_assoc()) {
            error_log("✅ HEADER FOUND: " . print_r($rec, true));
            $html = '<div class="' . htmlspecialchars($rec['css']) . '">';
            if (!empty($rec['images'])) {
                $html .= '<a title="' . htmlspecialchars($rec['label']) . '" href="' . htmlspecialchars($rec['link']) . '">';
                $html .= '<img class="logo" src="' . htmlspecialchars($rec['images']) . '" alt="logo">';
                $html .= '</a>';
                $html .= '<a class="companyname" title="' . htmlspecialchars($rec['label']) . '" href="' . htmlspecialchars($rec['link']) . '">' . htmlspecialchars($rec['headline']) . '</a>';
            } else {
                $html .= '<a href="' . htmlspecialchars($rec['link']) . '">' . htmlspecialchars($rec['label']) . '</a>';
            }
            $html .= '</div>';
            // Header-Bilder
            $imagesStmt = $db->prepare("SELECT image_url, link_url, alt_text FROM header_images WHERE header_id = ? ORDER BY sort_order ASC");
            $imagesStmt->bind_param("s", $rec['id']);
            $imagesStmt->execute();
            $imagesResult = $imagesStmt->get_result();

            if ($imagesResult->num_rows > 0) {
                $html .= '<div class="header-images">';
                while ($img = $imagesResult->fetch_assoc()) {
                    $imgTag = '<img src="' . htmlspecialchars($img['image_url']) . '" alt="' . htmlspecialchars($img['alt_text']) . '">';
                    $html .= !empty($img['link_url']) ? '<a href="' . htmlspecialchars($img['link_url']) . '">' . $imgTag . '</a>' : $imgTag;
                }
                $html .= '</div>';
            }
            #$html .= '<div id="LanguageSelector" class="language-selector">';
            $html .= self::getLanguageSelectorHtml($_SESSION['language'] ?? self::$language);
            #$html .= '</div>';
            return $html;
        }
        error_log("❌ Kein passender Header gefunden für Sprache $language und Rolle $role");
        error_log("ℹ️ Fallback-Header wird angezeigt.");
        return '<div class="header">
    <a title="Home" href="/"><img class="logo" src="/images/hd-logo.webp" alt="logo"></a>
    <a class="companyname" title="Home" href="/">HD Staffing Services</a>'
    . self::getLanguageSelectorHtml($_SESSION['language'] ?? self::$language) .
    '</div>';
    }
    public static function getFooterHtml(): string {
        ob_start();
        self::renderFooter();
        return ob_get_clean();
    }
    public static function renderFooter(): void {
        $db = self::$db ?? self::getDb();
        $stmt = $db->prepare("SELECT id, link, version, headline AS company FROM footer WHERE language = ? LIMIT 1");
        $language = $_SESSION['language'] ?? self::$language;
        $stmt->bind_param("s", $language);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($footer = $result->fetch_assoc()) {
            echo '<footer>';
            echo '© 2020 - ' . date("Y") . ' ';
            echo '<a href="' . htmlspecialchars($footer['link']) . '">';
            echo htmlspecialchars($footer['company']) . ' ';
            echo htmlspecialchars($footer['version']);
            echo '</a>';

            // Footer Images (moved here from removed method)
            $imgStmt = $db->prepare("SELECT image_url, alt_text FROM footer_images WHERE footer_id = ? ORDER BY sort_order ASC");
            $imgStmt->bind_param("s", $footer['id']);
            $imgStmt->execute();
            $imgResult = $imgStmt->get_result();
            if ($imgResult->num_rows > 0) {
                echo '<div class="footer-images">';
                while ($img = $imgResult->fetch_assoc()) {
                    echo '<img src="' . htmlspecialchars($img['image_url']) . '" alt="' . htmlspecialchars($img['alt_text']) . '">';
                }
                echo '</div>';
            }

            echo '</footer>';
        } else {
            echo '<footer>© ' . date("Y") . '</footer>';
        }
    }
    public static function getContentHtml($pageId): string {
        $_REQUEST['page'] = $pageId;
        ob_start();
        self::renderMain();
        return ob_get_clean();
    }
    public static function getLanguageSelectorHtml($language): string {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/function/language_selector.inc.php';
        $selector = new LanguageSelector(self::$db ?? self::getDb(), $language);
        return $selector->render();
    }
    public static function getNavigationHtml(mysqli $db, ?string $language = null, ?int $pageId = null): string {
        $language = $_SESSION['language'] ?? self::$language;
        $role = self::getRole();
        $pageId = $pageId ?? self::getStartPageId();
        $nav = new Navigation($db, $language, $role, $pageId);
        
        $logoutPageId = self::getLogoutPageId();
        $navHtml = $nav->render();
        if ($logoutPageId) {
            $navHtml = str_replace("?page={$logoutPageId}", '?action=logout', $navHtml);
        }
        return $navHtml;
    }
    public static function render(): void {
        self::getHeaderHtml();
        // DEBUG: Aktuelle Rolle ist: " . var_export(self::$role, true)
        #echo "<!-- DEBUG: Aktuelle Rolle ist: " . var_export(self::$role, true) . " -->";
        echo self::getNavigationHtml(
            self::getDb(),
            self::$language,
            $_REQUEST['page'] ?? null
        );
        self::renderMain();
        echo self::getFooterHtml();
    }
    public static function run(): void {
        self::init();
        self::render();
    }
    public static function init(): void {
        self::getDb();
        if (isset($_GET['language'])) {
            $_SESSION['language'] = $_GET['language'];
        }
        self::$language = $_SESSION['language'] ?? 'de';
        error_log("🌐 SESSION LANGUAGE = " . self::$language);
        
        // Korrigierte Rollenzuweisung
        if (isset($_SESSION['admin_a'])) {
            self::$role = $_SESSION['admin_a'] == 1 ? 1 : 2;
        } else {
            self::$role = 0; // Gastrolle setzen, nicht null
        }

        $_SESSION['role'] = self::$role;
        self::setRole(self::$role);

        // Benutzerrechte laden (UserRoleManager)
        require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserRoleManager.php';
        $roleManager = new UserRoleManager(self::$db, $_SESSION['user_id'] ?? null);
        $_SESSION['permissions'] = $roleManager->getPermissions();

        // PageIntegrityChecker aufrufen, um sicherzustellen, dass die öffentliche Startseite existiert
        require_once $_SERVER['DOCUMENT_ROOT'] . "/class/security/PageIntegrityChecker.php";
        $checker = new PageIntegrityChecker(self::$db);
        $checker->ensurePublicStartPageExists();
    }
    public static function getStartPageId(): ?int {
        $result = self::$db->query("SELECT UNIX_TIMESTAMP(id) AS ts FROM page WHERE fk_translation_placeholder='PAGE_START_LABEL' LIMIT 1");
        $row = $result->fetch_assoc();
        return $row['ts'] ?? null;
    }
    public static function getLogoutPageId(): ?int {
        $role = self::getRole();
        $stmt = self::$db->prepare("
            SELECT UNIX_TIMESTAMP(id) AS ts 
            FROM page 
            WHERE fk_translation_placeholder = 'PAGE_LOGOUT_LABEL' 
            AND (role IS NULL OR role = ?) 
            ORDER BY role DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return $row['ts'];
        }
        return null;
    }
    public static function renderMain(): void {
        if (empty($_REQUEST['page'])) {
            $startId = self::getStartPageId();
            if (empty($startId)) {
                die("⚠️ Keine Startseite gefunden!");
            }
            $_REQUEST['page'] = $startId;
        }

        $pageId = filter_var($_REQUEST['page'], FILTER_VALIDATE_INT);
        $filterContentId = $_REQUEST['plugin_content_id'] ?? null;

        if (!$pageId) {
            die("⚠️ Ungültige Seiten-ID.");
        }
        // Neue Filterlogik: Nur wenn plugin_content_id als gültiger Timestamp übergeben wurde
        if (!empty($filterContentId) && is_numeric($filterContentId)) {
            $stmt = self::$db->prepare("
                SELECT *, plugin.name AS plugin_label, UNIX_TIMESTAMP(page.id) AS page_id
                FROM page_config
                JOIN page ON page_config.fk_page_id = page.id
                JOIN plugin ON page_config.fk_plugin_id = plugin.id
                WHERE UNIX_TIMESTAMP(page_config.plugin_content_id) = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $filterContentId);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();

            if ($record) {
                // DEBUG-Ausgabe direkt nach if ($record)
                echo "<pre style='background:#f8f8f8;padding:1em;border:1px solid #ccc'>";
                echo "DEBUG plugin_content_id: {$record['plugin_content_id']}\n";
                echo "TS (datenbank): " . strtotime($record['plugin_content_id']) . "\n";
                echo "TS (url param): " . htmlspecialchars((string)$filterContentId) . "\n";
                echo "</pre>";
                error_log("🎯 Direktes Rendering plugin_content_id={$record['plugin_content_id']}, Plugin={$record['plugin_label']}");
                $loader = new PluginLoader($record['plugin_label']);
                $loader->render();
            } else {
                echo "<div class='error'>❌ Kein passender Plugin-Eintrag gefunden.</div>";
            }
            return;
        }
        // Ab hier nur ausführen, wenn kein gezielter plugin_content_id-Filter verwendet wurde
        else {
            $stmt = self::$db->prepare("
                SELECT *, plugin.name AS plugin_label, UNIX_TIMESTAMP(page.id) AS page_id
                FROM page_config
                JOIN page ON page_config.fk_page_id = page.id
                JOIN plugin ON page_config.fk_plugin_id = plugin.id
                WHERE UNIX_TIMESTAMP(page.id) = ?
                ORDER BY page_config.idx ASC
            ");
            $stmt->bind_param('i', $pageId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $page_output_all = [];

        while ($record = $result->fetch_assoc()) {
            $page_plugin = $record['page_id'] . '_' . $record['plugin_label'];
            if ($record['print_all'] == 1 && in_array($page_plugin, $page_output_all)) {
                continue;
            }
            if ($record['plugin_label'] === 'plaintext') {
                error_log("🔍 PLAINTEXT Plugin erkannt. Content-ID: " . $record['plugin_content_id']);
                if (!class_exists('PluginPlaintext')) {
                    foreach ((new PluginLoader('plaintext'))->pluginPaths as $path) {
                        $pluginFile = $_SERVER['DOCUMENT_ROOT'] . $path . 'plugin_plaintext.php';
                        if (file_exists($pluginFile)) {
                            require_once $pluginFile;
                            break;
                        }
                    }
                }
                if (class_exists('PluginPlaintext')) {
                    $page_plugin = $record['page_id'] . '_' . $record['plugin_label'];
                    if ($record['print_all'] == 1 && in_array($page_plugin, $page_output_all)) {
                        continue;
                    }
                    if ($record['print_all'] == 1) {
                        $page_output_all[] = $page_plugin;
                    }
                    $pluginContentId = (string) $record['plugin_content_id'];
                    if ($pluginContentId === false) {
                        echo "<div class=\"warning\">⚠️ Fehler: Ungültiges plugin_content_id-Datum: {$record['plugin_content_id']}</div>";
                        continue;
                    }
                    error_log("📄 Rendering PluginPlaintext mit Sprache: " . (self::$language ?? 'de'));
                    PluginPlaintext::render(
                        self::$db,
                        $pluginContentId,
                        $record['page_id'],
                        self::$language ?? 'de',
                        (bool)$record['print_all']
                    );
                    continue;
                }
            }
            error_log("🧩 PluginLoader wird aufgerufen mit Plugin: " . $record['plugin_label'] . ", plugin_content_id: " . $record['plugin_content_id']);
            // Neuer Log-Eintrag für Erfolg bei gesetztem Filter
            if ($filterContentId !== null) {
                error_log("🎯 Lade gezielten plugin_content_id=$filterContentId");
            }
            $loader = new PluginLoader($record['plugin_label']);
            $loader->render();
            if ($filterContentId !== null) {
                break;
            }
        }
    }
    public static function getDb(): mysqli {
        if (!isset(self::$db)) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.inc.php';
            self::$db = getDbConnection();
        }
        return self::$db;
    }
}
class PluginLoader {
    private string $pluginName;
    public array $pluginPaths = [
        '/plugin/',
        '/plugin/admin_plugin/',
        '/plugin/plugin_login/',
        '/plugin/plugin_member/',
        '/plugin/plugin_cards/',
        '/plugin/extra_plugin/',
        '/plugin/plugin_shop/',
    ];
    public function __construct(string $pluginName) {
        $this->pluginName = strtolower($pluginName);
    }
    public function render(): void {
        foreach ($this->pluginPaths as $relativePath) {
            $pluginFile = $_SERVER['DOCUMENT_ROOT'] . $relativePath . 'plugin_' . $this->pluginName . '.php';
            if (file_exists($pluginFile)) {
                include $pluginFile;
                return;
            }
        }
        echo "<section><h2>{$this->pluginName}</h2><p>⚠️ Plugin \"{$this->pluginName}\" nicht gefunden.<br>Pfad geprüft: {$pluginFile}</p></section>";
    }
}