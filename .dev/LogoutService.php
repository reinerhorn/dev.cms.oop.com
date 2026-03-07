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

        // Alle Session-Daten löschen
        $_SESSION = [];

        // Session-Cookie korrekt entfernen
        if (ini_get('session.use_cookies')) {
            $cookieParams = session_get_cookie_params();

            $cookieName   = session_name();
            $cookiePath   = $cookieParams['path'] ?? '/';
            $cookieDomain = $cookieParams['domain'] ?? '';
            $cookieSecure = (bool)($cookieParams['secure'] ?? false);
            $cookieHttp   = (bool)($cookieParams['httponly'] ?? false);

            setcookie(
                $cookieName,
                '',
                time() - 42000,
                $cookiePath,
                $cookieDomain,
                $cookieSecure,
                $cookieHttp
            );
        }

        // Session zerstören
        session_destroy();

        // Neue Session sauber starten + neue ID (Security)
        session_start();
        session_regenerate_id(true);
    }
}
