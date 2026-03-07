<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Repository\Navigation\Navigation;

class RequestInit
{
    protected \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Initialisiere Request: Rolle, Header, Navigation
     *
     * @param string|null $requiredPermission  optional: permission_id, die benötigt wird
     * @return array|false  ['role_id'=>string, 'role_numeric'=>int, 'header'=>array, 'navigation'=>array] oder false bei verweigert
     */
    public function init(?string $requiredPermission = null)
    {
        // Rolle aus Session
        $roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // Wenn Berechtigung erforderlich: prüfen
        if ($requiredPermission !== null) {
            $stmt = $this->db->prepare("
                SELECT 1
                FROM role_permissions
                WHERE role_id = ? AND permission_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ss', $roleId, $requiredPermission);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) {
                return false;  // Zugriff verweigert
            }
        }

        // Rolle numerisch übersetzen: guest=0, admin=1, member=2
        $roleNumeric = match (true) {
            str_starts_with($roleId, 'adminbereich')  => 1,
            str_starts_with($roleId, 'member') => 2,
            default                            => 0,
        };

        // Header laden: je Rolle
        $stmtH = $this->db->prepare("
            SELECT *
            FROM header
            WHERE role = ?
            LIMIT 1
        ");
        $stmtH->bind_param('i', $roleNumeric);
        $stmtH->execute();
        $header = $stmtH->get_result()->fetch_assoc();
        $stmtH->close();

        // Navigation laden über Repository (angepasst auf Rolle)
        $navRepo = new Navigation($this->db, /* language */ 'de', $roleId);
        $navigation = $navRepo->getItems();

        return [
            'role_id'    => $roleId,
            'role_numeric' => $roleNumeric,
            'header'     => $header,
            'navigation' => $navigation,
        ];
    }
}
