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
use CMS\Application\FormAction\FormDataLoaderResolver;

ini_set('display_errors', 1);
error_reporting(E_ALL);

final class CMSAppFrontend
{
 
    /**
     * Routing-Helfer: Slug → UUID → render()
     */
    public static function renderBySlug(
        Environment $twig,
        string $slug,
        string $language
    ): string {
        error_log('SLUG RECEIVED: ' . $slug);
        error_log('LANG RECEIVED: ' . $language);
        $db = CMSApp::getDb();
        CMSApp::setLanguage($language);

        $stmt = $db->prepare("
            SELECT page_uuid
            FROM page
            WHERE slug = ? AND enabled = 1
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException('Slug-Query fehlgeschlagen');
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();

        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['page_uuid'])) {
            error_log('SLUG NOT FOUND IN DB: ' . $slug);
            throw new RuntimeException('Seite nicht gefunden (Slug): ' . $slug);
        }

        // Übergabe an bestehende render()-Logik
        return self::render($twig, $row['page_uuid']);
    }
    /**
     * EINZIGER Render-Einstiegspunkt
     */
    public static function render(
        Environment $twig,
        string $pageId
    ): string {
        try {
            error_log('CMSAppFrontend::render CALLED FOR PAGE: ' . $pageId);

            // Kein Cache für HTML-Seiten
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            $db = CMSApp::getDb();
            $twig->addGlobal('csrf', $_SESSION['_csrf'] ?? '');

            // Sprache
            $language = self::detectLanguageFromRequest($db)
                ?? CMSApp::getLanguage()
                ?? 'de';

            // Page-Metadaten
            $stmt = $db->prepare(
                "SELECT
                    page_uuid,
                    context,
                    enabled,
                    auth_visibility,
                    required_permission_id,
                    nav_id,
                    page_css_id,
                    form_action,
                    meta_title,
                    meta_description,
                    meta_keywords
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

            // Access Check
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';
            CMSApp::getAccessResolver()->assertPageAccess($pageMeta, $roleId);

            // Form Action Dispatch (POST)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!empty($_POST['form_id']) && !empty($pageMeta['form_action'])) {
                    $result = PageFormActionDispatcher::dispatch(
                        $pageMeta['form_action'],
                        $_POST,
                        $pageMeta
                    );
                    $_SESSION['form_result'] = $result;
                    if (!empty($result['redirect'])) {
                        header('Location: ' . $result['redirect']);
                        exit;
                    }
                }
            }

            $pageContext = trim((string)($pageMeta['context'] ?? ''));
            if ($pageContext === '') {
                throw new RuntimeException('Page-Context fehlt');
            }

            // Context-Definition
            $contextConfig = ContextRegistry::get($pageContext);

            // Translation
            $translationService = TranslationService::getInstance($language);
            $twig->addFunction(
                new TwigFunction('t', fn(string $key) => $translationService->translate($key))
            );

            // Content Controller: Get prepared content data and rendered HTML
            $contentController = new ContentController($db, $language);

            // FormData Hydration moved to ContentController
            if (!empty($pageMeta['form_action'])) {
                $loader = FormDataLoaderResolver::resolve($pageMeta['form_action']);
                if ($loader !== null) {
                    $contentController->setFormDataLoader($loader);
                }
            }

            $pagePayload = $contentController->getPageContent($pageId);
            $contentData = $pagePayload['content_data'] ?? [];
            $contentHtml = $contentController->renderContentBlocks($contentData, $_SESSION['form_result'] ?? null);

            // Layout (Header, Nav, Footer)
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

            // JS Vars
            $jsVars = [];
            $formResult = $_SESSION['form_result'] ?? null;
            if (
                isset($formResult['errors'])
                && is_array($formResult['errors'])
                && in_array('Zustimmung erforderlich', $formResult['errors'], true)
            ) {
                $jsVars['requireLegalAcceptance'] = true;
            }

            // Meta-Tags
            $meta = [
                'title' => $pageMeta['meta_title'] ?? '',
                'description' => $pageMeta['meta_description'] ?? '',
                'keywords' => $pageMeta['meta_keywords'] ?? '',
            ];

            // View-Daten für base.twig
            $viewData = [
                'meta'        => $meta,
                'body_class'  => 'context-' . $pageContext,
                'role_id'     => $roleId,
                'language'    => $language,
                'form_result' => $_SESSION['form_result'] ?? null,
                'auth' => [
                    'logged_in' => !empty($_SESSION['user_id']),
                    'user_id'   => $_SESSION['user_id'] ?? null,
                    'role_id'   => $roleId,
                ],
                'header'      => $layout['header'] ?? [],
                'navigation'  => $layout['navigation'] ?? [],
                'navId'       => $layout['navId'] ?? ($pageMeta['nav_id'] ?? 'generalNav'),
                'footer'      => $layout['footer'] ?? [],
                'content'     => $contentHtml,
                'page_css_id' => $pageMeta['page_css_id'] ?? null,
                'asset_path'  => self::getAssetPath(),
                'css_files'   => self::getCssFiles($pageMeta['page_css_id'] ?? null),
                'js_files'    => self::getJsFiles($pageMeta['page_css_id'] ?? null),
                'languages'   => self::loadLanguages($db),
                'js_vars'     => $jsVars,
            ];

            // Flash-Daten nur einmal verwenden
            unset($_SESSION['form_result']);

            error_log('CMSAppFrontend::render FINISHED FOR PAGE: ' . $pageId);
            error_log('BODY CLASS: ' . $viewData['body_class']);
            error_log('CONTENT LENGTH: ' . strlen($contentHtml));
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