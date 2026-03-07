<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;
use RuntimeException;

/**
 * CMSLoginSession
 *
 * Zentrale Session- & Login-Logik
 * KEIN Rendering, KEIN Twig, KEIN Frontend
 */
final class CMSLoginSession
{
    /* =====================================================
     * ZENTRALER ENTRYPOINT
     * ===================================================== */
    public static function handleUserAction(array $data): array
    {
        $action = $data['action'] ?? null;

        try {
            return match ($action) {
                'login'    => self::login($data),
                'register' => self::register($data),
                'logout'   => self::logout(),
                default    => [
                    'success' => false,
                    'error'   => 'Unbekannte Aktion',
                ],
            };
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
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

        self::ensureSession();

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
        $user = $stmt->get_result()?->fetch_assoc();
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

        if (empty($user['role_id'])) {
            throw new RuntimeException('Benutzer hat keine Rolle');
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        session_regenerate_id(true);

        return [
            'success'  => true,
            'redirect' => $data['redirect'] ?? '/de/startseite',
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

        if ($email === '' || $password === '') {
            throw new RuntimeException('E-Mail oder Passwort fehlt');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ungültige E-Mail-Adresse');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Passwort zu kurz (min. 8 Zeichen)');
        }

        self::ensureSession();
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

        $userId = 'user-' . bin2hex(random_bytes(8));
        $roleId = 'member-role-002';
        $hash   = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            "INSERT INTO login_users
             (id, email, password, username, role_id, is_verified)
             VALUES (?, ?, ?, ?, ?, 0)"
        );

        if (!$stmt) {
            throw new RuntimeException('DB-Fehler beim Anlegen');
        }

        $stmt->bind_param(
            'sssss',
            $userId,
            $email,
            $hash,
            $username,
            $roleId
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
        self::ensureSession();

        unset($_SESSION['user_id'], $_SESSION['role_id']);
        session_regenerate_id(true);

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role_id']);
    }

    public static function requirePermission(string $permissionId): void
    {
        self::ensureSession();

        if (!self::isLoggedIn()) {
            throw new RuntimeException('Login erforderlich');
        }

        $auth = new AuthService();
        if (!$auth->hasPermission($permissionId)) {
            throw new RuntimeException('Keine Berechtigung');
        }
    }
}