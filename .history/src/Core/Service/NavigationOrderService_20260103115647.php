<?php
declare(strict_types=1);

namespace CMS\Core\Service;

use mysqli;

class NavigationOrderService
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Liefert den nächsten sort_order für einen Parent
     */
    public function getNextSortOrder(?string $parentId): int
    {
        $sql = "
            SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_order
            FROM navigation
            WHERE parent_id " . ($parentId === null ? "IS NULL" : "= ?");

        $stmt = $this->db->prepare($sql);

        if ($parentId !== null) {
            $stmt->bind_param('s', $parentId);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return (int)$result['next_order'];
    }
}
