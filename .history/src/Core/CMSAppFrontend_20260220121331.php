<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;
use CMS\Security\Exception\ForbiddenException;
use Twig\Environment;
use Twig\TwigFunction;
use CMS\Core\Service\TranslationService;
use CMS\Core\Controller\Layout\ContentController;
use CMS\Core\Controller\Layout\LayoutController;
use CMS\Core\Context\ContextRegistry;
use CMS\Application\FormAction\PageFormActionDispatcher;
final class CMSAppFrontend
{
    /**
     * EINZIGER Render-Einstiegspunkt
     */
  public static function render(
    Environment $twig,
    string $pageId
): string {
    try {
        error_log('CMSAppFrontend::render CALLED FOR PAGE: ' . $pageId);

    // 🔐 Kein Cache für HTML-Seiten
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    $db = CMSApp::getDb();
    $twig->addGlobal('csrf', $_SESSION['_csrf'] ?? '');
    // -------------------------------------------------
    // 1) Sprache (zentral, einmalig)
    // -------------------------------------------------
    $language = self::detectLanguageFromRequest($db)
        ?? CMSApp::getLanguage()
        ?? 'de';

        // -------------------------------------------------
        // 2) Page-Metadaten (Single Source of Truth)
        // -------------------------------------------------
        $stmt = $db->prepare(
            "SELECT
                page_uuid,
                context,
                enabled,
                auth_visibility,
                required_permission_id,
                nav_id,
                page_css_id,
                form_action
            FROM page
            WHERE page_uuid = ?
            LIMIT 1"
        );
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $pageMeta = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        if (!$pageMeta) {
            throw new RuntimeException('Seite nicht gefunden');
        }

        if ((int)$pageMeta['enabled'] !== 1) {
            throw new RuntimeException('Seite deaktiviert');
        }

        // -------------------------------------------------
        // 3) ZENTRALER ACCESS CHECK (DB-getrieben)
        // -------------------------------------------------
        $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // Guard: wirft RuntimeException bei fehlender Berechtigung
        CMSApp::getAccessResolver()->assertPageAccess(
            $pageMeta,
            $roleId
        );

// -------------------------------------------------
// 4) Form Action Dispatch (DB-getrieben, zentral)
// -------------------------------------------------
error_log('RENDER HIT');
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($pageMeta['form_action'])
    && isset($_POST['action'])
) {
    $result = PageFormActionDispatcher::dispatch(
        $pageMeta['form_action'],
        $_POST,
        $pageMeta
    );

    // Einheitliches Ergebnisformat auswerten
    $_SESSION['form_result'] = $result;

    // Redirect sofort ausführen (POST-Redirect-GET)
    if (!empty($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }
}

$pageContext = trim((string)($pageMeta['context'] ?? ''));
        if ($pageContext === '') {
            throw new RuntimeException('Page-Context fehlt');
        }

        // -------------------------------------------------
        // 5) Context-Definition (ZENTRAL, KEINE IFs)
        // -------------------------------------------------
        $contextConfig = ContextRegistry::get($pageContext);

        // -------------------------------------------------
        // 6) Rolle (Context-basiert, KEINE Public-Sonderlogik)
        // -------------------------------------------------
        $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // -------------------------------------------------
        // 7) Translation
        // -------------------------------------------------
        $translationService = TranslationService::getInstance($language);
        $twig->addFunction(
            new TwigFunction('t', fn(string $key) => $translationService->translate($key))
        );

        // -------------------------------------------------
        // 8) Content (nach Guards!)
        // -------------------------------------------------
        $contentController = new ContentController($db, $language);
        $pagePayload = $contentController->getPageContent($pageId);
        $contentData = $pagePayload['content_data'] ?? [];

        // -------------------------------------------------
        // 9) Content → HTML bauen (EINZIGE Stelle!)
        // -------------------------------------------------
        $contentHtml = '';

        foreach ($contentData as $block) {
            if (!isset($block['type'])) {
                continue;
            }

            // PLAINTEXT = internes CMS-Plugin → DIREKT HTML
            if ($block['type'] === 'plaintext') {
                $contentHtml .= '<article class="content-block">';

                if (!empty($block['headline'])) {
                    $contentHtml .= '<h1 class="content-headline">'
                        . htmlspecialchars($block['headline'])
                        . '</h1>';
                }

                if (!empty($block['text'])) {
                    $contentHtml .= '<div class="content-text">'
                        . $block['text']
                        . '</div>';
                }

                $contentHtml .= '</article>';
                continue;
            }

            // ALLE ANDEREN PLUGINS → Twig-Templates (z. B. forms)
            $tpl = 'pages/' . $block['type'] . '.twig';
            if ($twig->getLoader()->exists($tpl)) {
                $contentHtml .= $twig->render($tpl, [
                    'block'       => $block,
                    'buttons'     => $block['buttons'] ?? [],
                    'form_result' => $_SESSION['form_result'] ?? null
                ]);
            }
        }

        // -------------------------------------------------
        // 10) Layout (Context + Rolle entscheiden alles)
        // -------------------------------------------------
        $navId = $pageContext === 'backend'
             ? 'adminNav'
            : ($pageMeta['nav_id'] ?? 'generalNav');

        $layoutController = new LayoutController(
            $language,
            $roleId,
            $navId,
            $pageContext
        );
        $layout = $layoutController->getLayout();

        // -------------------------------------------------
        // Prepare jsVars for frontend
        // -------------------------------------------------
        $jsVars = [];

        $formResult = $_SESSION['form_result'] ?? null;

        if (
            isset($formResult['errors'])
            && is_array($formResult['errors'])
            && in_array('Zustimmung erforderlich', $formResult['errors'], true)
        ) {
            $jsVars['requireLegalAcceptance'] = true;
        }

        // -------------------------------------------------
        // 11) View-Daten
        // -------------------------------------------------
        $viewData = [
            // CSS wird AUSSCHLIESSLICH über context-* gesteuert
            'body_class' => 'context-' . $pageContext,
            // Rolle nur für Backend-Logik (nicht für CSS)
            'role_id' => $roleId,
            'language' => $language,
            'form_result' => $_SESSION['form_result'] ?? null,
            'auth' => [
                'logged_in' => !empty($_SESSION['user_id']),
                'user_id'   => $_SESSION['user_id'] ?? null,
                'role_id'   => $roleId,
            ],
            'header'       => $layout['header'] ?? [],
            'navigation'   => $layout['navigation'] ?? [],
            'navId'         => $layout['navId'] ?? ($pageMeta['nav_id'] ?? 'generalNav'),
            'footer'       => $layout['footer'] ?? [],
            'content' => $contentHtml,
            'page_css_id' => $pageMeta['page_css_id'] ?? null,
            'asset_path'  => self::getAssetPath(),
            'css_files'   => self::getCssFiles($pageMeta['page_css_id'] ?? null),
            'js_files'     => self::getJsFiles($pageMeta['page_css_id'] ?? null),
            'languages'    => self::loadLanguages($db),
            'js_vars'      => $jsVars,
        ];

        // Flash-Daten nur einmal verwenden
        unset($_SESSION['form_result']);

        error_log('CMSAppFrontend::render FINISHED FOR PAGE: ' . $pageId);
        return $twig->render('layout/base.twig', $viewData);

    } catch (ForbiddenException $e) {

        http_response_code(403);

        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            return json_encode([
                'error' => 'Forbidden',
                'message' => $e->getMessage(),
            ]);
        }

        return $twig->render('errors/403.twig');

    } catch (RuntimeException $e) {

        http_response_code(404);

        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            return json_encode([
                'error' => 'Not Found',
                'message' => $e->getMessage(),
            ]);
        }

