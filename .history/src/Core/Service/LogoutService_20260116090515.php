<?php
declare(strict_types=1);

namespace CMS\Core\Service;

/**
 * LogoutService
 *
 * Kapselt ALLE Logout- & Session-Zerstörungslogik
 * KEIN Output
 * KEIN Redirect
 */
final class LogoutService
{
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Session-Daten löschen
        $_SESSION = [];

        // Session-Cookie löschen (wichtig!)
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

        // Session zerstören
        session_destroy();

        // Neue Session-ID erzwingen (Security)
        session_start();
        session_regenerate_id(true);
    }
}
