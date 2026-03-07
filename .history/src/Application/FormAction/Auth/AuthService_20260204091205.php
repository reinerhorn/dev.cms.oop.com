<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use RuntimeException;

class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_ROLE_ID = 'role_id';

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    // =========================
    // Basis
    // =========================

    public function isLoggedIn(): bool
    {
        $this->ensureSession();
        return isset($_SESSION[self::SESSION_USER_ID]) && is_string($_SESSION[self::SESSION_USER_ID]);
    }

    public function getUserId(): ?string
    {
        $this->ensureSession();
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    public function getRoleId(): ?string
    {
        $this->ensureSession();
        return $_SESSION[self::SESSION_ROLE_ID] ?? null;
    }

    // =========================
    // Login / Logout
    // =========================

    public function login(string $userId, string $roleId): void
    {
        $this->ensureSession();
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_ROLE_ID] = $roleId;
        session_regenerate_id(true);
    }

    public function logout(): void
    {
        $this->ensureSession();

        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_ROLE_ID]
        );

        session_regenerate_id(true);
    }

    // =========================
    // Guards
    // =========================

    public function requireLogin(): void
    {
        $this->ensureSession();
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
        $this->ensureSession();
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
        $this->ensureSession();

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
        $this->ensureSession();
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
