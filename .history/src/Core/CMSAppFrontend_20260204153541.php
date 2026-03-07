<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;
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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }


        $db   = CMSApp::getDb();

        // -------------------------------------------------
        // 1) Sprache
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
        // 2.1) Form Action Dispatch (DB-getrieben, zentral)
        // -------------------------------------------------
        error_log('RENDER HIT');
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && !empty($pageMeta['form_action'])
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
        // 3) Context-Definition (ZENTRAL, KEINE IFs)
        // -------------------------------------------------
        $contextConfig = ContextRegistry::get($pageContext);

        // -------------------------------------------------
        // 4) Guards – konsistent & DB-getrieben
        // -------------------------------------------------

        // Öffentlich definierte Seiten (z. B. Login, Register, Info)
        $authVisibility = $pageMeta['auth_visibility'] ?? 'public';
        $isPublicPage = ($authVisibility === 'public');

        // PUBLIC → niemals blockieren
        if ($authVisibility === 'public') {
            // kein Guard, sofort weiter
        } else {

            // GUEST → nur für nicht eingeloggte Nutzer
            if ($authVisibility === 'guest' && !empty($_SESSION['user_id'])) {
                header('Location: /' . $language);
                exit;
            }

            // LOGGED_IN → Login erforderlich
            if ($authVisibility === 'logged_in' && empty($_SESSION['user_id'])) {

                if (self::isApiRequest()) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'error'   => 'Login erforderlich',
                        'code'    => 'unauthenticated',
                    ]);
                    exit;
                }

                $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
                header('Location: /' . $language . '/login');
                exit;
            }

            // Permission Guard (nur wenn explizit gesetzt)
            if (!empty($pageMeta['required_permission_id'])) {

                // Wenn nicht eingeloggt → zuerst Login erzwingen
                if (empty($_SESSION['user_id'])) {

                    if (self::isApiRequest()) {
                        http_response_code(401);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Login erforderlich',
                            'code'    => 'unauthenticated',
                        ]);
                        exit;
                    }

                    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
                    header('Location: /' . $language . '/login');
                    exit;
                }

                // Eingeloggt, aber Permission fehlt → 403
                if (!self::checkPermission($pageMeta['required_permission_id'])) {

                    if (self::isApiRequest()) {
                        http_response_code(403);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'success' => false,
                            'error'   => 'Keine Berechtigung',
                            'code'    => 'forbidden',
                        ]);
                        exit;
                    }

                    http_response_code(403);
                    throw new RuntimeException('Keine Berechtigung');
                }
            }
        }

        // -------------------------------------------------
        // 5) Rolle (nur bei geschütztem Context)
        // -------------------------------------------------
        $roleId = 'guest-role-000';

        if (
            ($contextConfig['requires_login'] === true)
            && !$isPublicPage
        ) {
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';
        }

        // -------------------------------------------------
        // 5) Translation
        // -------------------------------------------------
        $translationService = TranslationService::getInstance($language);
        $twig->addFunction(
            new TwigFunction('t', fn(string $key) => $translationService->translate($key))
        );

        // -------------------------------------------------
        // 6) Content (nach Guards!)
        // -------------------------------------------------
        $contentController = new ContentController($db, $language);
        $pagePayload = $contentController->getPageContent($pageId);
        $contentData = $pagePayload['content_data'] ?? [];

        // -------------------------------------------------
        // 6.1) Content → HTML bauen (EINZIGE Stelle!)
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
                    'form_result' => $_SESSION['form_result'] ?? null
                ]);
            }
        }

        // -------------------------------------------------
        // 7) Layout (Context + Rolle entscheiden alles)
        // -------------------------------------------------
        $layoutController = new LayoutController(
            $language,
            $roleId,
            $pageMeta['nav_id'] ?? 'generalNav',
            $pageContext
        );
        $layout = $layoutController->getLayout();

        // -------------------------------------------------
        // 8) View-Daten
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
            'page_css_id'  => $pageMeta['page_css_id'] ?? 'frontend',
            'asset_path'   => self::getAssetPath(),
            'css_files'    => self::getCssFiles($pageMeta['page_css_id'] ?? null),
            'js_files'     => self::getJsFiles($pageContext),
            'languages'    => self::loadLanguages($db),
        ];

        // Flash-Daten nur einmal verwenden
        unset($_SESSION['form_result']);

        return $twig->render('layout/base.twig', $viewData);
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

    private static function getJsFiles(string $context): array
    {
        // JS strikt nach Area
        if ($context === 'frontend') {
            return ['language_selector.js', ];
        }

        // backend
        return ['language_selector.js',];
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
        // Dummy permission check, implement as needed
        // For now, deny all
        return false;
    }
}