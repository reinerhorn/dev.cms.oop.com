<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/class/navi/navi.inc.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/security/UserRoleManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/dev/DebugHelper.php';

class AccessControl {
    public static function userHasRoleId(string $expectedRoleId): bool {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $db = CMSAppFrontend::getDb();
        $stmt = $db->prepare("SELECT role_id FROM login_users WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row && $row['role_id'] === $expectedRoleId;
    }
}
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
            // Debug-Schalter direkt vor dem return ins Header-HTML einfügen
            if (AccessControl::userHasRoleId('admin-role-001')) {
                $debugStatus = \DebugHelper::$enabled ? 'on' : 'off';
                $toggleLink = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['debug' => \DebugHelper::$enabled ? 'off' : 'on']));
                $html .= "<div class='debug-toggle'>
                    🛠️ Debug: <strong>$debugStatus</strong>
                    <a href=\"$toggleLink\">[umschalten]</a>
                </div>";
            }
            return $html;
        }
        error_log("❌ Kein passender Header gefunden für Sprache $language und Rolle $role");
        error_log("ℹ️ Fallback-Header wird angezeigt.");
        $html = '<div class="header">
    <a title="Home" href="/"><img class="logo" src="/images/hd-logo.webp" alt="logo"></a>
    <a class="companyname" title="Home" href="/">HD Staffing Services</a>'
    . self::getLanguageSelectorHtml($_SESSION['language'] ?? self::$language) .
    '</div>';
        // Debug-Schalter auch im Fallback-Header ausgeben
        if (AccessControl::userHasRoleId('admin-role-001')) {
            $debugStatus = \DebugHelper::$enabled ? 'on' : 'off';
            $toggleLink = $_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['debug' => \DebugHelper::$enabled ? 'off' : 'on']));
            $html .= "<div class='debug-toggle'>
                🛠️ Debug: <strong>$debugStatus</strong>
                <a href=\"$toggleLink\">[umschalten]</a>
            </div>";
        }
        return $html;
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
        DebugHelper::log("renderMain() gestartet mit page = " . ($_REQUEST['page'] ?? 'nicht gesetzt'));
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
        // Header ganz oben ausgeben
        echo self::getHeaderHtml();
        // Debug-Schalter ist jetzt im Header-HTML enthalten
        echo self::getNavigationHtml(
            self::getDb(),
            self::$language,
            $_REQUEST['page'] ?? null
        );
        self::renderMain();
        echo self::getFooterHtml();
        // Admin-Plugins dynamisch laden
        // (Debug-Switch Plugin wird nicht mehr hier geladen, da direkt im Header ausgegeben)
        if (!empty($_SESSION['admin_a']) && $_SESSION['admin_a'] == 1) {
            $adminPlugins = []; // Weitere Plugins hier ergänzen
            foreach ($adminPlugins as $pluginName) {
                $path = $_SERVER['DOCUMENT_ROOT'] . "/plugin/plugin_{$pluginName}.php";
                if (file_exists($path)) {
                    include_once $path;
                }
            }
        }
       
    }
    public static function run(): void {
        self::init();
        self::render();
    }
    public static function init(): void {
        // Debugging über URL aktivieren/deaktivieren
        if (isset($_GET['debug']) && $_GET['debug'] === 'on') {
            DebugHelper::enable();
        } else {
            DebugHelper::disable();
        }
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
        // Plugin Debug Switch wird nicht mehr automatisch geladen, da im Header-HTML enthalten
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

        // pageId sofort nach der Prüfung initialisieren
        $pageId = filter_var($_REQUEST['page'], FILTER_VALIDATE_INT);

        // SQL-Abfrage ohne LEFT JOINs für Karten-Plugins
        $stmt = self::$db->prepare("
            SELECT 
                *, 
                plugin.name AS plugin_label, 
                UNIX_TIMESTAMP(page.id) AS page_id, 
                page.id AS page_raw_id 
            FROM page_config 
            JOIN page ON page_config.fk_page_id = page.id 
            JOIN plugin ON page_config.fk_plugin_id = plugin.id 
            LEFT JOIN p_content_plaintext ON (
                plugin.name = 'plaintext' AND 
                page_config.plugin_content_id = p_content_plaintext.id
            )
            WHERE UNIX_TIMESTAMP(page.id) = ?
              AND (
                  (plugin.name != 'plaintext' AND plugin.name != 'card') OR
                  (plugin.name = 'plaintext' AND p_content_plaintext.fk_language_id = ?)
              )
            ORDER BY page_config.idx ASC
        ");
        $language = self::$language ?? 'de';
        $stmt->bind_param('is', $pageId, $language);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            DebugHelper::log("⚠️ Keine Einträge für page_id = $pageId in page_config gefunden", 'orange');
        } else {
            DebugHelper::log("✅ page_config Trefferanzahl: " . $result->num_rows, 'limegreen');
        }
        $page_output_all = [];

        while ($record = $result->fetch_assoc()) {
            DebugHelper::log("Plugin: {$record['plugin_label']}, Content-ID: {$record['plugin_content_id']}", 'deepskyblue');
            if (DebugHelper::$enabled) {
                echo "<pre style='background:#111;color:#0f0;padding:10px;'>";
                echo "▶️ page_id: " . htmlspecialchars($record['page_id']) . "\n";
                echo "▶️ plugin_label: " . htmlspecialchars($record['plugin_label']) . "\n";
                echo "▶️ plugin_content_id: " . htmlspecialchars($record['plugin_content_id']) . "\n";
                echo "▶️ print_all: " . htmlspecialchars($record['print_all']) . "\n";
                echo "</pre>";
            }

            $pluginContentId = $record['plugin_content_id'];
            $pluginLabel = $record['plugin_label'];
            $currentPageId = $record['page_id'];
            $language = self::$language ?? 'de';
            $printAll = (bool)$record['print_all'];

            if ($pluginLabel === 'plaintext') {
                error_log("🔍 PLAINTEXT Plugin erkannt. Content-ID: " . $pluginContentId);
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
                    PluginPlaintext::render(
                        self::$db,
                        $pluginContentId,
                        $currentPageId,
                        $language,
                        $printAll
                    );
                    continue;
                }
            }

            error_log("🧩 PluginLoader wird aufgerufen mit Plugin: $pluginLabel, plugin_content_id: $pluginContentId");
            $loader = new PluginLoader($pluginLabel);
            $loader->render();
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
        '/plugin/formulare/',
    ];
    public function __construct(string $pluginName) {
        $this->pluginName = $pluginName;
    }
    public function render(): void {
        foreach ($this->pluginPaths as $relativePath) {
            $pluginFile = $_SERVER['DOCUMENT_ROOT'] . $relativePath . 'plugin_' . $this->pluginName . '.php';
            if (file_exists($pluginFile)) {
                include $pluginFile;
                return;
            }
        }
        echo "<section><h2>{$this->pluginName}</h2><p>⚠️ Plugin \"{$this->pluginName}\" nicht gefunden.</p></section>";
    }
    public static function includeDebugSwitch(): void {
        if (!empty($_SESSION['admin_a']) && $_SESSION['admin_a'] == 1) {
            include_once $_SERVER['DOCUMENT_ROOT'] . "/plugin/plugin_debug_switch.php";
        }
    }
}