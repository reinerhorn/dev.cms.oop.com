<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class CMSLoginSession
{
    /* =====================================================
     * ZENTRALER ENTRYPOINT
     * ===================================================== */
    public static function handleUserAction(array $data): array
    {
        self::ensureSession();

        return match ($data['intent'] ?? '') {
            'login'    => self::login($data),
            'register' => self::register($data),
            'logout'   => self::logout(),
            default    => [
                'success' => false,
                'error'   => 'Unbekannte Aktion',
            ],
        };
    }

    /* =====================================================
     * LOGIN
     * ===================================================== */
    private static function login(array $data): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new RuntimeException('E-Mail oder Passwort fehlt');
        }

        $db = CMSApp::getDb();

        $stmt = $db->prepare(
            "SELECT id, password, role_id, is_verified
             FROM login_users
             WHERE email = ?
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            throw new RuntimeException('Benutzer nicht gefunden');
        }

        if (!password_verify($password, $user['password'])) {
            throw new RuntimeException('Ungültiges Passwort');
        }

        if ((int)$user['is_verified'] !== 1) {
            throw new RuntimeException('Account nicht verifiziert');
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        session_regenerate_id(true);

        return [
            'success'  => true,
            'redirect' => str_starts_with($user['role_id'], 'admin-')
                ? '/de/adminbereich'
                : '/de/startseite',
        ];
    }

    /* =====================================================
     * REGISTER
     * ===================================================== */
    private static function register(array $data): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $username = trim((string)($data['username'] ?? ''));

        if ($email === '' || $password === '' || $username === '') {
            throw new RuntimeException('Pflichtfelder fehlen');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ungültige E-Mail-Adresse');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Passwort zu kurz');
        }

        $db = CMSApp::getDb();

        $check = $db->prepare(
            "SELECT 1 FROM login_users WHERE email = ? LIMIT 1"
        );
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            throw new RuntimeException('E-Mail bereits registriert');
        }
        $check->close();

        $userId      = 'user-' . bin2hex(random_bytes(8));
        $verifyToken = bin2hex(random_bytes(32));

        $stmt = $db->prepare(
            "INSERT INTO login_users
             (id, email, password, username, role_id, verify_token, is_verified)
             VALUES (?, ?, ?, ?, 'member-role-002', ?, 0)"
        );
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler beim Anlegen');
        }

        $stmt->bind_param(
            'sssss',
            $userId,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $username,
            $verifyToken
        );
        $stmt->execute();
        $stmt->close();

        return [
            'success'  => true,
            'redirect' => '/de/login',
        ];
    }

    /* =====================================================
     * LOGOUT
     * ===================================================== */
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

    /* =====================================================
     * SESSION
     * ===================================================== */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}