<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use RuntimeException;

class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_ROLE_ID = 'role_id';

    // =========================
    // Basis
    // =========================

    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_USER_ID]) && is_string($_SESSION[self::SESSION_USER_ID]);
    }

    public function getUserId(): ?string
    {
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    public function getRoleId(): ?string
    {
        return $_SESSION[self::SESSION_ROLE_ID] ?? null;
    }

    // =========================
    // Login / Logout
    // =========================

    public function login(string $userId, string $roleId): void
    {
        error_log('AUTH LOGIN START');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
            error_log('SESSION STARTED');
        }

        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_ROLE_ID] = $roleId;

        error_log('SESSION DATA WRITTEN');

        if (!headers_sent()) {
            session_regenerate_id(true);
            error_log('SESSION REGENERATED');
        } else {
            error_log('HEADERS ALREADY SENT');
        }

        error_log('AUTH LOGIN END');
    }

    public function logout(): void
    {
        // 1) Session komplett leeren
        $_SESSION = [];

        // 2) Session-Cookie im Browser löschen
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

        // 4) Frische Session als Gast starten
        session_start();
        session_regenerate_id(true);
    }

    // =========================
    // Guards
    // =========================

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            throw new RuntimeException('Login erforderlich');
        }
    }

    public function requirePermission(string $permissionId): void
    {
        $this->requireLogin();

        if (!$this->hasPermission($permissionId)) {
            throw new RuntimeException('Keine Berechtigung');
        }
    }

    // =========================
    // Permission-Logik (KERN)
    // =========================

    public function hasPermission(string $permissionId): bool
    {
        $roleId = $this->getRoleId();
        if ($roleId === null) {
            return false;
        }

        $db = CMSApp::getDb();

        $sql = "
            SELECT 1
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
              AND p.id = ?
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('ss', $roleId, $permissionId);
        $stmt->execute();
        $stmt->store_result();

        $hasPermission = $stmt->num_rows > 0;
        $stmt->close();

        return $hasPermission;
    }

    // =========================
    // Audience / Context (KERN)
    // =========================

    /**
     * Liefert die Audience anhand der Rolle
     * Beispiele:
     *  - admin-role-001   → admin
     *  - member-role-001  → member
     *  - support-role-002 → support
     *  - guest / null     → public
     */
    public function getAudience(): string
    {
        $roleId = $this->getRoleId();

        if (!$roleId || $roleId === 'guest-role-000') {
            return 'public';
        }

        // Alles vor dem ersten "-" ist die Audience
        return explode('-', $roleId, 2)[0];
    }

    // =========================
    // Helper (optional)
    // =========================

    /**
     * Für Twig / Debug / Navigation
     */
    public function getAllPermissions(): array
    {
        $roleId = $this->getRoleId();
        if ($roleId === null) {
            return [];
        }

        $db = CMSApp::getDb();

        $sql = "
            SELECT p.id
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param('s', $roleId);
        $stmt->execute();
        $result = $stmt->get_result();

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['id'];
        }

        $stmt->close();

        return $permissions;
    }
}
