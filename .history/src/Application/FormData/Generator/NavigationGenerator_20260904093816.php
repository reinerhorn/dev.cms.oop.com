<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class NavigationGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Erstellt einen Navigationseintrag.
     *
     * Navigation-Modell:
     *
     * main
     * └── sub
     *     └── sub
     *         └── sub
     *
     * Die Tiefe wird ausschließlich über parent_id bestimmt.
     *
     * Erwartete Config:
     *
     * - page_uuid
     * - page_slug
     * - navigation_parent_id
     * - navigation_title
     * - navigation_slug
     * - navigation_context_id
     * - navigation_align
     * - navigation_permission_id
     * - navigation_auth_visibility
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        $navUuid = $this->uuid();

        /*
         * ---------------------------------------------------------
         * POSITION BESTIMMEN
         * ---------------------------------------------------------
         *
         * Kein Parent:
         *
         * parent_id = NULL
         * position  = main
         *
         * Parent vorhanden:
         *
         * parent_id = UUID des direkten Elternknotens
         * position  = sub
         */
        $parentId = $config['navigation_parent_id'];

        $position = $parentId === ''
            ? 'main'
            : 'sub';

        /*
         * ---------------------------------------------------------
         * PAGE UUID
         * ---------------------------------------------------------
         *
         * Der Navigationseintrag kann mit einer Page verbunden sein.
         */
        $pageUuid = $config['page_uuid'];

        if ($pageUuid === '') {
            $pageUuid = $config['page'];
        }

        /*
         * ---------------------------------------------------------
         * SEO SLUG
         * ---------------------------------------------------------
         */
        $seoSlug = $config['navigation_slug'];

        if ($seoSlug === '') {
            $seoSlug = $config['page_slug'];
        }

        /*
         * ---------------------------------------------------------
         * TRANSLATION PLACEHOLDER
         * ---------------------------------------------------------
         *
         * Falls keiner explizit angegeben wurde,
         * wird der Navigationstitel als technischer
         * Placeholder erzeugt.
         */
        $translationPlaceholder =
            $config['navigation_translation_placeholder'];

        if ($translationPlaceholder === '') {
            $translationPlaceholder =
                $this->buildTranslationPlaceholder(
                    $config['navigation_title']
                );
        }

        /*
         * ---------------------------------------------------------
         * SORT ORDER
         * ---------------------------------------------------------
         *
         * Falls kein sort_order angegeben wurde,
         * wird automatisch der nächste freie Wert
         * innerhalb desselben Parents bestimmt.
         */
        $sortOrder = $config['navigation_sort_order'];

        if ($sortOrder === null) {
            $sortOrder = $this->getNextSortOrder(
                $parentId,
                $config['navigation_context_id'],
                $position
            );
        }

        /*
         * ---------------------------------------------------------
         * INSERT
         * ---------------------------------------------------------
         */
        $stmt = $this->db->prepare("
            INSERT INTO navigation
            (
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
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'NavigationGenerator prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        /*
         * NULL muss als echte SQL-NULL gespeichert werden.
         */
        $parentIdValue = $parentId === ''
            ? null
            : $parentId;

        $pageUuidValue = $pageUuid === ''
            ? null
            : $pageUuid;

        $seoSlugValue = $seoSlug === ''
            ? null
            : $seoSlug;

        $enabled = $config['navigation_enabled'];

        $navAlign = $config['navigation_align'];

        $contextId = $config['navigation_context_id'];

        $permissionId =
            $config['navigation_permission_id'];

        $authVisibility =
            $config['navigation_auth_visibility'];

        $stmt->bind_param(
            'ssssssisssss',
            $navUuid,
            $parentIdValue,
            $position,
            $pageUuidValue,
            $seoSlugValue,
            $translationPlaceholder,
            $sortOrder,
            $enabled,
            $navAlign,
            $contextId,
            $permissionId,
            $authVisibility
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'NavigationGenerator konnte nicht speichern: '
                . $error
            );
        }

        $stmt->close();

        return [
            'success' => true,

            'nav_uuid' => $navUuid,

            'parent_id' => $parentIdValue,

            'position' => $position,

            'fk_page_uuid' => $pageUuidValue,

            'seo_slug' => $seoSlugValue,

            'translation_placeholder' =>
                $translationPlaceholder,

            'sort_order' => $sortOrder,

            'enabled' => (bool) $enabled,

            'nav_align' => $navAlign,

            'context_id' => $contextId,

            'required_permission_id' => $permissionId,

            'auth_visibility' => $authVisibility,
        ];
    }

    /**
     * Normalisiert die Konfiguration.
     */
    private function normalizeConfig(array $config): array
    {
        $defaults = [

            /*
             * Page
             */
            'page' => '',
            'page_uuid' => '',
            'page_slug' => '',

            /*
             * Navigation
             */
            'navigation_parent_id' => '',
            'navigation_title' => '',
            'navigation_slug' => '',

            /*
             * Translation
             */
            'navigation_translation_placeholder' => '',

            /*
             * Darstellung
             */
            'navigation_align' => 'left',
            'navigation_context_id' => 'admin',

            /*
             * Berechtigung
             */
            'navigation_permission_id' =>
                'perm-view-admin',

            /*
             * Sichtbarkeit
             */
            'navigation_auth_visibility' =>
                'public',

            /*
             * Status
             */
            'navigation_enabled' => true,

            /*
             * Reihenfolge
             */
            'navigation_sort_order' => null,
        ];

        $config = array_merge(
            $defaults,
            $config
        );

        /*
         * Strings normalisieren.
         */
        foreach (
            [
                'page',
                'page_uuid',
                'page_slug',

                'navigation_parent_id',
                'navigation_title',
                'navigation_slug',

                'navigation_translation_placeholder',

                'navigation_align',
                'navigation_context_id',

                'navigation_permission_id',

                'navigation_auth_visibility',
            ] as $field
        ) {
            $config[$field] = trim(
                (string) $config[$field]
            );
        }

        /*
         * Enabled normalisieren.
         */
        $config['navigation_enabled'] =
            $this->toBool(
                $config['navigation_enabled']
            );

        /*
         * Sort Order.
         */
        if (
            $config['navigation_sort_order'] === ''
            || $config['navigation_sort_order'] === null
        ) {
            $config['navigation_sort_order'] = null;
        } else {
            $config['navigation_sort_order'] =
                (int) $config['navigation_sort_order'];
        }

        return $config;
    }

    /**
     * Validiert die Navigation.
     */
    private function validateConfig(array $config): void
    {
        /*
         * Page ist optional für reine Dropdown-Knoten.
         *
         * Deshalb keine Pflichtprüfung auf page_uuid.
         */

        /*
         * Context.
         */
        if ($config['navigation_context_id'] === '') {
            throw new RuntimeException(
                'Navigation Context darf nicht leer sein.'
            );
        }

        /*
         * Parent prüfen.
         */
        if ($config['navigation_parent_id'] !== '') {
            if (
                !$this->navigationExists(
                    $config['navigation_parent_id']
                )
            ) {
                throw new RuntimeException(
                    'Übergeordneter Navigationseintrag '
                    . 'wurde nicht gefunden: '
                    . $config['navigation_parent_id']
                );
            }
        }

        /*
         * Page prüfen, wenn eine Page angegeben wurde.
         */
        $pageUuid = $config['page_uuid'];

        if ($pageUuid === '') {
            $pageUuid = $config['page'];
        }

        if (
            $pageUuid !== ''
            && !$this->pageExists($pageUuid)
        ) {
            throw new RuntimeException(
                'Page wurde nicht gefunden: '
                . $pageUuid
            );
        }
    }

    /**
     * Ermittelt den nächsten sort_order.
     *
     * Der Wert wird innerhalb desselben
     * Parent-Knotens berechnet.
     */
    private function getNextSortOrder(
        string $parentId,
        string $contextId,
        string $position
    ): int {
        if ($parentId === '') {
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(MAX(sort_order), -1) + 1
                FROM navigation
                WHERE parent_id IS NULL
                  AND context_id = ?
                  AND position = ?
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'Navigation sort_order prepare '
                    . 'fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                'ss',
                $contextId,
                $position
            );
        } else {
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(MAX(sort_order), -1) + 1
                FROM navigation
                WHERE parent_id = ?
                  AND context_id = ?
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'Navigation sort_order prepare '
                    . 'fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                'ss',
                $parentId,
                $contextId
            );
        }

        $stmt->execute();

        $stmt->bind_result($sortOrder);

        $stmt->fetch();

        $stmt->close();

        return (int) $sortOrder;
    }

    /**
     * Prüft, ob ein Navigationseintrag existiert.
     */
    private function navigationExists(
        string $navUuid
    ): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM navigation
            WHERE nav_uuid = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Navigation Exists prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $navUuid
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $exists =
            $result
            && $result->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Prüft, ob eine Page existiert.
     */
    private function pageExists(
        string $pageUuid
    ): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM page
            WHERE page_uuid = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Page Exists prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pageUuid
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $exists =
            $result
            && $result->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Erzeugt aus einem Navigationstitel
     * einen technischen Translation Placeholder.
     *
     * Beispiel:
     *
     * Header bearbeiten
     *     -> HEADER_BEARBEITEN
     */
    private function buildTranslationPlaceholder(
        string $title
    ): string {
        $title = trim($title);

        if ($title === '') {
            return 'NAVIGATION';
        }

        $title = preg_replace(
            '/[^a-zA-Z0-9]+/',
            '_',
            $title
        );

        return strtoupper(
            trim(
                (string) $title,
                '_'
            )
        );
    }

    /**
     * Konvertiert verschiedene Eingaben in Boolean.
     */
    private function toBool(
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

        return !empty($value);
    }

    /**
     * UUID v4.
     */
    private function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            (ord($data[6]) & 0x0f)
            | 0x40
        );

        $data[8] = chr(
            (ord($data[8]) & 0x3f)
            | 0x80
        );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(
                bin2hex($data),
                4
            )
        );
    }
}

