<?php
declare(strict_types=1);

namespace CMS\Security;

use mysqli;
use RuntimeException;

class UserRoleManager
{
    private mysqli $db;
    private string $userId;
    private ?string $roleId = null;
    private array $permissions = [];

    public function __construct(mysqli $db, string $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->loadUserRole();
        $this->loadPermissions();
    }

    /**
     * Holt die Role-ID aus login_users (z. B. admin-role-001)
     */
    private function loadUserRole(): void
    {
        $stmt = $this->db->prepare("SELECT role_id FROM login_users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler (UserRole): ' . $this->db->error);
        }
        $stmt->bind_param('s', $this->userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $this->roleId = $row['role_id'] ?? null;
    }

    /**
     * Lädt alle Berechtigungen der Rolle
     */
    private function loadPermissions(): void
    {
        if (!$this->roleId) {
            $this->permissions = [];
            return;
        }

        $sql = "
            SELECT p.name
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE rp.role_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler (Permissions): ' . $this->db->error);
        }
        $stmt->bind_param('s', $this->roleId);
        $stmt->execute();
        $res = $stmt->get_result();

        $this->permissions = [];
        while ($row = $res->fetch_assoc()) {
            $this->permissions[] = $row['name'];
        }
        $stmt->close();
    }

    /**
     * Gibt alle Berechtigungen des Nutzers zurück.
     */
    public function getAllPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Prüft, ob der Nutzer eine bestimmte Berechtigung hat.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Gibt die Role-ID des Nutzers zurück (z. B. admin-role-001)
     */
    public function getRoleId(): ?string
    {
        return $this->roleId;
    }

    /**
     * Gibt den Rollennamen zurück (z. B. "Administrator")
     */
    public function getRoleName(): ?string
    {
        if (!$this->roleId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT name FROM roles WHERE role_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $this->roleId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        return $row['name'] ?? null;
    }

    /**
     * Initialisiert UserRoleManager aus der Session und lädt Berechtigungen in die Session.
     *
     * @param mysqli $db
     * @return UserRoleManager|null
     */
    public static function initFromSession(mysqli $db): ?self
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        $userId = $_SESSION['user_id'];
        $userRoleManager = new self($db, $userId);

        $_SESSION['permissions'] = $userRoleManager->getAllPermissions();

        return $userRoleManager;
    }
}