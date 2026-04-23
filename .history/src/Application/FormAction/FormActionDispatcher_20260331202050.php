<?php
namespace CMS\Application\FormAction;

use RuntimeException;
use mysqli;
use CMS\Security\AccessResolver;
 
error_log('POST SESSION ID: ' . session_id());
error_log('POST TOKEN: ' . ($_POST['_csrf'] ?? 'NULL'));
error_log('SESSION TOKEN: ' . ($_SESSION['_csrf'] ?? 'NULL'));
final class FormActionDispatcher
{
    private mysqli $db;
    private AccessResolver $accessResolver;

    public function __construct(mysqli $db, AccessResolver $accessResolver)
    {
        $this->db = $db;
        $this->accessResolver = $accessResolver;
    }

    public function dispatch(array $postData, array $pageMeta): array {
        try {
            $pageAction = trim($postData['action'] ?? ($pageMeta['form_action'] ?? ''));
            $buttonAction = trim($postData['action'] ?? '');

            // 1) CSRF prüfen
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals($_SESSION['_csrf'], $postData['_csrf'])
            ) {
                throw new RuntimeException('Invalid CSRF token');
            }

            // 2) Handler datengetrieben aus form_actions laden
            $db = $this->db;

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

            // 1) Button-Daten holen (Action muss existieren & aktiv sein)
            $stmt = $db->prepare(
                'SELECT action FROM ui_button WHERE action = ? AND enabled = 1 LIMIT 1'
            );
            $stmt->bind_param('s', $buttonAction);
            $stmt->execute();
            $buttonResult = $stmt->get_result()->fetch_assoc();

            if (!$buttonResult) {
                throw new RuntimeException('Action nicht erlaubt oder Button deaktiviert: ' . $buttonAction);
            }

            // 2) Permission zur Action auflösen
            // Regel: Existiert KEINE Permission → Action ist public
            $stmt = $db->prepare(
                'SELECT id FROM permissions WHERE id = ? LIMIT 1'
            );
            $stmt->bind_param('s', $buttonAction);
            $stmt->execute();
            $permRow = $stmt->get_result()->fetch_assoc();

            // 3) Action-Metadaten (ohne is_public, rein datengetrieben)
            $actionMeta = [
                'action'        => $buttonAction,
                'permission_id' => $permRow['id'] ?? null,
            ];

            $this->accessResolver->assertFormActionAllowed(
                $actionMeta,
                $roleId
            );

            $result = (new $handlerClass())->handle($postData, $pageMeta);

            // AJAX / API Requests bleiben unverändert
            if (self::isJsonRequest()) {
                return $result;
            }

            // HTML Requests: POST Ownership wird hier abgeschlossen
            $_SESSION['form_result'] = $result;

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
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
