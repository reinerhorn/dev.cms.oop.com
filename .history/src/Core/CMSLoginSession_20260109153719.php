<?php
declare(strict_types=1);

namespace CMS\Core\Session;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;
use RuntimeException;

class CMSLoginSession
{
    /**
     * Login anhand E-Mail + Passwort
     */
    public static function login(string $email, string $password): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = CMSApp::getDb();

        $sql = "
            SELECT
                id,
                password,
                role_id,
                is_verified
            FROM login_users
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB-Fehler (prepare)');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result?->fetch_assoc();
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
            throw new RuntimeException('Benutzer hat keine role_id');
        }

        // 🔐 Session setzen (NUR UUIDs)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        // Session härten
        session_regenerate_id(true);
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset(
            $_SESSION['user_id'],
            $_SESSION['role_id']
        );

        session_regenerate_id(true);
    }

    /**
     * Ist ein Benutzer eingeloggt?
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['role_id']);
    }

    /**
     * Aktuelle User-ID (UUID)
     */
    public static function getUserId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Aktuelle Role-ID (UUID)
     */
    public static function getRoleId(): ?string
    {
        return $_SESSION['role_id'] ?? null;
    }

    /**
     * Login erzwingen
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            throw new RuntimeException('Login erforderlich');
        }
    }

    /**
     * Permission erzwingen (delegiert an AuthService)
     */
    public static function requirePermission(string $permissionId): void
    {
        self::requireLogin();

        $auth = new AuthService();

        if (!$auth->hasPermission($permissionId)) {
            throw new RuntimeException('Keine Berechtigung');
        }
    }
}