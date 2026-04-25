<?php
declare(strict_types=1);

namespace CMS\Application\Service;

use mysqli;

class RoleService
{
    private mysqli $db;

    /** Cache für Permissions pro Rolle */
    private array $permissionCache = [];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /* -------------------------------------------------
     * Permissions
     * ------------------------------------------------- */

    public function getPermissionsForRole(string $roleId): array
    {
        if (isset($this->permissionCache[$roleId])) {
            return $this->permissionCache[$roleId];
        }

        $stmt = $this->db->prepare("
            SELECT p.id
            FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ?
        ");
        $stmt->bind_param('s', $roleId);
        $stmt->execute();

        $result = $stmt->get_result();
        $permissions = [];

        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['id'];
        }

        $stmt->close();

        return $this->permissionCache[$roleId] = $permissions;
    }

    public function roleHasPermission(string $roleId, string $permissionId): bool
    {
        return in_array(
            $permissionId,
            $this->getPermissionsForRole($roleId),
            true
        );
    }

    public function roleHasAllPermissions(string $roleId, array $permissionIds): bool
    {
        $permissions = $this->getPermissionsForRole($roleId);

        foreach ($permissionIds as $permissionId) {
            if (!in_array($permissionId, $permissions, true)) {
                return false;
            }
        }

        return true;
    }

    public function roleHasAnyPermission(string $roleId, array $permissionIds): bool
    {
        $permissions = $this->getPermissionsForRole($roleId);

        foreach ($permissionIds as $permissionId) {
            if (in_array($permissionId, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    /* -------------------------------------------------
     * Session / Role
     * ------------------------------------------------- */

    public function getCurrentRoleId(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $_SESSION['role_id'] ?? 'guest-role-000';
    }

    /* -------------------------------------------------
     * Startseiten-Auflösung (DB-driven)
     * ------------------------------------------------- */

    public function getDefaultPageId(string $roleId): ?string
    {
        return $this->fetchRolePageField($roleId, 'default_page_id');
    }

    public function getMemberFallbackPageId(string $roleId): ?string
    {
        return $this->fetchRolePageField($roleId, 'member_fallback_page_id');
    }

    private function fetchRolePageField(string $roleId, string $field): ?string
    {
        $stmt = $this->db->prepare("
            SELECT {$field}
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $roleId);
        $stmt->execute();

        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        return $row[$field] ?? null;
    }
}
