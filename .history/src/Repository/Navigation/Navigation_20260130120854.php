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
            WHERE n.context_id IN (?, 'global')
              AND n.enabled = 1
              AND n.position = 'main'
              AND n.parent_id IS NULL
            ORDER BY n.sort_order ASC
        ");

        $stmt->bind_param('ss', $this->language, $this->context);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            // ⛔ Logout NIE für Gäste ausliefern
            if (
                ($row['required_permission_id'] ?? '') === 'perm-can-logout'
                && ($this->roleId === '' || $this->roleId === 'guest-role-000')
            ) {
                continue;
            }

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
            WHERE n.context_id IN (?, 'global')
              AND n.enabled = 1
              AND n.parent_id = ?
            ORDER BY n.sort_order ASC
        ");

        $stmt->bind_param('sss', $this->language, $this->context, $parentId);

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
                : null,

            'align'    => $row['nav_align'] ?? 'left',
            'position' => $row['position'] ?? 'main',

            // Active-State (optional, später erweiterbar)
            'active' => false,

            'children' => [],
            'is_logout' => ($row['required_permission_id'] === 'perm-can-logout'),
        ];
    }

    private function isAllowed(?string $requiredPermissionId): bool
    {
        // Keine Permission definiert → immer sichtbar
        if ($requiredPermissionId === null || $requiredPermissionId === '') {
            return true;
        }

        // Explizit für Gäste freigegeben
        if ($requiredPermissionId === 'perm-view-guest') {
            return true;
        }

        // Logout: nur für eingeloggte Rollen mit Permission
        if ($requiredPermissionId === 'perm-can-logout') {
            if ($this->roleId === '' || $this->roleId === 'guest-role-000') {
                return false;
            }

            return $this->roleService->roleHasPermission(
                $this->roleId,
                'perm-can-logout'
            );
        }

        // Alle anderen Permissions: Rolle MUSS existieren
        if ($this->roleId === '' || $this->roleId === 'guest-role-000') {
            return false;
        }

        return $this->roleService->roleHasPermission(
            $this->roleId,
            $requiredPermissionId
        );
    }
}