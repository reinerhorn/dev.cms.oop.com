<?php
namespace CMS\Application\FormAction;

use RuntimeException;
use CMS\Core\CMSApp;

final class PageFormActionDispatcher

{
   private const MAP = [
   // 'auth_login_test' => \CMS\Application\FormAction\Auth\TestLoginHandler::class,
    //    'auth_login'    => Auth\LoginHandler::class,
      //  'auth_register' => Auth\RegisterHandler::class,
      //  'contact_send'  => Contact\ContactHandler::class,
 
    'auth_login'    => \CMS\Application\FormAction\Auth\LoginHandler::class,
    'auth_register' => \CMS\Application\FormAction\Auth\RegisterHandler::class,
    'contact_send'  => \CMS\Application\FormAction\Contact\ContactHandler::class,
 

];

    private const ACTION_ROLES = [
        // Auth
        'auth_login'    => ['guest-role-000'],
        'auth_register' => ['guest-role-000'],
        'contact_send'  => ['guest-role-000', 'member-role-000', 'admin-role-000'],

        // Beispiele für später:
        // 'page_save'   => ['admin-role-000', 'editor-role-000'],
        // 'page_delete' => ['admin-role-000'],
    ];

    public static function dispatch(
        string $action,
        array $postData,
        array $pageMeta
    ): array {
        try {
            // 1) CSRF prüfen
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals($_SESSION['_csrf'], $postData['_csrf'])
            ) {
                throw new RuntimeException('Invalid CSRF token');
            }

            // 2) Page erlaubt diese Action?
            $allowedActions = array_filter(
                array_map('trim', explode(',', (string)($pageMeta['form_action'] ?? '')))
            );

            if (!in_array($action, $allowedActions, true)) {
                throw new RuntimeException('FormAction auf dieser Seite nicht erlaubt');
            }

            // 3) Existiert ein Handler für diese Action?
            if (!isset(self::MAP[$action])) {
                throw new RuntimeException('Unbekannte FormAction: ' . $action);
            }

            $handlerClass = self::MAP[$action];

            // 4) Rollen-Prüfung (Action-basiert, zentral)
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

            if (
                isset(self::ACTION_ROLES[$action])
                && !in_array($roleId, self::ACTION_ROLES[$action], true)
            ) {
                throw new RuntimeException('Keine Berechtigung für diese Aktion');
            }

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
