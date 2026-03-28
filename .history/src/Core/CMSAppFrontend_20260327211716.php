<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;
use CMS\Security\Exception\ForbiddenException;
use Twig\Environment;
use CMS\Core\Service\TranslationService;
use CMS\Core\Controller\Layout\LayoutController;
use CMS\Core\Controller\Layout\ContentController;
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

            // Kein Cache für HTML-Seiten
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            $db = CMSApp::getDb();
            $twig->addGlobal('csrf', $_SESSION['_csrf'] ?? '');

            // 1) Sprache bestimmen
            $language = self::detectLanguageFromRequest($db)
                ?? CMSApp::getLanguage()
                ?? 'de';

            // -------------------------------------------------
            // 2) Page-Metadaten laden
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
            // 3) Zugriffsprüfung (Access Check)
            // -------------------------------------------------
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

            // Guard: wirft RuntimeException bei fehlender Berechtigung
            CMSApp::getAccessResolver()->assertPageAccess(
                $pageMeta,
                $roleId
            );

            // -------------------------------------------------
            // 4) Layout laden (Header, Navigation, Footer) zuerst
            // -------------------------------------------------
            $pageContext = trim((string)($pageMeta['context'] ?? ''));
            if ($pageContext === '') {
                throw new RuntimeException('Page-Context fehlt');
            }
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
            // 5) Content-Controller initialisieren und Content laden
            // -------------------------------------------------
            $contentController = new ContentController($db, $language);
            $pagePayload = $contentController->getPageContent($pageId);
            $contentData = $pagePayload['content_data'] ?? [];

            // -------------------------------------------------
            // 6) Content-Rendering datengetrieben
            // -------------------------------------------------
            $contentHtml = '';
            foreach ($contentData as $block) {
                if (!isset($block['type'])) {
                    continue;
                }
                // Dynamisch Render-Methode für Block-Typ aufrufen, falls vorhanden
                $renderMethod = 'render' . str_replace(' ', '', ucwords(str_replace('_', ' ', $block['type'])));
                if (method_exists(__CLASS__, $renderMethod)) {
                    $contentHtml .= self::$renderMethod($block);
                } else {
                    // Fallback: plaintext als Default
                    $contentHtml .= self::renderPlaintext($block);
                }
            }

            // 7) View-Daten für das Template
            $viewData = [
                'body_class' => 'context-' . $pageContext,
                'role_id' => $roleId,
                'language' => $language,
                'form_result' => null,
                'auth' => [
                    'logged_in' => !empty($_SESSION['user_id']),
                    'user_id'   => $_SESSION['user_id'] ?? null,
                    'role_id'   => $roleId,
                ],
                'header'       => $layout['header'] ?? [],
                'navigation'   => $layout['navigation'] ?? [],
                'navId'        => $layout['navId'] ?? ($pageMeta['nav_id'] ?? 'generalNav'),
                'footer'       => $layout['footer'] ?? [],
                'content'      => $contentHtml,
                'page_css_id'  => $pageMeta['page_css_id'] ?? null,
                'asset_path'   => self::getAssetPath(),
                'css_files'    => self::getCssFiles($pageMeta['page_css_id'] ?? null),
                'js_files'     => self::getJsFiles($pageMeta['page_css_id'] ?? null),
                'languages'    => self::loadLanguages($db),
                'js_vars'      => [],
            ];

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

    private static function renderPlaintext(array $block): string
    {
        $html = '<article class="content-block">';
        if (!empty($block['headline'])) {
            $html .= '<h1 class="content-headline">'
                . htmlspecialchars($block['headline'])
                . '</h1>';
        }
        if (!empty($block['text'])) {
            $html .= '<div class="content-text">'
                . $block['text']
                . '</div>';
        }
        $html .= '</article>';
        return $html;
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