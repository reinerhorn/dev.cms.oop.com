<?php
declare(strict_types=1);

namespace CMS\Repository\Navigation;

use mysqli;
use CMS\Core\Service\RoleService;

class Navigation
{
    private mysqli $db;
    private string $language;
    private string $context;
    private RoleService $roleService;
    private string $roleId;

    public function __construct(
        mysqli $db,
        ?string $language,
        string $context,
        RoleService $roleService,
        string $roleId
    ) {
        $this->db       = $db;
        $this->language = $language ?: 'de';
        $this->context  = $context;
        $this->roleService = $roleService;
        $this->roleId      = $roleId;
    }

    private function getAudience(): string
    {
        if (!$this->roleId || $this->roleId === 'guest-role-000') {
            return 'public';
        }
        return explode('-', $this->roleId, 2)[0];
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
                n.required_permission_id,
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
              AND EXISTS (
                  SELECT 1
                  FROM view_context_audience v
                  WHERE v.context_id = n.context_id
                    AND v.audience COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
              )
            ORDER BY n.sort_order ASC
        ");

        $audience = $this->getAudience();
        $stmt->bind_param('sss', $this->language, $this->context, $audience);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($this->isAllowed($row['required_permission_id'])) {
                $items[] = $this->mapItem($row);
            }
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
                n.required_permission_id,
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
              AND n.parent_id = ?
              AND EXISTS (
                  SELECT 1
                  FROM view_context_audience v
                  WHERE v.context_id = n.context_id
                    AND v.audience COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
              )
            ORDER BY n.sort_order ASC
        ");

        $audience = $this->getAudience();
        $stmt->bind_param(
            'ssss',
            $this->language,
            $this->context,
            $parentId,
            $audience
        );

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            if ($this->isAllowed($row['required_permission_id'])) {
                $children[] = $this->mapItem($row);
            }
        }

        $stmt->close();

        return $children;
    }

    /**
     * Normalisiert einen Navigationseintrag für Twig
     * Twig erwartet: title, url, align, children
     */
    private function mapItem(array $row): array
    {
        $slug = $row['seo_slug'] ?? '';

        return [
            'nav_uuid' => $row['nav_uuid'],

            // Twig-kompatibel
            'title' => $row['label'] ?? '',

            // URL mit Sprachprefix
            'url' => $slug !== ''
                ? '/' . $this->language . '/' . $slug
                : '#',

            'align'    => $row['nav_align'] ?? 'left',
            'position' => $row['position'] ?? 'main',

            // Active-State (optional, später erweiterbar)
            'active' => false,

            'children' => [],
            'is_logout' => ($row['seo_slug'] === null || $row['seo_slug'] === ''),
        ];
    }

    private function isAllowed(?string $requiredPermissionId): bool
    {
        // Kein Permission-Eintrag → immer sichtbar
        if ($requiredPermissionId === null || $requiredPermissionId === '') {
            return true;
        }

        return $this->roleService->roleHasPermission(
            $this->roleId,
            $requiredPermissionId
        );
    }
}