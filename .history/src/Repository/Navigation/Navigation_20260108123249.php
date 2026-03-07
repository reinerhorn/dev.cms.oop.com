<?php
declare(strict_types=1);

namespace CMS\Repository\Navigation;

use mysqli;

class Navigation
{
    private mysqli $db;
    private string $language;
    private string $context;

    public function __construct(
        mysqli $db,
        ?string $language,
        string $context
    ) {
        $this->db       = $db;
        $this->language = $language ?: 'de';
        $this->context  = $context;
    }

    /**
     * Liefert komplette Navigation (inkl. Subnavigation)
     */
    public function getItems(): array
    {
        $items = [];

        // ---------------------------------------------
        // 1) Top-Level Navigation laden (parent_id IS NULL)
        // ---------------------------------------------
        $stmt = $this->db->prepare("
            SELECT
                n.nav_uuid,
                n.parent_id,
                n.position,
                n.seo_slug,
                n.nav_align,
                n.sort_order,
                COALESCE(t_lang.label, t_de.label) AS label
            FROM navigation n
            LEFT JOIN translation t_lang
              ON t_lang.fk_translation_holder = n.fk_translation_placeholder
             AND t_lang.fk_language_id = ?
            LEFT JOIN translation t_de
              ON t_de.fk_translation_holder = n.fk_translation_placeholder
             AND t_de.fk_language_id = 'de'
            WHERE n.context_id = ?
              AND n.enabled = 1
              AND n.position = 'main'
              AND n.parent_id IS NULL
            ORDER BY n.sort_order ASC
        ");

        $stmt->bind_param('ss', $this->language, $this->context);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $items[] = $this->mapItem($row);
        }

        $stmt->close();

        // ---------------------------------------------
        // 2) Subnavigation anhängen
        // ---------------------------------------------
        foreach ($items as &$item) {
            $item['children'] = $this->getChildren($item['nav_uuid']);
        }
        unset($item);

        return $items;
    }

    /**
     * Lädt Subnavigation für einen Parent
     */
    private function getChildren(string $parentId): array
    {
        $children = [];

        $stmt = $this->db->prepare("
            SELECT
                n.nav_uuid,
                n.parent_id,
                n.position,
                n.seo_slug,
                n.nav_align,
                n.sort_order,
                COALESCE(t_lang.label, t_de.label) AS label
            FROM navigation n
            LEFT JOIN translation t_lang
              ON t_lang.fk_translation_holder = n.fk_translation_placeholder
             AND t_lang.fk_language_id = ?
            LEFT JOIN translation t_de
              ON t_de.fk_translation_holder = n.fk_translation_placeholder
             AND t_de.fk_language_id = 'de'
            WHERE n.context_id = ?
              AND n.enabled = 1
              AND n.position = 'main'
              AND n.parent_id = ?
            ORDER BY n.sort_order ASC
        ");

        $stmt->bind_param(
            'sss',
            $this->language,
            $this->context,
            $parentId
        );

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $children[] = $this->mapItem($row);
        }

        $stmt->close();

        return $children;
    }

    /**
     * Normalisiert einen Navigationseintrag
     */
    private function mapItem(array $row): array
    {
        return [
            'nav_uuid' => $row['nav_uuid'],
            'label'    => $row['label'] ?? '',
            'slug'     => $row['seo_slug'] ?? '',
            'align'    => $row['nav_align'] ?? 'left',
            'position' => $row['position'] ?? 'main',
            'children' => [],
        ];
    }
}