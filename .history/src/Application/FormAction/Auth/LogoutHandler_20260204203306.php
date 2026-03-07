<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

final class LogoutHandler
{
    public static function handle(): array
    {
        // Session sicherstellen
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Sprache sichern, bevor Session geleert wird
        $language = $_SESSION['language'] ?? 'de';

        // Alle Session-Daten löschen
        $_SESSION = [];

        // Session-Cookie sauber löschen
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

        // Session endgültig zerstören
        session_destroy();

        return [
            'success'  => true,
            'redirect' => '/' . $language . '/startseite',
        ];
    }
}