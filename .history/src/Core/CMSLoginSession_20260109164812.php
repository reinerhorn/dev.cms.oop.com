<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;
use RuntimeException;

final class CMSLoginSession
{
    /* =====================================================
     * ZENTRALER ENTRYPOINT (Login / Logout)
     * ===================================================== */
    public static function handleUserAction(array $data): array
    {
        try {
            $action = $data['action'] ?? null;

            if ($action === 'login') {
                self::handleLogin($data);
                return [
                    'success'  => true,
                    'redirect' => $data['redirect'] ?? '/de/startseite'
                ];
            }

            if ($action === 'logout') {
                self::logout();
                return [
                    'success'  => true,
                    'redirect' => '/de/startseite'
                ];
            }

            return [
                'success' => false,
                'error'   => 'Unbekannte Aktion'
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    /* =====================================================
     * LOGIN
     * ===================================================== */
    private static function handleLogin(array $data): void
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new RuntimeException('E-Mail oder Passwort fehlt');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = CMSApp::getDb();

        $sql = "
            SELECT id, password, role_id, is_verified
            FROM login_users
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result?->fetch_assoc();
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

        // 🔐 Session setzen (NUR UUIDs)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        session_regenerate_id(true);
    }

    /* =====================================================
     * LOGOUT
     * ===================================================== */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_id'], $_SESSION['role_id']);
        session_regenerate_id(true);
    }

    /* =====================================================
     * STATUS / GUARDS
     * ===================================================== */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role_id']);
    }

    public static function getUserId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getRoleId(): ?string
    {
        return $_SESSION['role_id'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            throw new RuntimeException('Login erforderlich');
        }
    }

    public static function requirePermission(string $permissionId): void
    {
        self::requireLogin();

        $auth = new AuthService();
        if (!$auth->hasPermission($permissionId)) {
            throw new RuntimeException('Keine Berechtigung');
        }
    }
}