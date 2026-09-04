<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

final class PageGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt eine neue Page inklusive Translation-Placeholder
     * und Übersetzungen.
     *
     * Navigation wird NICHT hier erzeugt.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $table = $this->stringValue(
            $config['table'] ?? $config['db_table'] ?? ''
        );

        $saveKey = $this->stringValue(
            $config['save_key'] ?? ''
        );

        $slugMode = $this->stringValue(
            $config['slug_mode'] ?? 'new'
        );

        $slug = $this->stringValue(
            $config['slug'] ?? ''
        );

        $newSlug = $this->stringValue(
            $config['new_slug'] ?? ''
        );

        /*
         * ---------------------------------------------------------
         * Validierung
         * ---------------------------------------------------------
         */

        if ($table === '') {
            throw new RuntimeException(
                'PageGenerator: Keine Tabelle angegeben.'
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new RuntimeException(
                'PageGenerator: Ungültiger Tabellenname.'
            );
        }

        if ($saveKey === '') {
            throw new RuntimeException(
                'PageGenerator: Kein save_key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
            $saveKey
        )) {
            throw new RuntimeException(
                'PageGenerator: Ungültiger save_key.'
            );
        }

        if (!in_array(
            $slugMode,
            ['existing', 'new'],
            true
        )) {
            throw new RuntimeException(
                'PageGenerator: Ungültige slug_mode.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Slug ermitteln
         * ---------------------------------------------------------
         */

        $pageSlug = $this->resolveSlug(
            $slugMode,
            $slug,
            $newSlug
        );

        /*
         * ---------------------------------------------------------
         * Page darf nicht bereits existieren
         * ---------------------------------------------------------
         */

        if ($this->pageSlugExists($pageSlug)) {
            throw new RuntimeException(
                sprintf(
                    'PageGenerator: Die Page mit dem Slug "%s" existiert bereits.',
                    $pageSlug
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * Translation Placeholder
         * ---------------------------------------------------------
         */

        $translationPlaceholder = $this->buildTranslationPlaceholder(
            $table,
            $saveKey
        );

        if ($this->translationPlaceholderExists(
            $translationPlaceholder
        )) {
            throw new RuntimeException(
                sprintf(
                    'PageGenerator: Translation-Placeholder "%s" existiert bereits.',
                    $translationPlaceholder
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * Transaction
         * ---------------------------------------------------------
         */

        $this->db->begin_transaction();

        try {
            /*
             * Slug Placeholder
             */
            $this->ensureSlugPlaceholder($pageSlug);

            /*
             * Translation Placeholder
             */
            $this->createTranslationPlaceholder(
                $translationPlaceholder
            );

            /*
             * Page
             */
            $pageUuid = $this->createPage(
                $pageSlug,
                $translationPlaceholder
            );

            /*
             * Übersetzungen
             */
            $this->createTranslations(
                $translationPlaceholder,
                $table,
                $saveKey
            );

            $this->db->commit();

            return [
                'success' => true,

                'page_uuid' => $pageUuid,
                'page_slug' => $pageSlug,

                'slug' => $pageSlug,

                'translation_placeholder' =>
                    $translationPlaceholder,

                'table' => $table,
                'save_key' => $saveKey,

                'name' => $this->buildPageName(
                    $table,
                    $saveKey
                ),

                'required_permission_id' =>
                    'perm-view-admin',

                'page_css_id' => 'admin',
                'template' => 'default',
                'enabled' => 1,
                'sort_order' => 0,
            ];
        } catch (Throwable $e) {
            $this->db->rollback();

            throw $e;
        }
    }

    /**
     * Ermittelt den verwendeten Slug.
     */
    private function resolveSlug(
        string $slugMode,
        string $slug,
        string $newSlug
    ): string {
        if ($slugMode === 'existing') {
            if ($slug === '') {
                throw new RuntimeException(
                    'PageGenerator: Bei slug_mode=existing fehlt der Slug.'
                );
            }

            if (!$this->slugPlaceholderExists($slug)) {
                throw new RuntimeException(
                    sprintf(
                        'PageGenerator: Der Slug-Placeholder "%s" existiert nicht.',
                        $slug
                    )
                );
            }

            return $slug;
        }

        if ($newSlug === '') {
            throw new RuntimeException(
                'PageGenerator: Bei slug_mode=new fehlt new_slug.'
            );
        }

        $newSlug = strtolower(trim($newSlug));

        if (!preg_match(
            '/^[a-z0-9][a-z0-9-]*$/',
            $newSlug
        )) {
            throw new RuntimeException(
                sprintf(
                    'PageGenerator: Ungültiger Slug "%s".',
                    $newSlug
                )
            );
        }

        return $newSlug;
    }

    /**
     * Legt den Slug-Placeholder an, falls dieser noch nicht existiert.
     */
    private function ensureSlugPlaceholder(string $slug): void
    {
        if ($this->slugPlaceholderExists($slug)) {
            return;
        }

        $sql = '
            INSERT INTO slug_placeholder (
                id
            ) VALUES (?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Prepare slug_placeholder fehlgeschlagen: '
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
                'PageGenerator: Slug-Placeholder konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Erstellt die Page.
     */
    private function createPage(
        string $pageSlug,
        string $translationPlaceholder
    ): string {
        $pageUuid = $this->uuidV4();

        $name = strtoupper(
            $this->humanize(
                $pageSlug
            )
        );

        $requiredPermissionId = 'perm-view-admin';
        $pageCssId = 'admin';
        $template = 'default';
        $enabled = 1;
        $sortOrder = 0;

        $sql = '
            INSERT INTO page (
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                enabled,
                sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Prepare page fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssssii',
            $pageUuid,
            $pageSlug,
            $name,
            $requiredPermissionId,
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
                'PageGenerator: Page konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();

        return $pageUuid;
    }

    /**
     * Erstellt DE / EN / FR Übersetzungen.
     */
    private function createTranslations(
        string $translationPlaceholder,
        string $table,
        string $saveKey
    ): void {
        $labels = [
            'de' => $this->buildGermanLabel(
                $table,
                $saveKey
            ),

            'en' => $this->buildEnglishLabel(
                $table,
                $saveKey
            ),

            'fr' => $this->buildFrenchLabel(
                $table,
                $saveKey
            ),
        ];

        foreach ($labels as $languageId => $label) {
            $this->createTranslation(
                $translationPlaceholder,
                $languageId,
                $label
            );
        }
    }

    /**
     * Erstellt eine einzelne Übersetzung.
     */
    private function createTranslation(
        string $translationPlaceholder,
        string $languageId,
        string $label
    ): void {
        if (!$this->languageExists($languageId)) {
            throw new RuntimeException(
                sprintf(
                    'PageGenerator: Sprache "%s" existiert nicht.',
                    $languageId
                )
            );
        }

        $translationUuid = $this->uuidV4();

        $sql = '
            INSERT INTO translation (
                translation_uuid,
                fk_translation_holder,
                fk_language_id,
                label
            ) VALUES (?, ?, ?, ?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Prepare translation fehlgeschlagen: '
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
                'PageGenerator: Translation konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Erstellt einen Translation Placeholder.
     */
    private function createTranslationPlaceholder(
        string $placeholder
    ): void {
        $sql = '
            INSERT INTO translation_placeholder (
                id
            ) VALUES (?)
        ';

        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Prepare translation_placeholder fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $placeholder
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'PageGenerator: Translation-Placeholder konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Prüft, ob der Slug existiert.
     */
    private function slugPlaceholderExists(
        string $slug
    ): bool {
        return $this->exists(
            'SELECT id FROM slug_placeholder WHERE id = ? LIMIT 1',
            $slug
        );
    }

    /**
     * Prüft, ob eine Page mit diesem Slug existiert.
     */
    private function pageSlugExists(
        string $slug
    ): bool {
        return $this->exists(
            'SELECT page_uuid FROM page WHERE slug = ? LIMIT 1',
            $slug
        );
    }

    /**
     * Prüft Translation Placeholder.
     */
    private function translationPlaceholderExists(
        string $placeholder
    ): bool {
        return $this->exists(
            '
                SELECT id
                FROM translation_placeholder
                WHERE id = ?
                LIMIT 1
            ',
            $placeholder
        );
    }

    /**
     * Prüft Sprache.
     */
    private function languageExists(
        string $languageId
    ): bool {
        return $this->exists(
            'SELECT id FROM trans_language WHERE id = ? LIMIT 1',
            $languageId
        );
    }

    /**
     * Allgemeiner EXISTS-Helper.
     */
    private function exists(
        string $sql,
        string $value
    ): bool {
        $stmt = $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Prepare EXISTS fehlgeschlagen: '
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
                'PageGenerator: EXISTS-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result = $stmt->get_result();

        $exists = $result !== false
            && $result->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Erstellt einen eindeutigen Translation Placeholder.
     */
    private function buildTranslationPlaceholder(
        string $table,
        string $saveKey
    ): string {
        $base = strtoupper(
            preg_replace(
                '/[^A-Z0-9]+/',
                '_',
                $saveKey
            ) ?? $saveKey
        );

        $base = trim(
            $base,
            '_'
        );

        if ($base === '') {
            $base = strtoupper(
                $this->tableToEntity($table)
            );
        }

        return 'PAGE_' . $base;
    }

    /**
     * Erzeugt den Seitennamen.
     */
    private function buildPageName(
        string $table,
        string $saveKey
    ): string {
        return strtoupper(
            $this->humanize(
                $saveKey !== ''
                    ? $saveKey
                    : $table
            )
        );
    }

    /**
     * Deutsches Label.
     */
    private function buildGermanLabel(
        string $table,
        string $saveKey
    ): string {
        $entity = $this->humanize(
            $saveKey !== ''
                ? $saveKey
                : $table
        );

        return $entity;
    }

    /**
     * Englisches Label.
     */
    private function buildEnglishLabel(
        string $table,
        string $saveKey
    ): string {
        $entity = $this->humanize(
            $saveKey !== ''
                ? $saveKey
                : $table
        );

        return $entity;
    }

    /**
     * Französisches Label.
     */
    private function buildFrenchLabel(
        string $table,
        string $saveKey
    ): string {
        $entity = $this->humanize(
            $saveKey !== ''
                ? $saveKey
                : $table
        );

        return $entity;
    }

    /**
     * Wandelt z.B. "address_book" in "Address Book".
     */
    private function humanize(
        string $value
    ): string {
        $value = str_replace(
            ['_', '-'],
            ' ',
            trim($value)
        );

        $value = preg_replace(
            '/\s+/',
            ' ',
            $value
        ) ?? $value;

        return ucwords(
            strtolower($value)
        );
    }

    /**
     * Tabelle -> Entity.
     */
    private function tableToEntity(
        string $table
    ): string {
        $table = preg_replace(
            '/[^a-zA-Z0-9_]/',
            '',
            $table
        ) ?? $table;

        $parts = preg_split(
            '/[_\s-]+/',
            $table
        );

        $parts = array_filter(
            $parts ?: [],
            static fn ($part) => $part !== ''
        );

        $entity = '';

        foreach ($parts as $part) {
            $entity .= ucfirst(
                strtolower($part)
            );
        }

        return $entity !== ''
            ? $entity
            : 'Entity';
    }

    /**
     * Normalisiert einen Wert zu String.
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
     * UUID v4.
     */
    private function uuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(
            ord($data[6]) & 0x0f | 0x40
        );

        $data[8] = chr(
            ord($data[8]) & 0x3f | 0x80
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
