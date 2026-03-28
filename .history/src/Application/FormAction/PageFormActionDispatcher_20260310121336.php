<?php
namespace CMS\Application\FormAction;

use RuntimeException;
use CMS\Core\CMSApp;
 
error_log('POST SESSION ID: ' . session_id());
error_log('POST TOKEN: ' . ($_POST['_csrf'] ?? 'NULL'));
error_log('SESSION TOKEN: ' . ($_SESSION['_csrf'] ?? 'NULL'));
final class PageFormActionDispatcher

{

    public static function dispatch(
        string $actionKey,
        array $postData,
        array $pageMeta
    ): array {
        try {
            $pageAction   = trim($actionKey);
            $buttonAction = trim($postData['button_action'] ?? '');

            // 1) CSRF prüfen
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals($_SESSION['_csrf'], $postData['_csrf'])
            ) {
                throw new RuntimeException('Invalid CSRF token');
            }

            // 2) Handler datengetrieben aus form_actions laden
            $db = CMSApp::getDb();

            $stmt = $db->prepare("
                SELECT module, handler_class
                FROM plugin
                WHERE action_key = ?
                  AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param('s', $pageAction);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new RuntimeException('Unbekannte FormAction: ' . $pageAction);
            }

            $baseNamespace = 'CMS\\Application\\FormAction\\';

            $moduleNamespace = ucfirst($row['module']);

            $handlerClass = $baseNamespace
                . $moduleNamespace
                . '\\'
                . $row['handler_class'];
            if (!class_exists($handlerClass)) {
                throw new RuntimeException('Handler nicht gefunden: ' . $handlerClass);
            }

            // 4) ZENTRALE Action-Berechtigung (AccessResolver)
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

            // Action-Metadaten datengetrieben aus DB laden (ui_button + permissions)

            // Action-Metadaten datengetrieben aus DB laden (ui_button + permissions)
            $stmt = $db->prepare(
                'SELECT 
                    b.button_action,
                    p.id AS permission_id
                 FROM ui_button b
                 LEFT JOIN permissions p 
                      ON p.id = b.button_action
                 WHERE b.button_action = ?
                   AND b.enabled = 1
                 LIMIT 1'
            );

            $stmt->bind_param('s', $buttonAction);
            $stmt->execute();

            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new RuntimeException(
                    'Action nicht erlaubt oder Button deaktiviert: ' . $buttonAction
                );
            }

            // Action-Metadaten für AccessResolver
            $actionMeta = [
                'action'        => $row['button_action'],
                'permission_id' => $row['permission_id'] ?? null,
            ];

            $accessResolver = CMSApp::getAccessResolver();
            $accessResolver->assertFormActionAllowed(
                $actionMeta,
                $roleId
            );

            return (new $handlerClass())->handle($postData, $pageMeta);
        } catch (RuntimeException $e) {

            // JSON-Requests (AJAX / API)
            if (self::isJsonRequest()) {
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }

            // HTML-Requests → Redirect mit Flash-Message
            $_SESSION['form_result'] = [
                'success' => false,
                'message' => $e->getMessage(),
            ];

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }
    }

    private static function isJsonRequest(): bool
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        if (!empty($_SERVER['HTTP_ACCEPT'])
            && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return true;
        }

        return false;
    }
}
