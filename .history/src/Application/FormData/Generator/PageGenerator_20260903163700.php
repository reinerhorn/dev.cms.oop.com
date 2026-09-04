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
     * - translation DE/EN/FR
     * - optional slug_placeholder
     */
    public function generate(array $config): array
    {
        $table = trim((string) ($config['table'] ?? ''));
        $saveKey = trim((string) ($config['save_key'] ?? ''));

        $slugMode = trim((string) ($config['slug_mode'] ?? 'existing'));
        $slug = trim((string) ($config['slug'] ?? ''));
        $newSlug = trim((string) ($config['new_slug'] ?? ''));

        if ($table === '') {
            throw new RuntimeException(
                'PageGenerator: Keine Tabelle angegeben.'
            );
        }

        /*
         * Entity bestimmen.
         */
        $entity = trim((string) ($config['entity'] ?? ''));

        if ($entity === '') {
            $entity = $this->tableToEntity($table);
        }

        /*
         * Technischer Translation Placeholder.
         *
         * Beispiel:
         * ADDRESS
         */
        $translationPlaceholder = $this->buildTranslationPlaceholder($entity);

        /*
         * Prüfen, ob der Placeholder bereits existiert.
         */
        if ($this->translationPlaceholderExists($translationPlaceholder)) {
            throw new RuntimeException(
                'Translation Placeholder existiert bereits: '
                . $translationPlaceholder
            );
        }

        /*
         * Slug bestimmen.
         */
        if ($slugMode === 'existing') {
            if ($slug === '') {
                throw new RuntimeException(
                    'PageGenerator: Kein vorhandener Slug angegeben.'
                );
            }

            $pageSlug = $slug;

            /*
             * Der vorhandene Slug muss existieren.
             */
            if (!$this->slugPlaceholderExists($pageSlug)) {
                throw new RuntimeException(
                    'PageGenerator: Slug Placeholder nicht gefunden: '
                    . $pageSlug
                );
            }
        } elseif ($slugMode === 'new') {
            if ($newSlug === '') {
                throw new RuntimeException(
                    'PageGenerator: Kein neuer Slug angegeben.'
                );
            }

            $pageSlug = $newSlug;

            /*
             * Neuen Slug Placeholder nur erzeugen,
             * wenn er noch nicht existiert.
             */
            if (!$this->slugPlaceholderExists($pageSlug)) {
                $this->createSlugPlaceholder($pageSlug);
            }
        } else {
            throw new RuntimeException(
                'PageGenerator: Ungültiger slug_mode: ' . $slugMode
            );
        }

        /*
         * Prüfen, ob die Page mit diesem Slug bereits existiert.
         */
        if ($this->pageSlugExists($pageSlug)) {
            throw new RuntimeException(
                'PageGenerator: Page mit Slug existiert bereits: '
                . $pageSlug
            );
        }

        /*
         * Page UUID.
         */
        $pageUuid = $this->uuid();

        /*
         * Page-Name.
         *
         * Beispiel:
         * ADDRESS
         */
        $pageName = strtoupper($entity);

        /*
         * Page erzeugen.
         */
        $this->createPage(
            $pageUuid,
            $pageSlug,
            $pageName,
            $translationPlaceholder
        );

        /*
         * Übersetzungen erzeugen.
         */
        $translations = $this->createTranslations(
            $translationPlaceholder,
            $entity
        );

        return [
            'success' => true,

            'page_uuid' => $pageUuid,
            'slug' => $pageSlug,
            'name' => $pageName,

            'required_permission_id' => 'perm-view-admin',
            'page_css_id' => 'admin',
            'template' => 'default',

            'translation_placeholder' => $translationPlaceholder,

            'translations' => $translations,

            'slug_mode' => $slugMode,
            'slug_placeholder' => $pageSlug,

            'table' => $table,
            'save_key' => $saveKey,
            'entity' => $entity,
        ];
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

        $permission = 'perm-view-admin';
        $pageCssId = 'admin';
        $template = 'default';
        $enabled = 1;
        $sortOrder = 0;

        $stmt->bind_param(
            'sssssssis',
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

        /*
         * Hinweis:
         * sort_order ist INTEGER.
         *
         * Daher verwenden wir unten die korrekte
         * bind_param-Signatur nochmals explizit.
         */
        $stmt->close();

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
                    'PageGenerator Translation prepare fehlgeschlagen: '
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
                    'PageGenerator Translation konnte nicht gespeichert werden: '
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
    private function createSlugPlaceholder(string $slug): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO slug_placeholder
            (
                id
            )
            VALUES (?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator Slug Placeholder prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param('s', $slug);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageGenerator Slug Placeholder konnte nicht gespeichert werden: '
                . $error
            );
        }

        $stmt->close();
    }

    private function translationPlaceholderExists(
        string $placeholder
    ): bool {
        return $this->exists(
            'translation_placeholder',
            'id',
            $placeholder
        );
    }

    private function slugPlaceholderExists(
        string $slug
    ): bool {
        return $this->exists(
            'slug_placeholder',
            'id',
            $slug
        );
    }

    private function pageSlugExists(
        string $slug
    ): bool {
        return $this->exists(
            'page',
            'slug',
            $slug
        );
    }

    private function exists(
        string $table,
        string $column,
        string $value
    ): bool {
        /*
         * Tabellen- und Spaltennamen sind hier fest im Code.
         */
        $stmt = $this->db->prepare("
            SELECT 1
            FROM {$table}
            WHERE {$column} = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator Exists prepare fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param('s', $value);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * ADDRESS
     * USER
     * PRODUCT
     */
    private function tableToEntity(string $table): string
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        $parts = preg_split('/[_-]+/', $table);

        $parts = array_filter(
            $parts,
            static fn ($part) => $part !== ''
        );

        if (!$parts) {
            return 'Page';
        }

        return implode(
            '',
            array_map(
                static fn ($part) => ucfirst(strtolower($part)),
                $parts
            )
        );
    }

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

    private function buildGermanLabel(string $entity): string
    {
        $map = [
            'Address' => 'Adressen',
            'User' => 'Benutzer',
            'Product' => 'Produkte',
            'Order' => 'Bestellungen',
            'Customer' => 'Kunden',
        ];

        return $map[$entity] ?? $this->humanize($entity);
    }

    private function buildEnglishLabel(string $entity): string
    {
        $map = [
            'Address' => 'Addresses',
            'User' => 'Users',
            'Product' => 'Products',
            'Order' => 'Orders',
            'Customer' => 'Customers',
        ];

        return $map[$entity] ?? $this->humanize($entity);
    }

    private function buildFrenchLabel(string $entity): string
    {
        $map = [
            'Address' => 'Adresses',
            'User' => 'Utilisateurs',
            'Product' => 'Produits',
            'Order' => 'Commandes',
            'Customer' => 'Clients',
        ];

        return $map[$entity] ?? $this->humanize($entity);
    }

    private function humanize(string $value): string
    {
        $value = preg_replace(
            '/([a-z])([A-Z])/',
            '$1 $2',
            $value
        );

        return ucfirst(strtolower($value));
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
            str_split(bin2hex($data), 4)
        );
    }
}
