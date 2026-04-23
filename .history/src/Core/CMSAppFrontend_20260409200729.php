<?php
 
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;
use CMS\Security\Exception\ForbiddenException;
use Twig\Environment;
use CMS\Core\Controller\Layout\LayoutController;
use CMS\Core\Controller\Layout\ContentController;

final class CMSAppFrontend
{
    public static function render(Environment $twig, string $pageId): string
    {
        try {
            
            error_log('CMSAppFrontend::render CALLED FOR PAGE: ' . $pageId);

            $db = CMSApp::getDb();
            $twig->addGlobal('csrf', $_SESSION['_csrf'] ?? '');

            // 1) Sprache
            $language = self::detectLanguageFromRequest($db) ?? CMSApp::getLanguage() ?? 'de';

            // 🔥 Page-Metadaten (MUSS vor POST Handling geladen werden)
            $stmt = $db->prepare("
                SELECT page_uuid, context, enabled, auth_visibility,
                       required_permission_id, nav_id, page_css_id, form_action
                FROM page
                WHERE page_uuid = ?
                LIMIT 1
            ");
            $stmt->bind_param('s', $pageId);
            $stmt->execute();
            $pageMeta = $stmt->get_result()?->fetch_assoc();
            $stmt->close();

            if (!$pageMeta || (int)$pageMeta['enabled'] !== 1) {
                throw new RuntimeException('Seite nicht gefunden oder deaktiviert');
            }

            // 🚨 POST DISPATCHER (CMSFrontend darf POST nicht selbst behandeln)
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

                $action = $_POST['action'] ?? null;

                if ($action) {
                    $dispatcher = CMSApp::getFormDispatcher();

                    $formResult = $dispatcher->dispatch($_POST, $pageMeta);

                    $_SESSION['form_result'] = $formResult;

                    $uri = $_SERVER['REQUEST_URI'] ?? '/';
                    header('Location: ' . $uri);
                    exit;
                }
            }

            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            // 3) Access Check
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';
            CMSApp::getAccessResolver()->assertPageAccess($pageMeta, $roleId);

            $pageContext = trim((string)($pageMeta['context'] ?? ''));
            if ($pageContext === '') {
                throw new RuntimeException('Page-Context fehlt');
            }

            $navId = $pageContext === 'backend'
                ? 'adminNav'
                : ($pageMeta['nav_id'] ?? 'generalNav');

            // 4) Layout
            $layoutController = new LayoutController($language, $roleId, $navId, $pageContext);
            $layout = $layoutController->getLayout();

            // 5) Content
            $contentController = new ContentController($db, $language);
            $pagePayload = $contentController->getPageContent($pageId);

            $contentData = $pagePayload['content_data'] ?? [];

            error_log('CONTENT DATA: ' . print_r($contentData, true));

            // ✅ ONLY PLUGIN PIPELINE (NO FALLBACK ANYMORE)
            $contentHtml = '';
            error_log('BLOCK LOOP START');

            foreach ($contentData as $block) {
                error_log('BLOCK DATA: ' . print_r($block, true));
                if (!isset($block['type'])) {
                    continue;
                }
                $template = 'blocks/' . $block['type'] . '.twig';

                if (!$twig->getLoader()->exists($template)) {
                    error_log('❌ Missing block template: ' . $template);
                    continue;
                }

                try {
                    $contentHtml .= $twig->render($template, [
                        'block' => $block,
                        'csrf'  => $_SESSION['_csrf'] ?? ''
                    ]);
                } catch (\Throwable $e) {
                    error_log('PLUGIN RENDER ERROR: ' . $e->getMessage());
                    continue;
                }
            }
            error_log('RENDERED HTML FINAL: ' . $contentHtml);

            // 🔥 PRG: Session-FormResult auswerten + Redirect
            $formResult = $_SESSION['form_result'] ?? null;

            if (!empty($formResult) && !empty($formResult['redirect'])) {
                unset($_SESSION['form_result']);
                session_write_close();
                header('Location: ' . $formResult['redirect']);
                exit;
            }

            // 7) View
            $viewData = [
                'body_class'   => 'context-' . $pageContext,
                'role_id'      => $roleId,
                'language'     => $language,
                'form_result'  => $formResult,
                'auth'         => [
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

            unset($_SESSION['form_result']);

            return $twig->render('layout/base.twig', $viewData);
        } catch (ForbiddenException $e) {
            http_response_code(403);
            return $twig->render('errors/403.twig');
        } catch (RuntimeException $e) {
            http_response_code(404);
            return $twig->render('errors/404.twig');
        }
    }

    private static function resolveFormHandler(string $formId): ?string
    {
        $db = CMSApp::getDb();

        $stmt = $db->prepare("
            SELECT handler_class
            FROM plugin
            WHERE name = ?
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->bind_param('s', $formId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res || empty($res['handler_class'])) {
            error_log('❌ Kein handler_class gefunden für form_id: ' . $formId);
            return null;
        }

        return $res['handler_class'];
    }

    private static function detectLanguageFromRequest(\mysqli $db): ?string
    {
        $parts = explode('/', trim($_SERVER['REQUEST_URI'] ?? '', '/'));
        $lang = $parts[0] ?? null;

        if (!$lang) return null;

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
        $css = ['style.css', 'language_selector.css', 'navi.css'];
        if ($pageCssId) $css[] = 'page-' . $pageCssId . '.css';
        return $css;
    }

    private static function getJsFiles(?string $pageJsId = null): array
    {
        $js = ['language_selector.js'];
        if ($pageJsId) $js[] = 'page-' . $pageJsId . '.js';
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
}