        return $twig->render('errors/404.twig');

    }

    }

    private static function isApiRequest(): bool
    {
        // fetch() & AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        // JSON clients
        if (!empty($_SERVER['HTTP_ACCEPT'])
            && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return true;
        }

        // Explicit API routes (optional safety)
        if (!empty($_SERVER['REQUEST_URI'])
            && str_starts_with($_SERVER['REQUEST_URI'], '/api')) {
            return true;
        }

        return false;
    }

    private static function detectLanguageFromRequest(\mysqli $db): ?string
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return null;
        }
        $parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        $lang  = $parts[0] ?? null;
        if (!$lang) {
            return null;
        }
        $stmt = $db->prepare("SELECT id FROM trans_language WHERE id = ? LIMIT 1");
        $stmt->bind_param('s', $lang);
        $stmt->execute();
        $ok = $stmt->get_result()?->num_rows > 0;
        $stmt->close();
        return $ok ? $lang : null;
    }

    private static function getAssetPath(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $proto = $https ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $proto . '://' . $host;
    }

    private static function getCssFiles(?string $pageCssId = null): array
    {
        $css = [
            'style.css',
            'language_selector.css',
            'navi.css',
        ];

        // page_css_id ist ein logischer Name (z. B. "login", "form-box", "admin")
        // Dateinamen-Regel ist ZENTRAL hier definiert
        if ($pageCssId) {
            $css[] = 'page-' . $pageCssId . '.css';
        }

        return $css;
    }
 


    private static function getJsFiles(?string $pageJsId = null): array
    {
        // globale JS-Dateien
        $js = [
            'language_selector.js',
        ];

        // seitenabhängige JS-Datei (analog zu CSS)
        if ($pageJsId) {
            $js[] = 'page-' . $pageJsId . '.js';
        }

        return $js;
    }

    private static function loadLanguages(\mysqli $db): array
    {
        $out = [];
        $res = $db->query("SELECT id, label, flag_path FROM trans_language ORDER BY label");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private static function checkPermission(string $permissionId): bool
    {
        // Gast-Fallback
        $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // ZENTRALER RoleService aus der CMSApp
        $roleService = CMSApp::getRoleService();

        return $roleService->roleHasPermission($roleId, $permissionId);
    }
}