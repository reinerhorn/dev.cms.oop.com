<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\Repository\UserRepository;

final class CMSLoginSession
{
    public static function handleUserAction(array $data): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $intent = $data['intent'] ?? null;

        return match ($intent) {
            'login'    => self::login($data),
            'register' => self::register($data),
            'logout'   => self::logout(),
            default    => [
                'success' => false,
                'error'   => 'Unbekannte Aktion',
            ],
        };
    }

    /* =========================
       LOGIN
       ========================= */
    private static function login(array $data): array
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Daten fehlen'];
        }

        $user = UserRepository::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Login fehlgeschlagen'];
        }

        // ✅ SESSION SETZEN
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        return [
            'success'  => true,
            'redirect' => '/de/adminbereich',
        ];
    }

    /* =========================
       REGISTER (Platzhalter)
       ========================= */
    private static function register(array $data): array
    {
        return [
            'success' => false,
            'error'   => 'Register noch nicht implementiert',
        ];
    }

    /* =========================
       LOGOUT
       ========================= */
    private static function logout(): array
    {
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

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }
}