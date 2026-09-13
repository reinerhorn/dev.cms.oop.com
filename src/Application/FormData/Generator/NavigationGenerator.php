<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

final class NavigationGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt einen Navigationseintrag für eine bereits
     * vorhandene Page.
     *
     * navigation_parent_id:
     *
     *   ''       => main
     *   UUID     => sub
     *
     * Dadurch sind beliebig tiefe Navigationen möglich:
     *
     * Main
     * └── Sub
     *     └── Sub
     *         └── Sub
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $pageUuid = $this->stringValue(
            $config['page_uuid'] ?? ''
        );

        $pageSlug = $this->stringValue(
            $config['page_slug']
                ?? $config['slug']
                ?? ''
        );

        $parentId = $this->stringValue(
            $config['navigation_parent_id']
                ?? $config['parent_id']
                ?? ''
        );

        $navigationSlug = $this->stringValue(
            $config['navigation_slug'] ?? ''
        );

        $translationPlaceholder = $this->stringValue(
            $config['navigation_translation_placeholder'] ?? ''
        );

        $sortOrder = $this->nullableInt(
            $config['navigation_sort_order'] ?? null
        );

        $enabled = $this->boolValue(
            $config['navigation_enabled'] ?? true
        );

        $align = $this->stringValue(
            $config['navigation_align'] ?? 'left'
        );

        $contextId = $this->stringValue(
            $config['navigation_context_id'] ?? 'admin'
        );

        $permissionId = $this->stringValue(
            $config['navigation_permission_id']
                ?? 'perm-view-admin'
        );

        $authVisibility = $this->stringValue(
            $config['navigation_auth_visibility']
                ?? 'public'
        );

        /*
         * ---------------------------------------------------------
         * Validierung
         * ---------------------------------------------------------
         */

        if ($pageUuid === '') {
            throw new RuntimeException(
                'NavigationGenerator: Keine page_uuid angegeben.'
            );
        }

        if ($contextId === '') {
            throw new RuntimeException(
                'NavigationGenerator: Keine context_id angegeben.'
            );
        }

        if (!in_array(
            $align,
            ['left', 'right'],
            true
        )) {
            throw new RuntimeException(
                'NavigationGenerator: Ungültige Navigation-Ausrichtung.'
            );
        }

        if (!in_array(
            $authVisibility,
            ['public', 'guest', 'logged_in'],
            true
        )) {
            throw new RuntimeException(
                'NavigationGenerator: Ungültige auth_visibility.'
            );
        }

        if (
            $sortOrder !== null
            && $sortOrder < 0
        ) {
            throw new RuntimeException(
                'NavigationGenerator: sort_order darf nicht negativ sein.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Page laden
         * ---------------------------------------------------------
         *
         * Wir vertrauen nicht blind auf page_slug oder
         * translation_placeholder aus dem POST.
         */

        $page = $this->loadPage(
            $pageUuid
        );

        $pageSlug = $page['slug'];

        $translationPlaceholder =
            $page['fk_translation_placeholder'];

        /*
         * ---------------------------------------------------------
         * Parent prüfen
         * ---------------------------------------------------------
         */

        if ($parentId !== '') {
            $this->assertParentExists(
                $parentId,
                $contextId
            );
        }

        /*
         * ---------------------------------------------------------
         * Position bestimmen
         * ---------------------------------------------------------
         */

        $position = $parentId === ''
            ? 'main'
            : 'sub';

        /*
         * ---------------------------------------------------------
         * Navigation-Slug
         * ---------------------------------------------------------
         */

        if ($navigationSlug === '') {
            $navigationSlug = $pageSlug;
        }

        $this->assertSlugExists(
            $navigationSlug
        );

        /*
         * ---------------------------------------------------------
         * Doppelten Navigationseintrag verhindern
         * ---------------------------------------------------------
         */

        if ($this->navigationForPageExists(
            $pageUuid,
            $contextId
        )) {
            $existingNavigation =
                $this->loadNavigationForPage(
                    $pageUuid,
                    $contextId
                );

            return [
                'success' => true,
                'existing' => true,
                ...$existingNavigation,
            ];
        }

        /*
         * ---------------------------------------------------------
         * Sort Order
         * ---------------------------------------------------------
         */

        if ($sortOrder === null) {
            $sortOrder = $this->nextSortOrder(
                $parentId,
                $contextId
            );
        }

        /*
         * ---------------------------------------------------------
         * Navigation UUID
         * ---------------------------------------------------------
         */

        $navigationUuid = $this->uuidV4();

        /*
         * ---------------------------------------------------------
         * Insert
         * ---------------------------------------------------------
         */

        $this->db->begin_transaction();

        try {
            $this->insertNavigation(
                navigationUuid: $navigationUuid,
                parentId: $parentId !== ''
                    ? $parentId
                    : null,
                position: $position,
                pageUuid: $pageUuid,
                pageSlug: $pageSlug,
                translationPlaceholder: $translationPlaceholder,
                sortOrder: $sortOrder,
                enabled: $enabled,
                align: $align,
                contextId: $contextId,
                permissionId: $permissionId,
                authVisibility: $authVisibility
            );

            $this->db->commit();

            return [
                'success' => true,

                'navigation_uuid' =>
                    $navigationUuid,

                'parent_id' =>
                    $parentId !== ''
                        ? $parentId
                        : null,

                'position' =>
                    $position,

                'fk_page_uuid' =>
                    $pageUuid,

                'seo_slug' =>
                    $navigationSlug,

                'fk_translation_placeholder' =>
                    $translationPlaceholder,

                'sort_order' =>
                    $sortOrder,

                'enabled' =>
                    $enabled ? 1 : 0,

                'nav_align' =>
                    $align,

                'context_id' =>
                    $contextId,

                'required_permission_id' =>
                    $permissionId,

                'auth_visibility' =>
                    $authVisibility,
            ];
        } catch (Throwable $e) {
            $this->db->rollback();

            throw $e;
        }
    }

    /**
     * Lädt eine vorhandene Page.
     *
     * @return array<string,string>
     */
    private function loadPage(
        string $pageUuid
    ): array {
        $sql = '
            SELECT
                page_uuid,
                slug,
                fk_translation_placeholder
            FROM page
            WHERE page_uuid = ?
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Page-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pageUuid
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Page-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        if (
            $result === false
            || $result->num_rows === 0
        ) {
            $stmt->close();

            throw new RuntimeException(
                sprintf(
                    'NavigationGenerator: Page "%s" wurde nicht gefunden.',
                    $pageUuid
                )
            );
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return [
            'page_uuid' =>
                (string) $row['page_uuid'],

            'slug' =>
                (string) $row['slug'],

            'fk_translation_placeholder' =>
                (string) (
                    $row['fk_translation_placeholder']
                    ?? ''
                ),
        ];
    }

    /**
     * Prüft, ob ein Parent existiert.
     *
     * Parent darf aus beliebiger Navigationsebene stammen.
     */
    private function assertParentExists(
        string $parentId,
        string $contextId
    ): void {
        $sql = '
            SELECT nav_uuid
            FROM navigation
            WHERE nav_uuid = ?
              AND context_id = ?
              AND enabled = 1
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Parent-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $parentId,
            $contextId
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Parent-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $exists =
            $result !== false
            && $result->num_rows > 0;

        $stmt->close();

        if (!$exists) {
            throw new RuntimeException(
                sprintf(
                    'NavigationGenerator: Parent-Navigation "%s" wurde im Kontext "%s" nicht gefunden.',
                    $parentId,
                    $contextId
                )
            );
        }
    }

    /**
     * Prüft Slug Placeholder.
     */
    private function assertSlugExists(
        string $slug
    ): void {
        $sql = '
            SELECT id
            FROM slug_placeholder
            WHERE id = ?
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Slug-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $slug
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Slug-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $exists =
            $result !== false
            && $result->num_rows > 0;

        $stmt->close();

        if (!$exists) {
            throw new RuntimeException(
                sprintf(
                    'NavigationGenerator: Slug-Placeholder "%s" existiert nicht.',
                    $slug
                )
            );
        }
    }

    /**
     * Prüft, ob für eine Page bereits Navigation existiert.
     */
    private function navigationForPageExists(
        string $pageUuid,
        string $contextId
    ): bool {
        $sql = '
            SELECT nav_uuid
            FROM navigation
            WHERE fk_page_uuid = ?
              AND context_id = ?
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Navigation-Prüfung fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $pageUuid,
            $contextId
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Navigation-Prüfung fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $exists =
            $result !== false
            && $result->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Ermittelt den nächsten Sortierwert unterhalb
     * desselben Parents.
     */
    private function nextSortOrder(
        string $parentId,
        string $contextId
    ): int {
        if ($parentId === '') {
            $sql = '
                SELECT COALESCE(
                    MAX(sort_order),
                    -1
                ) + 1 AS next_sort
                FROM navigation
                WHERE parent_id IS NULL
                  AND context_id = ?
            ';

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException(
                    'NavigationGenerator: Prepare Sort-Abfrage fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                's',
                $contextId
            );
        } else {
            $sql = '
                SELECT COALESCE(
                    MAX(sort_order),
                    -1
                ) + 1 AS next_sort
                FROM navigation
                WHERE parent_id = ?
                  AND context_id = ?
            ';

            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException(
                    'NavigationGenerator: Prepare Sort-Abfrage fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                'ss',
                $parentId,
                $contextId
            );
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Sort-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        if ($result === false) {
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Sort-Ergebnis konnte nicht gelesen werden.'
            );
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return (int) (
            $row['next_sort'] ?? 0
        );
    }

    /**
     * Fügt Navigation ein.
     */
    private function insertNavigation(
        string $navigationUuid,
        ?string $parentId,
        string $position,
        string $pageUuid,
        string $pageSlug,
        string $translationPlaceholder,
        int $sortOrder,
        bool $enabled,
        string $align,
        string $contextId,
        string $permissionId,
        string $authVisibility
    ): void {
        $sql = '
            INSERT INTO navigation (
                nav_uuid,
                parent_id,
                position,
                fk_page_uuid,
                seo_slug,
                fk_translation_placeholder,
                sort_order,
                enabled,
                nav_align,
                context_id,
                required_permission_id,
                auth_visibility
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Navigation-Insert fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ssssssiissss',
            $navigationUuid,
            $parentId,
            $position,
            $pageUuid,
            $pageSlug,
            $translationPlaceholder,
            $sortOrder,
            $enabled,
            $align,
            $contextId,
            $permissionId,
            $authVisibility
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Navigation konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Lädt den vorhandenen Navigationseintrag einer Page.
     *
     * @return array<string,mixed>
     */
    private function loadNavigationForPage(
        string $pageUuid,
        string $contextId
    ): array {
        $sql = '
            SELECT
                nav_uuid,
                parent_id,
                position,
                fk_page_uuid,
                seo_slug,
                fk_translation_placeholder,
                sort_order,
                enabled,
                nav_align,
                context_id,
                required_permission_id,
                auth_visibility
            FROM navigation
            WHERE fk_page_uuid = ?
              AND context_id = ?
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator: Prepare Navigation-Laden fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $pageUuid,
            $contextId
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Navigation-Laden fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        if (
            $result === false
            || $result->num_rows === 0
        ) {
            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator: Navigation wurde nach der Existenzprüfung nicht gefunden.'
            );
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        if (!is_array($row)) {
            throw new RuntimeException(
                'NavigationGenerator: Ungültige Navigation-Daten.'
            );
        }

        return $row;
    }

    /**
     * Normalisiert String.
     */
    private function stringValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim(
                (string) $value
            );
        }

        return '';
    }

    /**
     * Normalisiert Boolean.
     */
    private function boolValue(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                [
                    '1',
                    'true',
                    'yes',
                    'ja',
                    'on',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Nullable Integer.
     */
    private function nullableInt(
        mixed $value
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^-?\d+$/',
                trim($value)
            )
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            'NavigationGenerator: Ungültiger Sortierwert.'
        );
    }

    /**
     * UUID v4.
     */
    private function uuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
        );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}
