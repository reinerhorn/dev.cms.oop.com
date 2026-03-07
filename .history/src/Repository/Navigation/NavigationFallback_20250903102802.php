<?php
declare(strict_types=1);

namespace CMS\Repository\Navigation;

use mysqli;

class NavigationFallback
{
    public static function getItems(mysqli $db, string $language, int $role, ?string $pageId): array
    {
        $stmt = $db->prepare("
            SELECT page_uuid, label, type, parent_id, css, slug
            FROM page
            WHERE language = ?
              AND enabled = 1
              AND (role IS NULL OR role = ?)
            ORDER BY idx ASC
        ");

        if (!$stmt) {
            throw new \RuntimeException('Datenbank-Statement konnte nicht vorbereitet werden: ' . $db->error);
        }

        $stmt->bind_param("si", $language, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        $mainPages = [];
        $subPagesMap = [];

        while ($row = $result->fetch_assoc()) {
            if (($row['type'] ?? 'main') === 'main') {
                $mainPages[] = $row;
            } elseif (($row['type'] ?? '') === 'sub') {
                $subPagesMap[$row['parent_id']][] = $row;
            }
        }

        $items = [];
        foreach ($mainPages as $page) {
            $subs = [];
            if (!empty($subPagesMap[$page['page_uuid']])) {
                foreach ($subPagesMap[$page['page_uuid']] as $sub) {
                    $subs[] = [
                        'title'  => $sub['label'],
                        'url'    => '/' . $language . '/' . ($sub['slug'] ?? $sub['page_uuid']),
                        'active' => ($sub['page_uuid'] === $pageId),
                        'type'   => 'sub',
                        'css'    => $sub['css'] ?? ''
                    ];
                }
            }

            $items[] = [
                'title'    => $page['label'],
                'url'      => '/' . $language . '/' . ($page['slug'] ?? $page['page_uuid']),
                'active'   => ($page['page_uuid'] === $pageId),
                'subpages' => $subs,
                'type'     => $page['type'] ?? 'main',
                'css'      => $page['css'] ?? ''
            ];
        }

        return $items;
    }
}