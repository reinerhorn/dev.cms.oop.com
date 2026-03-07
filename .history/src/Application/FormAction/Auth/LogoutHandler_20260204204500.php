<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

final class LogoutHandler
{
    public static function handle(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Sprache sichern (minimaler Whitelist-State)
        $language = $_SESSION['language'] ?? 'de';

        // 1) Session komplett leeren
        $_SESSION = [];

        // 2) Session-Cookie löschen (KRITISCH)
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

        // 3) Session zerstören
        session_destroy();

        // 4) Neue saubere Session starten (Guest)
        session_start();
        session_regenerate_id(true);

        // 5) Nur definierte Guest-Werte setzen
        $_SESSION['language'] = $language;
        $_SESSION['role_id'] = 'guest-role-000';

        return [
            'success'  => true,
            'redirect' => '/' . $language . '/startseite',
        ];
    }
}