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

    public static function dispatch(
        string $action,
        array $postData,
        array $pageMeta
    ): array {
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

        // 4) Optionale Rollen-/Permission-Prüfung (zentral erweiterbar)
        // Aktuelle Rolle aus der Session
        $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // Falls später benötigt, kann hier z. B. CMSApp::getRoleService() genutzt werden

        return (new $handlerClass())->handle($postData, $pageMeta);
    }
}
