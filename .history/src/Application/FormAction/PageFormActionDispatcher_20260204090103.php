<?php
namespace CMS\Application\FormAction;

use RuntimeException;

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
        if (!isset(self::MAP[$action])) {
            throw new RuntimeException('Unbekannte FormAction: ' . $action);
        }

        $handlerClass = self::MAP[$action];

        return (new $handlerClass())->handle($postData, $pageMeta);
    }
}
