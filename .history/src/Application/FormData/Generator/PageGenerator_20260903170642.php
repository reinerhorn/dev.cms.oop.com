<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class PageGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Erstellt eine neue Page inklusive:
     *
     * - translation_placeholder
     * - Übersetzungen DE / EN / FR
     * - optional slug_placeholder
     * - optional Navigation
     *
     * Navigation:
     *
     * main = oberste Ebene, parent_id NULL
     * sub  = beliebige Unterebene, parent_id zeigt
     *        immer auf den direkten Elternknoten.
     */
    public function generate(array $config): array
    {
        $table = trim((string) ($config['table'] ?? ''));
        $saveKey = trim((string) ($config['save_key'] ?? ''));

        $slugMode = trim(
            (string) ($config['slug_mode'] ?? 'existing')
        );

        $slug = trim(
            (string) ($config['slug'] ?? '')
        );

        $newSlug = trim(
            (string) ($config['new_slug'] ?? '')
        );

        $navigationMode = trim(
            (string) ($config['navigation_mode'] ?? 'none')
        );

        $navigationParentId = trim(
            (string) ($config['navigation_parent_id'] ?? '')
        );

        /*
         * Tabelle prüfen.
         */
        if ($table === '') {
            throw new RuntimeException(
                'PageGenerator: Keine Tabelle angegeben.'
            );
        }

        /*
         * Entity bestimmen.
         */
        $entity = trim(
            (string) ($config['entity'] ?? '')
        );

        if ($entity === '') {
            $entity = $this->tableToEntity($table);
        }

        /*
         * Translation Placeholder erzeugen.
         *
         * Beispiel:
         *
         * Address
         * ↓
         * ADDRESS
         */
        $translationPlaceholder =
            $this->buildTranslationPlaceholder($entity);

        /*
         * Translation Placeholder darf noch nicht existieren.
         */
        if (
            $this->translationPlaceholderExists(
                $translationPlaceholder
            )
        ) {
            throw new RuntimeException(
                'Translation Placeholder existiert bereits: '
                . $translationPlaceholder
            );
        }

        /*
         * Slug bestimmen.
         */
        $pageSlug = $this->resolveSlug(
            $slugMode,
            $slug,
            $newSlug
        );

        /*
         * Page mit diesem Slug darf noch nicht existieren.
         */
        if ($this->pageSlugExists($pageSlug)) {
            throw new RuntimeException(
                'Page mit Slug existiert bereits: '
                . $pageSlug
            );
        }

        /*
         * Page UUID.
         */
        $pageUuid = $this->uuid();

        /*
         * Page Name.
         *
         * Beispiel:
         *
         * ADDRESS
         */
        $pageName = strtoupper($entity);

        /*
         * Page erstellen.
         */
        $this->createPage(
            $pageUuid,
            $pageSlug,
            $pageName,
            $translationPlaceholder
        );

        /*
         * Übersetzungen erstellen.
         */
        $translations = $this->createTranslations(
            $translationPlaceholder,
            $entity
        );

        /*
         * Navigation optional erstellen.
         */
        $navigation = null;

        if ($navigationMode !== 'none') {
            $navigation = $this->createNavigation(
                $pageUuid,
                $pageSlug,
                $translationPlaceholder,
                $navigationMode,
                $navigationParentId !== ''
                    ? $navigationParentId
                    : null
            );
        }

        return [
            'success' => true,

            'page_uuid' => $pageUuid,

            'slug' => $pageSlug,

            'name' => $pageName,

            'required_permission_id' => 'perm-view-admin',

            'page_css_id' => 'admin',

            'template' => 'default',

            'translation_placeholder' =>
                $translationPlaceholder,

            'translations' => $translations,

            'slug_mode' => $slugMode,

            'slug_placeholder' => $pageSlug,

            'table' => $table,

            'save_key' => $saveKey,

            'entity' => $entity,

            'navigation' => $navigation,

            'navigation_mode' => $navigationMode,

            'navigation_parent_id' =>
                $navigationParentId !== ''
                    ? $navigationParentId
                    : null,
        ];
    }

    /**
     * Ermittelt den Page-Slug.
     *
     * existing:
     *   vorhandenen slug_placeholder verwenden
     *
     * new:
     *   slug_placeholder bei Bedarf erzeugen
     */
    private function resolveSlug(
        string $slugMode,
        string $slug,
        string $newSlug
    ): string {
        if ($slugMode === 'existing') {
            if ($slug === '') {
                throw new RuntimeException(
                    'PageGenerator: Kein vorhandener Slug angegeben.'
                );
            }

            if (!$this->slugPlaceholderExists($slug)) {
                throw new RuntimeException(
                    'PageGenerator: Slug Placeholder nicht gefunden: '
                    . $slug
                );
            }

            return $slug;
        }

        if ($slugMode === 'new') {
            if ($newSlug === '') {
                throw new RuntimeException(
                    'PageGenerator: Kein neuer Slug angegeben.'
                );
            }

            if (!$this->slugPlaceholderExists($newSlug)) {
                $this->createSlugPlaceholder($newSlug);
            }

            return $newSlug;
        }

        throw new RuntimeException(
            'PageGenerator: Ungültiger slug_mode: '
            . $slugMode
        );
    }

    /**
     * Erstellt die Page.
     */
    private function createPage(
        string $pageUuid,
        string $slug,
        string $name,
        string $translationPlaceholder
    ): void {
        $permission = 'perm-view-admin';

        $pageCssId = 'admin';

        $template = 'default';

        $enabled = 1;

        $sortOrder = 0;

        $stmt = $this->db->prepare("
            INSERT INTO page
            (
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                enabled,
                sort_order
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator Page prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssssii',
            $pageUuid,
            $slug,
            $name,
            $permission,
            $pageCssId,
            $translationPlaceholder,
            $template,
            $enabled,
            $sortOrder
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Page konnte nicht gespeichert werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Erstellt DE / EN / FR Übersetzungen.
     */
    private function createTranslations(
        string $translationPlaceholder,
        string $entity
    ): array {
        $labels = [
            'de' => $this->buildGermanLabel($entity),
            'en' => $this->buildEnglishLabel($entity),
            'fr' => $this->buildFrenchLabel($entity),
        ];

        $created = [];

        foreach ($labels as $languageId => $label) {
            $translationUuid = $this->uuid();

            $stmt = $this->db->prepare("
                INSERT INTO translation
                (
                    translation_uuid,
                    fk_translation_holder,
                    fk_language_id,
                    label
                )
                VALUES (?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'PageGenerator Translation prepare '
                    . 'fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                'ssss',
                $translationUuid,
                $translationPlaceholder,
                $languageId,
                $label
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;

                $stmt->close();

                throw new RuntimeException(
                    'PageGenerator Translation konnte '
                    . 'nicht gespeichert werden: '
                    . $error
                );
            }

            $stmt->close();

            $created[] = [
                'translation_uuid' => $translationUuid,
                'language_id' => $languageId,
                'label' => $label,
            ];
        }

        return $created;
    }

    /**
     * Erstellt einen neuen Slug Placeholder.
     */
    private function createSlugPlaceholder(
        string $slug
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO slug_placeholder
            (
                id
            )
            VALUES (?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator Slug Placeholder prepare '
                . 'fehlgeschlagen: '
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
                'PageGenerator Slug Placeholder konnte '
                . 'nicht gespeichert werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Erstellt einen Navigationseintrag.
     *
     * main:
     *
     * parent_id = NULL
     * position  = main
     *
     * sub:
     *
     * parent_id = direkter Elternknoten
     * position  = sub
     *
     * Dadurch sind beliebig tiefe Ebenen möglich:
     *
     * Main
     * └── Sub
     *     └── Sub
     *         └── Sub
     */
    private function createNavigation(
        string $pageUuid,
        string $slug,
        string $translationPlaceholder,
        string $navigationMode,
        ?string $parentId
    ): array {
        if (!in_array(
            $navigationMode,
            ['main', 'sub'],
            true
        )) {
            throw new RuntimeException(
                'PageGenerator: Ungültiger navigation_mode: '
                . $navigationMode
            );
        }

        /*
         * Main hat keinen Parent.
         */
        if ($navigationMode === 'main') {
            $parentId = null;
        }

        /*
         * Sub benötigt immer einen Parent.
         */
        if ($navigationMode === 'sub') {
            if ($parentId === null || $parentId === '') {
                throw new RuntimeException(
                    'PageGenerator: Für eine Sub-Navigation '
                    . 'muss ein übergeordnetes Element '
                    . 'angegeben werden.'
                );
            }

            if (!$this->navigationExists($parentId)) {
                throw new RuntimeException(
                    'PageGenerator: Übergeordnetes '
                    . 'Navigationselement nicht gefunden: '
                    . $parentId
                );
            }
        }

        $navUuid = $this->uuid();

        $position =
            $navigationMode === 'main'
                ? 'main'
                : 'sub';

        /*
         * Dein bestehender Admin-Kontext.
         */
        $contextId = 'admin';

        $permission = 'perm-view-admin';

        $authVisibility = 'public';

        $navAlign = 'left';

        $enabled = 1;

        /*
         * Sortierung wird innerhalb des jeweiligen
         * Parent-Knotens automatisch fortgeführt.
         */
        $sortOrder = $this->nextNavigationSortOrder(
            $parentId
        );

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
                'PageGenerator Navigation prepare '
                . 'fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ssssssiissss',
            $navUuid,
            $parentId,
            $position,
            $pageUuid,
            $slug,
            $translationPlaceholder,
            $sortOrder,
            $enabled,
            $navAlign,
            $contextId,
            $permission,
            $authVisibility
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Navigation konnte '
                . 'nicht gespeichert werden: '
                . $error
            );
        }

        $stmt->close();

        return [
            'nav_uuid' => $navUuid,

            'parent_id' => $parentId,

            'position' => $position,

            'fk_page_uuid' => $pageUuid,

            'seo_slug' => $slug,

            'fk_translation_placeholder' =>
                $translationPlaceholder,

            'sort_order' => $sortOrder,

            'context_id' => $contextId,
        ];
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
                'PageGenerator Navigation Exists '
                . 'prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $navUuid
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Navigation Exists '
                . 'konnte nicht ausgeführt werden: '
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
     * Ermittelt die nächste Sortierung.
     *
     * Wichtig:
     *
     * Nicht global zählen.
     *
     * Jeder Parent bekommt seine eigene Reihenfolge.
     *
     * Beispiel:
     *
     * Main A
     * ├── Sub 0
     * ├── Sub 1
     * └── Sub 2
     *
     * Main B
     * ├── Sub 0
     * └── Sub 1
     */
    private function nextNavigationSortOrder(
        ?string $parentId
    ): int {
        if ($parentId === null) {
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(MAX(sort_order), -1) + 1
                    AS next_sort_order
                FROM navigation
                WHERE parent_id IS NULL
                  AND context_id = 'admin'
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'PageGenerator Navigation Sort '
                    . 'prepare fehlgeschlagen: '
                    . $this->db->error
                );
            }
        } else {
            $stmt = $this->db->prepare("
                SELECT
                    COALESCE(MAX(sort_order), -1) + 1
                    AS next_sort_order
                FROM navigation
                WHERE parent_id = ?
                  AND context_id = 'admin'
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'PageGenerator Navigation Sort '
                    . 'prepare fehlgeschlagen: '
                    . $this->db->error
                );
            }

            $stmt->bind_param(
                's',
                $parentId
            );
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Navigation Sort '
                . 'konnte nicht ermittelt werden: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $row =
            $result !== false
                ? $result->fetch_assoc()
                : null;

        $stmt->close();

        return (int) (
            $row['next_sort_order'] ?? 0
        );
    }

    /**
     * Prüft einen Translation Placeholder.
     */
    private function translationPlaceholderExists(
        string $placeholder
    ): bool {
        return $this->exists(
            'translation_placeholder',
            'id',
            $placeholder
        );
    }

    /**
     * Prüft einen Slug Placeholder.
     */
    private function slugPlaceholderExists(
        string $slug
    ): bool {
        return $this->exists(
            'slug_placeholder',
            'id',
            $slug
        );
    }

    /**
     * Prüft, ob bereits eine Page mit dem Slug existiert.
     */
    private function pageSlugExists(
        string $slug
    ): bool {
        return $this->exists(
            'page',
            'slug',
            $slug
        );
    }

    /**
     * Allgemeine Existenzprüfung.
     *
     * Tabellen- und Spaltennamen kommen ausschließlich
     * aus dem festen Code dieser Klasse.
     */
    private function exists(
        string $table,
        string $column,
        string $value
    ): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$table}
            WHERE {$column} = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator Exists prepare '
                . 'fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $value
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Exists konnte '
                . 'nicht ausgeführt werden: '
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
     * Tabelle → Entity.
     *
     * address
     * → Address
     *
     * customer_address
     * → CustomerAddress
     */
    private function tableToEntity(
        string $table
    ): string {
        $table = preg_replace(
            '/[^a-zA-Z0-9_]/',
            '',
            $table
        );

        $parts = preg_split(
            '/[_-]+/',
            $table
        );

        $parts = array_filter(
            $parts,
            static fn ($part) =>
                $part !== ''
        );

        if (!$parts) {
            return 'Page';
        }

        return implode(
            '',
            array_map(
                static fn ($part) =>
                    ucfirst(strtolower($part)),
                $parts
            )
        );
    }

    /**
     * Entity → Translation Placeholder.
     *
     * CustomerAddress
     * → CUSTOMER_ADDRESS
     */
    private function buildTranslationPlaceholder(
        string $entity
    ): string {
        return strtoupper(
            preg_replace(
                '/([a-z])([A-Z])/',
                '$1_$2',
                $entity
            )
        );
    }

    /**
     * Deutsches Label.
     */
    private function buildGermanLabel(
        string $entity
    ): string {
        $map = [
            'Address' => 'Adressen',
            'User' => 'Benutzer',
            'Product' => 'Produkte',
            'Order' => 'Bestellungen',
            'Customer' => 'Kunden',
        ];

        return $map[$entity]
            ?? $this->humanize($entity);
    }

    /**
     * Englisches Label.
     */
    private function buildEnglishLabel(
        string $entity
    ): string {
        $map = [
            'Address' => 'Addresses',
            'User' => 'Users',
            'Product' => 'Products',
            'Order' => 'Orders',
            'Customer' => 'Customers',
        ];

        return $map[$entity]
            ?? $this->humanize($entity);
    }

    /**
     * Französisches Label.
     */
    private function buildFrenchLabel(
        string $entity
    ): string {
        $map = [
            'Address' => 'Adresses',
            'User' => 'Utilisateurs',
            'Product' => 'Produits',
            'Order' => 'Commandes',
            'Customer' => 'Clients',
        ];

        return $map[$entity]
            ?? $this->humanize($entity);
    }

    /**
     * Macht aus einer Entity einen lesbaren Fallback.
     */
    private function humanize(
        string $value
    ): string {
        $value = preg_replace(
            '/([a-z])([A-Z])/',
            '$1 $2',
            $value
        );

        return ucfirst(
            strtolower($value)
        );
    }

    /**
     * UUID v4.
     */
    private function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
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
