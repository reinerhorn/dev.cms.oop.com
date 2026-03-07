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

            if ($action === 'register') {
                self::handleRegister($data);
                return [
                    'success'  => true,
                    'redirect' => $data['redirect'] ?? '/de/login'
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
     * REGISTER
     * ===================================================== */
    private static function handleRegister(array $data): void
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $username = trim((string)($data['username'] ?? ''));

        if ($email === '' || $password === '') {
            throw new \RuntimeException('E-Mail oder Passwort fehlt');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige E-Mail-Adresse');
        }

        if (strlen($password) < 8) {
            throw new \RuntimeException('Passwort zu kurz (min. 8 Zeichen)');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = CMSApp::getDb();

        // Prüfen ob E-Mail existiert
        $check = $db->prepare("SELECT 1 FROM login_users WHERE email = ? LIMIT 1");
        if (!$check) {
            throw new \RuntimeException('DB-Fehler');
        }
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            throw new \RuntimeException('E-Mail bereits registriert');
        }
        $check->close();

        $userId   = 'user-' . bin2hex(random_bytes(8));
        $roleId   = 'member-role-002'; // Standardrolle
        $hash     = password_hash($password, PASSWORD_DEFAULT);
        $verified = 0;

        $stmt = $db->prepare("
            INSERT INTO login_users
                (id, email, password, username, role_id, is_verified)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new \RuntimeException('DB-Fehler beim Anlegen');
        }

        $stmt->bind_param(
            'sssssi',
            $userId,
            $email,
            $hash,
            $username,
            $roleId,
            $verified
        );

        $stmt->execute();
        $stmt->close();
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