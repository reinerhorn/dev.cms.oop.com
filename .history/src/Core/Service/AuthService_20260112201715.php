<?php
declare(strict_types=1);

namespace CMS\Core\Service;

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
        return isset($_SESSION[self::SESSION_USER_ID]);
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
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_ROLE_ID] = $roleId;
    }

    public function logout(): void
    {
        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_ROLE_ID]
        );
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