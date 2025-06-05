<?php
class UserRoleManager {
    private mysqli $db;
    private string $userId;

    public function __construct(mysqli $db, ?string $userId) {
        if (empty($userId)) {
            error_log("⚠️ UserRoleManager initialized with empty userId");
            $userId = 'unknown'; // Optional fallback, or set to return early in other methods
        }
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getRoles(): array {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->bind_param("s", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row['id'];
        }
        return $roles;
    }

    public function getPermissions(): array {
        $stmt = $this->db->prepare("
            SELECT p.name 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->bind_param("s", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['name'];
        }
        return $permissions;
    }

    public function hasPermission(string $permName): bool {
        return in_array($permName, $this->getPermissions(), true);
    }
    public function getAllPermissions(): array {
        return $this->getPermissions();
    }
}
