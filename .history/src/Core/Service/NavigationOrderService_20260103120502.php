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

    /**
     * Normalisiert die sort_order-Werte für einen Parent (0..n ohne Lücken)
     * Wird nach Delete / Move verwendet
     */
    public function normalizeSortOrderForParent(?string $parentId): void
    {
        $sql = "
            SELECT nav_uuid
            FROM navigation
            WHERE parent_id " . ($parentId === null ? "IS NULL" : "= ?") . "
            ORDER BY sort_order ASC, nav_uuid ASC
        ";

        $stmt = $this->db->prepare($sql);

        if ($parentId !== null) {
            $stmt->bind_param('s', $parentId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $order = 0;

        $update = $this->db->prepare(
            "UPDATE navigation SET sort_order = ? WHERE nav_uuid = ?"
        );

        while ($row = $result->fetch_assoc()) {
            $update->bind_param('is', $order, $row['nav_uuid']);
            $update->execute();
            $order++;
        }
    }
}

