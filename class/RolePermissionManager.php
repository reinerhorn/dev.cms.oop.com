<?php
class RolePermissionManager {
    private mysqli $db;
    private string $roleId;
    private array $permissions = [];

    public function __construct(mysqli $db, string $roleId) {
        $this->db = $db;
        $this->roleId = $roleId;
        $this->loadPermissions();
    }

    private function loadPermissions(): void {
        $stmt = $this->db->prepare("
            SELECT p.key 
            FROM permissions p
            INNER JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->bind_param("s", $this->roleId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $this->permissions[] = $row['key'];
        }
    }

    public function hasPermission(string $key): bool {
        return in_array($key, $this->permissions);
    }

    public function getPermissions(): array {
        return $this->permissions;
    }
}
