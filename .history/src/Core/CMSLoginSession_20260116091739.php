<?php
declare(strict_types=1);

namespace CMS\Core;


use RuntimeException;

final class CMSLoginSession
{
    public function handle(array $data): void
    {
        $action = $data['intent'] ?? null;

        if ($action === 'logout') {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();

            echo json_encode([
                'success'  => true,
                'redirect' => '/de/startseite'
            ]);
            return;
        }

        $result = CMSLoginSession::handleUserAction($data);

        header('Content-Type: application/json');
        echo json_encode($result);
    }
}