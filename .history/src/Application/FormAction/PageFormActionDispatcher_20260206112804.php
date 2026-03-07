<?php
namespace CMS\Application\FormAction;

use RuntimeException;
use CMS\Core\CMSApp;
use CMS\Security\AccessResolver;

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
            // 0) Action muss aus dem POST stammen
            if (empty($action)) {
                throw new RuntimeException('Keine FormAction übermittelt');
            }
            // 0.1) Action muss im POST vorhanden sein (Button wurde geklickt)
            if (!isset($postData['action']) || $postData['action'] !== $action) {
                throw new RuntimeException('Ungültige oder manipulierte FormAction');
            }

            // 1) CSRF prüfen
            if (
                empty($postData['_csrf'])
                || empty($_SESSION['_csrf'])
                || !hash_equals($_SESSION['_csrf'], $postData['_csrf'])
            ) {
                throw new RuntimeException('Invalid CSRF token');
            }


            // 3) Existiert ein Handler für diese Action?
            if (!isset(self::MAP[$action])) {
                throw new RuntimeException('Unbekannte FormAction: ' . $action);
            }

            $handlerClass = self::MAP[$action];

            // 4) ZENTRALE Action-Berechtigung (AccessResolver)
            $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

            $accessResolver = CMSApp::getAccessResolver();
            $accessResolver->assertFormActionAllowed(
                $action,
                $roleId,
                (string)($pageMeta['page_uuid'] ?? '')
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
