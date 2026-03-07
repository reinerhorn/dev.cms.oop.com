<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;
use CMS\Core\Service\TranslationService;
use CMS\Core\Controller\ContentController;
use CMS\Core\Controller\LayoutController;
use Twig\Environment;
use Twig\TwigFunction;

final class CMSAppFrontend
{
    public static function render(
        Environment $twig,
        ?string $pageId = null,
        ?array $contentData = []
    ): string {

        // --------------------------------------------
        // Session sicherstellen
        // --------------------------------------------
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = CMSApp::getDb();

        // --------------------------------------------
        // Sprache bestimmen
        // --------------------------------------------
        $language = self::detectLanguageFromRequest($db)
            ?? CMSApp::getLanguage()
            ?? 'de';

        // --------------------------------------------
        // PageId → UUID (nur Mapping, kein Content!)
        // --------------------------------------------
        if ($pageId !== null && !preg_match('/^[0-9a-fA-F\-]{36}$/', $pageId)) {
            $stmt = $db->prepare(
                "SELECT page_uuid, context, required_permission_id, enabled
                 FROM page
                 WHERE slug = ?
                 LIMIT 1"
            );
            $stmt->bind_param('s', $pageId);
            $stmt->execute();
            $meta = $stmt->get_result()?->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                throw new \RuntimeException('Seite nicht gefunden');
            }

            $pageId = $meta['page_uuid'];
        } else {
            // UUID direkt → Meta laden
            $stmt = $db->prepare(
                "SELECT page_uuid, context, required_permission_id, enabled
                 FROM page
                 WHERE page_uuid = ?
                 LIMIT 1"
            );
            $stmt->bind_param('s', $pageId);
            $stmt->execute();
            $meta = $stmt->get_result()?->fetch_assoc();
            $stmt->close();

            if (!$meta) {
                throw new \RuntimeException('Seite nicht gefunden');
            }
        }

        // --------------------------------------------
        // PAGE ENABLED GUARD
        // --------------------------------------------
        if ((int)$meta['enabled'] !== 1) {
            throw new \RuntimeException('Seite deaktiviert');
        }

        $pageContext = $meta['context'] ?? 'frontend';
        $requiredPermissionId = $meta['required_permission_id'] ?? null;

        if (!in_array($pageContext, ['frontend', 'admin'], true)) {
            throw new \RuntimeException('Ungültiger Page-Context');
        }

        // --------------------------------------------
        // 🔐 HARTER ACCESS-GUARD (VOR ALLEM!)
        // --------------------------------------------
        $auth = new AuthService();

        if ($pageContext === 'admin') {

            // Nicht eingeloggt → IMMER raus
            if (!$auth->isLoggedIn()) {
                header('Location: /' . $language . '/startseite');
                exit;
            }

            // Permission fehlt → 403
            if ($requiredPermissionId && !$auth->hasPermission($requiredPermissionId)) {
                http_response_code(403);
                throw new \RuntimeException('Zugriff verweigert');
            }
        }

        // --------------------------------------------
        // Translation
        // --------------------------------------------
        $translationService = TranslationService::getInstance($language);
        $twig->addFunction(
            new TwigFunction('t', fn(string $key) =>
                $translationService->translate($key)
            )
        );

        // --------------------------------------------
        // Content laden (JETZT ERST!)
        // --------------------------------------------
        $contentController = new ContentController($db, $language);
        $pagePayload = $contentController->getPageContent($pageId);

        if (empty($pagePayload)) {
            throw new \RuntimeException('Seite nicht gefunden');
        }

        $contentDataToRender =
            $contentData['content_data']
            ?? $pagePayload['content_data']
            ?? [];

        foreach ($contentDataToRender as &$block) {
            foreach (['headline', 'title', 'label'] as $field) {
                if (isset($block[$field]) && is_string($block[$field])) {
                    $block[$field] = $translationService->translate($block[$field]);
                }
            }
        }
        unset($block);

        // --------------------------------------------
        // Rolle / Navigation
        // --------------------------------------------
        $roleId = $_SESSION['role_id'] ?? null;
        $effectiveRoleId = $roleId ?? 'guest-role-000';

        $navRole = match (true) {
            is_string($roleId) && str_starts_with($roleId, 'admin-')  => 'admin',
            is_string($roleId) && str_starts_with($roleId, 'member-') => 'member',
            default                                                   => 'guest',
        };

        $navId = match ($navRole) {
            'admin'  => 'adminNav',
            'member' => 'memberNav',
            default  => 'generalNav',
        };

        // --------------------------------------------
        // Layout
        // --------------------------------------------
        $layoutController = new LayoutController(
            $language,
            $effectiveRoleId,
            $pageContext
        );
        $layout = $layoutController->getLayout();

        // --------------------------------------------
        // View-Daten
        // --------------------------------------------
        return $twig->render('layout/base.twig', [
            'language' => $language,
            'auth' => [
                'logged_in' => $auth->isLoggedIn(),
                'user_id'   => $_SESSION['user_id'] ?? null,
                'role_id'   => $roleId ?? 'guest-role-000',
            ],
            'asset_path'   => self::getAssetPath(),
            'css_files'    => self::getCssFiles(),
            'header'       => $layout['header'] ?? [],
            'navigation'   => $layout['navigation'] ?? [],
            'navRole'      => $navRole,
            'navId'        => $navId,
            'footer'       => $layout['footer'] ?? [],
            'languages'    => self::loadLanguages($db),
            'content_data' => $contentDataToRender,
            'debug'        => (getenv('APP_DEBUG') === '1'),
        ]);
    }

    // ... restliche Methoden (detectLanguageFromRequest, getAssetPath, getCssFiles, loadLanguages) bleiben unverändert ...
}