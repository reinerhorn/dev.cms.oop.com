<?php
declare(strict_types=1);

namespace CMS\Repository\Navigation;

class Navigation
{
    private \mysqli $db;
    private string $language;
    private string $roleId;
    private string $context;

    public function __construct(
        \mysqli $db,
        string $language,
        string $roleId,
        string $context
    ) {
        $this->db        = $db;
        $this->language  = $language;
        $this->roleId = $roleId;
        $this->context   = $context;
    }

    /**
     * Einstiegspunkt
     */
    public function getItems(): array
    {
        return $this->getChildren(null, []);
    }

    /**
     * Rekursive Navigation
     */
    private function getChildren(?string $parentUuid, array $visited): array
    {
        if ($parentUuid !== null) {
            if (isset($visited[$parentUuid])) {
                return [];
            }
            $visited[$parentUuid] = true;
        }
        $sql = "
            SELECT
                n.nav_uuid,
                n.parent_id,
                n.fk_page_uuid,
                n.seo_slug,
                n.nav_align,
                COALESCE(t.label, n.fk_translation_placeholder) AS label
            FROM navigation n
            LEFT JOIN translation t
              ON t.fk_translation_holder = n.fk_translation_placeholder
             AND t.fk_language_id = ?
            WHERE n.enabled = 1
              AND n.context_id = ?
              AND (
                    n.visible_from_role IS NULL
                    OR n.visible_from_role = ?
                  )
              AND n.parent_id <=> ?
            ORDER BY n.sort_order ASC
        ";

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param(
            'ssss',
            $this->language,
            $this->context,
            $this->roleId,
            $parentUuid
        );

        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $items = [];

        foreach ($rows as $row) {
            $children = $this->getChildren($row['nav_uuid'], $visited);

            $items[] = [
                'tsid'         => $row['nav_uuid'],
                'title'        => $row['label'],
                'url'          => $this->buildUrl($row),
                'children'     => $children,
                'is_container' => !empty($children),
                'align'        => $row['nav_align'] ?? 'left',
            ];
        }

        return $items;
    }

    private function buildUrl(array $row): string
    {
        if (!empty($row['seo_slug'])) {
            return '/' . $this->language . '/' . ltrim($row['seo_slug'], '/');
        }

        if (!empty($row['fk_page_uuid'])) {
            return '/' . $this->language . '/page/' . $row['fk_page_uuid'];
        }

        return '#';
    }
}