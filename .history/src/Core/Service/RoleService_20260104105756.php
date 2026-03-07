<?php
declare(strict_types=1);

namespace CMS\Core\Service;

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

    /**
     * Liefert alle Permissions einer Rolle
     */
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

        $this->permissionCache[$roleId] = $permissions;

        return $permissions;
    }

    /**
     * Prüft ob eine Rolle eine Permission besitzt
     */
    public function roleHasPermission(string $roleId, string $permissionId): bool
    {
        return in_array(
            $permissionId,
            $this->getPermissionsForRole($roleId),
            true
        );
    }

    /**
     * Prüft mehrere Permissions (AND)
     */
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

    /**
     * Prüft mehrere Permissions (OR)
     */
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
}
