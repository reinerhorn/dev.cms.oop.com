<?php
declare(strict_types=1);

namespace CMS\Repository\Navigation;

use mysqli;

class NavigationFallback
{
    public static function getItems(mysqli $db, string $language, int $role, ?string $pageId): array
    {
        $stmt = $db->prepare("
            SELECT n.id, n.parent_id, n.slug, n.css, t.label, p.page_uuid
            FROM navigation n
            JOIN page p ON n.page_id = p.page_uuid
            LEFT JOIN translation t ON t.page_id = p.page_uuid AND t.language = ?
            WHERE p.enabled = 1
              AND (p.role IS NULL OR p.role = ?)
            ORDER BY n.idx ASC
        ");

        if (!$stmt) {
            throw new \RuntimeException('Datenbank-Statement konnte nicht vorbereitet werden: ' . $db->error);
        }

        $stmt->bind_param("si", $language, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        $itemsById = [];
        $tree = [];

        while ($row = $result->fetch_assoc()) {
            // URL-Generierung: zuerst Slug, wenn vorhanden, sonst page_uuid
            if (!empty($row['slug'])) {
                $url = '/' . $language . '/' . $row['slug'];
            } else {
                $url = '/' . $language . '/' . $row['page_uuid'];
            }
            $itemsById[$row['id']] = [
                'id' => $row['id'],
                'parent_id' => $row['parent_id'],
                'title' => $row['label'] ?? '',
                'url' => $url,
                'active' => ($row['page_uuid'] === $pageId),
                'css' => $row['css'] ?? '',
                'subpages' => []
            ];
        }

        foreach ($itemsById as $id => &$item) {
            if ($item['parent_id'] === null || !isset($itemsById[$item['parent_id']])) {
                $tree[] = &$item;
            } else {
                $itemsById[$item['parent_id']]['subpages'][] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}