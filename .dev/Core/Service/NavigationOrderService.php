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

    /**
     * Verschiebt ein Navigation-Item zu einem anderen Parent
     * - hängt es beim neuen Parent ans Ende
     * - normalisiert den alten Parent
     */
    public function moveItem(string $navUuid, ?string $newParentId): void
    {
        $this->db->begin_transaction();

        try {
            // aktuellen Parent ermitteln
            $stmt = $this->db->prepare("
                SELECT parent_id
                FROM navigation
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('s', $navUuid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if (!$row) {
                throw new \RuntimeException('Navigation item not found: ' . $navUuid);
            }

            $oldParentId = $row['parent_id'];

            // neuen sort_order für Ziel-Parent bestimmen
            $newSortOrder = $this->getNextSortOrder($newParentId);

            // Item verschieben
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET parent_id = ?, sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('sis', $newParentId, $newSortOrder, $navUuid);
            $stmt->execute();

            // alten Parent neu nummerieren
            $this->normalizeSortOrderForParent($oldParentId);

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    /**
     * Verschiebt ein Navigation-Item eine Position nach oben
     * (innerhalb desselben parent_id-Blocks)
     */
    public function moveUp(string $navUuid): void
    {
        // aktuellen Eintrag laden
        $stmt = $this->db->prepare("
            SELECT parent_id, sort_order
            FROM navigation
            WHERE nav_uuid = ?
        ");
        $stmt->bind_param('s', $navUuid);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current) {
            return;
        }

        $parentId  = $current['parent_id'];
        $sortOrder = (int)$current['sort_order'];

        if ($sortOrder === 0) {
            return; // bereits ganz oben
        }

        // Vorgänger ermitteln
        $stmt = $this->db->prepare("
            SELECT nav_uuid
            FROM navigation
            WHERE parent_id <=> ?
              AND sort_order = ?
            LIMIT 1
        ");
        $prevOrder = $sortOrder - 1;
        $stmt->bind_param('si', $parentId, $prevOrder);
        $stmt->execute();
        $prev = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prev) {
            return;
        }

        // Swap innerhalb einer Transaktion
        $this->db->begin_transaction();

        try {
            // Vorgänger nach unten
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('is', $sortOrder, $prev['nav_uuid']);
            $stmt->execute();
            $stmt->close();

            // Aktuellen nach oben
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('is', $prevOrder, $navUuid);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Verschiebt ein Navigation-Item eine Position nach unten
     * (innerhalb desselben parent_id-Blocks)
     */
    public function moveDown(string $navUuid): void
    {
        // aktuellen Eintrag laden
        $stmt = $this->db->prepare("
            SELECT parent_id, sort_order
            FROM navigation
            WHERE nav_uuid = ?
        ");
        $stmt->bind_param('s', $navUuid);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$current) {
            return;
        }

        $parentId  = $current['parent_id'];
        $sortOrder = (int)$current['sort_order'];

        // Maximum sort_order für den gleichen parent_id ermitteln
        $stmt = $this->db->prepare("
            SELECT MAX(sort_order) AS max_order
            FROM navigation
            WHERE parent_id <=> ?
        ");
        $stmt->bind_param('s', $parentId);
        $stmt->execute();
        $maxResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $maxOrder = $maxResult ? (int)$maxResult['max_order'] : $sortOrder;

        if ($sortOrder >= $maxOrder) {
            return; // bereits ganz unten
        }

        // Nachfolger ermitteln
        $stmt = $this->db->prepare("
            SELECT nav_uuid
            FROM navigation
            WHERE parent_id <=> ?
              AND sort_order = ?
            LIMIT 1
        ");
        $nextOrder = $sortOrder + 1;
        $stmt->bind_param('si', $parentId, $nextOrder);
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$next) {
            return;
        }

        // Swap innerhalb einer Transaktion
        $this->db->begin_transaction();

        try {
            // Nachfolger nach oben
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('is', $sortOrder, $next['nav_uuid']);
            $stmt->execute();
            $stmt->close();

            // Aktuellen nach unten
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('is', $nextOrder, $navUuid);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    /**
     * Verschiebt ein Navigation-Item an eine exakte Position
     * innerhalb eines Parents (für Drag & Drop)
     */
    public function moveToPosition(
        string $navUuid,
        ?string $newParentId,
        int $newPosition
    ): void {
        $this->db->begin_transaction();

        try {
            // Aktuelles Item laden
            $stmt = $this->db->prepare("
                SELECT parent_id, sort_order
                FROM navigation
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('s', $navUuid);
            $stmt->execute();
            $current = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$current) {
                throw new \RuntimeException('Navigation item not found: ' . $navUuid);
            }

            $oldParentId = $current['parent_id'];

            // Ziel-Position absichern (nicht < 0)
            if ($newPosition < 0) {
                $newPosition = 0;
            }

            // Platz schaffen: alle Items ab newPosition nach unten schieben
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET sort_order = sort_order + 1
                WHERE parent_id <=> ?
                  AND sort_order >= ?
            ");
            $stmt->bind_param('si', $newParentId, $newPosition);
            $stmt->execute();
            $stmt->close();

            // Item auf neue Position setzen
            $stmt = $this->db->prepare("
                UPDATE navigation
                SET parent_id = ?, sort_order = ?
                WHERE nav_uuid = ?
            ");
            $stmt->bind_param('sis', $newParentId, $newPosition, $navUuid);
            $stmt->execute();
            $stmt->close();

            // Alten Parent bereinigen
            $this->normalizeSortOrderForParent($oldParentId);

            // Neuen Parent bereinigen
            $this->normalizeSortOrderForParent($newParentId);

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

