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
     * Erzeugt eine Page inklusive:
     *
     * - Slug-Placeholder
     * - Translation-Placeholder
     * - Page
     * - DE/EN/FR Translation-Einträge
     *
     * Navigation wird hier bewusst NICHT erzeugt.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $table =
            $this->stringValue(
                $config['table']
                    ?? $config['db_table']
                    ?? ''
            );

        $saveKey =
            $this->stringValue(
                $config['save_key']
                    ?? ''
            );

        $slugMode =
            $this->stringValue(
                $config['slug_mode']
                    ?? 'new'
            );

        $slug =
            $this->stringValue(
                $config['slug']
                    ?? ''
            );

        $newSlug =
            $this->stringValue(
                $config['new_slug']
                    ?? ''
            );

        /*
         * ---------------------------------------------------------
         * Page-Konfiguration
         * ---------------------------------------------------------
         */

        $context =
            $this->stringValue(
                $config['context']
                    ?? 'frontend'
            );

        $navId =
            $this->stringValue(
                $config['nav_id']
                    ?? ''
            );

        $requiredPermissionId =
            $this->nullableStringValue(
                $config['required_permission_id']
                    ?? null
            );

        $pageCssId =
            $this->nullableStringValue(
                $config['page_css_id']
                    ?? null
            );

        $authVisibility =
            $this->stringValue(
                $config['auth_visibility']
                    ?? 'public'
            );

        $template =
            $this->stringValue(
                $config['template']
                    ?? 'default'
            );

        $metaTitle =
            $this->nullableStringValue(
                $config['meta_title']
                    ?? null
            );

        $metaDescription =
            $this->nullableStringValue(
                $config['meta_description']
                    ?? null
            );

        $enabled =
            $this->boolValue(
                $config['enabled']
                    ?? true
            );

        $sortOrder =
            $this->nullableInt(
                $config['sort_order']
                    ?? 0
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

        if ($saveKey === '') {
            throw new RuntimeException(
                'PageGenerator: Kein Save Key angegeben.'
            );
        }

        if (
            !in_array(
                $context,
                [
                    'frontend',
                    'frontend-auth',
                    'backend',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'PageGenerator: Ungültiger Context: '
                . $context
            );
        }

        if (
            !in_array(
                $authVisibility,
                [
                    'public',
                    'guest',
                    'logged_in',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'PageGenerator: Ungültige Auth-Sichtbarkeit: '
                . $authVisibility
            );
        }

        if ($template === '') {
            throw new RuntimeException(
                'PageGenerator: Template darf nicht leer sein.'
            );
        }

        if ($navId === '') {
            throw new RuntimeException(
                'PageGenerator: nav_id darf nicht leer sein.'
            );
        }

        if (
            $sortOrder !== null
            && $sortOrder < 0
        ) {
            throw new RuntimeException(
                'PageGenerator: sort_order darf nicht negativ sein.'
            );
        }

        if (
            $metaTitle !== null
            && mb_strlen($metaTitle) > 150
        ) {
            throw new RuntimeException(
                'PageGenerator: meta_title darf maximal 150 Zeichen lang sein.'
            );
        }

        if (
            $metaDescription !== null
            && mb_strlen($metaDescription) > 255
        ) {
            throw new RuntimeException(
                'PageGenerator: meta_description darf maximal 255 Zeichen lang sein.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Slug bestimmen
         * ---------------------------------------------------------
         */

        $pageSlug =
            $this->resolvePageSlug(
                $slugMode,
                $slug,
                $newSlug
            );

        if ($pageSlug === '') {
            throw new RuntimeException(
                'PageGenerator: Kein Page-Slug vorhanden.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Prüfen, ob Page bereits existiert
         * ---------------------------------------------------------
         */

        if ($this->pageExistsBySlug($pageSlug)) {
            throw new RuntimeException(
                'Page mit Slug "' . $pageSlug . '" existiert bereits.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Translation Placeholder
         * ---------------------------------------------------------
         */

        $translationPlaceholder =
            $this->buildTranslationPlaceholder(
                $pageSlug
            );

        if (
            $this->translationPlaceholderExists(
                $translationPlaceholder
            )
        ) {
            throw new RuntimeException(
                'Translation-Placeholder "'
                . $translationPlaceholder
                . '" existiert bereits.'
            );
        }

        /*
         * ---------------------------------------------------------
         * Slug Placeholder
         * ---------------------------------------------------------
         */

        $slugPlaceholderId =
            $this->ensureSlugPlaceholder(
                $pageSlug
            );

        /*
         * ---------------------------------------------------------
         * Transaktion
         * ---------------------------------------------------------
         */

        $this->db->begin_transaction();

        try {
            /*
             * Page Translation Placeholder
             */
            $this->createTranslationPlaceholder(
                $translationPlaceholder
            );
            /*
             * Standard-Übersetzungen
             */
            $this->createTranslations(
                $translationPlaceholder,
                $pageSlug
            );
            /*
             * Page
             */
            $pageUuid =
                $this->createPage(
                    pageSlug: $pageSlug,
                    translationPlaceholder: $translationPlaceholder,
                    context: $context,
                    navId: $navId,
                    requiredPermissionId: $requiredPermissionId,
                    pageCssId: $pageCssId,
                    authVisibility: $authVisibility,
                    template: $template,
                    metaTitle: $metaTitle,
                    metaDescription: $metaDescription,
                    enabled: $enabled,
                    sortOrder: $sortOrder
                );


            $this->db->commit();

            return [
                'success' =>
                    true,

                'page_uuid' =>
                    $pageUuid,

                'page_slug' =>
                    $pageSlug,

                'slug' =>
                    $pageSlug,

                'slug_placeholder_id' =>
                    $slugPlaceholderId,

                'translation_placeholder' =>
                    $translationPlaceholder,

                'table' =>
                    $table,

                'save_key' =>
                    $saveKey,

                'name' =>
                    $this->buildPageName(
                        $pageSlug
                    ),

                'context' =>
                    $context,

                'nav_id' =>
                    $navId,

                'required_permission_id' =>
                    $requiredPermissionId,

                'page_css_id' =>
                    $pageCssId,

                'auth_visibility' =>
                    $authVisibility,

                'template' =>
                    $template,

                'meta_title' =>
                    $metaTitle,

                'meta_description' =>
                    $metaDescription,

                'enabled' =>
                    $enabled ? 1 : 0,

                'sort_order' =>
                    $sortOrder ?? 0,
            ];
        } catch (Throwable $e) {
            $this->db->rollback();

            throw $e;
        }
    }

    /**
     * Ermittelt den Page-Slug.
     */
    private function resolvePageSlug(
        string $slugMode,
        string $slug,
        string $newSlug
    ): string {
        if ($slugMode === 'existing') {
            if ($slug === '') {
                throw new RuntimeException(
                    'PageGenerator: Bei slug_mode "existing" muss ein Slug angegeben werden.'
                );
            }

            return $this->normalizeSlug(
                $slug
            );
        }

        if ($slugMode !== 'new') {
            throw new RuntimeException(
                'PageGenerator: Ungültiger slug_mode: '
                . $slugMode
            );
        }

        if ($newSlug === '') {
            throw new RuntimeException(
                'PageGenerator: Bei slug_mode "new" muss new_slug angegeben werden.'
            );
        }

        return $this->normalizeSlug(
            $newSlug
        );
    }

    /**
     * Normalisiert und validiert einen Slug.
     */
    private function normalizeSlug(
        string $slug
    ): string {
        $slug =
            strtolower(
                trim($slug)
            );

        if ($slug === '') {
            return '';
        }

        if (
            !preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            )
        ) {
            throw new RuntimeException(
                'PageGenerator: Ungültiger Slug "'
                . $slug
                . '". Erlaubt sind Kleinbuchstaben, Zahlen und Bindestriche.'
            );
        }

        if (mb_strlen($slug) > 100) {
            throw new RuntimeException(
                'PageGenerator: Slug darf maximal 100 Zeichen lang sein.'
            );
        }

        return $slug;
    }

    /**
     * Prüft, ob eine Page mit dem Slug existiert.
     */
    private function pageExistsBySlug(
        string $slug
    ): bool {
        $sql = '
            SELECT page_uuid
            FROM page
            WHERE slug = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: SELECT page fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $slug
        );

        $stmt->execute();

        $stmt->store_result();

        $exists =
            $stmt->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Erzeugt die Page.
     */
    private function createPage(
        string $pageSlug,
        string $translationPlaceholder,
        string $context,
        string $navId,
        ?string $requiredPermissionId,
        ?string $pageCssId,
        string $authVisibility,
        string $template,
        ?string $metaTitle,
        ?string $metaDescription,
        bool $enabled,
        ?int $sortOrder
    ): string {
        $pageUuid =
            $this->uuidV4();

        $name =
            $this->buildPageName(
                $pageSlug
            );

        $enabledValue =
            $enabled ? 1 : 0;

        $sortOrderValue =
            $sortOrder ?? 0;

        /*
         * form_action wird absichtlich NICHT gesetzt.
         *
         * Die Datenbankspalte existiert weiterhin,
         * wird aber von diesem Generator nicht verwendet.
         */
        $sql = '
            INSERT INTO page (
                page_uuid,
                slug,
                name,
                required_permission_id,
                page_css_id,
                fk_translation_placeholder,
                template,
                meta_title,
                meta_description,
                enabled,
                sort_order,
                context,
                nav_id,
                auth_visibility,
                form_action
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: INSERT page fehlgeschlagen: '
                . $this->db->error
            );
        }

        $formAction = '';

        $stmt->bind_param(
            'sssssssssiiisss',
            $pageUuid,
            $pageSlug,
            $name,
            $requiredPermissionId,
            $pageCssId,
            $translationPlaceholder,
            $template,
            $metaTitle,
            $metaDescription,
            $enabledValue,
            $sortOrderValue,
            $context,
            $navId,
            $authVisibility,
            $formAction
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator: INSERT page fehlgeschlagen: '
                . $error
            );
        }

        $stmt->close();

        return $pageUuid;
    }

    /**
     * Erzeugt den Translation-Placeholder.
     */
    private function createTranslationPlaceholder(
        string $placeholder
    ): void {
        $sql = '
            INSERT INTO translation_placeholder (
                id
            ) VALUES (?)
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Translation-Placeholder konnte nicht vorbereitet werden: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $placeholder
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator: Translation-Placeholder konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Prüft, ob Translation-Placeholder existiert.
     */
    private function translationPlaceholderExists(
        string $placeholder
    ): bool {
        $sql = '
            SELECT id
            FROM translation_placeholder
            WHERE id = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: SELECT translation_placeholder fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $placeholder
        );

        $stmt->execute();

        $stmt->store_result();

        $exists =
            $stmt->num_rows > 0;

        $stmt->close();

        return $exists;
    }

    /**
     * Erzeugt DE/EN/FR Übersetzungen.
     */
    private function createTranslations(
        string $placeholder,
        string $pageSlug
    ): void {
        $translations = [
            'de' =>
                $this->buildPageName(
                    $pageSlug
                ),

            'en' =>
                $this->buildPageName(
                    $pageSlug
                ),

            'fr' =>
                $this->buildPageName(
                    $pageSlug
                ),
        ];

        foreach (
            $translations as $languageId => $text
        ) {
            $this->insertTranslation(
                $placeholder,
                $languageId,
                $text
            );
        }
    }

    /**
     * Fügt eine Translation ein.
     */
    private function insertTranslation(
        string $placeholder,
        string $languageId,
        string $text
    ): void {
        $translationUuid =
            $this->uuidV4();

        $sql = '
            INSERT INTO translation (
                translation_uuid,
                fk_translation_holder,
                fk_language_id,
                label
            ) VALUES (?, ?, ?, ?)
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: Translation INSERT konnte nicht vorbereitet werden: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ssss',
            $translationUuid,
            $placeholder,
            $languageId,
            $text
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PageGenerator: Translation konnte nicht angelegt werden: '
                . $error
            );
        }

        $stmt->close();
    }

    /**
     * Stellt sicher, dass ein Slug-Placeholder existiert.
     *
     * @return string Placeholder-ID
     */
    private function ensureSlugPlaceholder(
        string $slug
    ): string {
        $sql = '
            SELECT id
            FROM slug_placeholder
            WHERE id = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'PageGenerator: SELECT slug_placeholder fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $slug
        );

        $stmt->execute();

        $stmt->bind_result(
            $existingId
        );

        if ($stmt->fetch()) {
            $stmt->close();

            return (string) $existingId;
        }

        $stmt->close();

        $insertSql = '
            INSERT INTO slug_placeholder (
                id
            ) VALUES (?)
        ';

        $insertStmt =
            $this->db->prepare(
                $insertSql
            );

        if (!$insertStmt) {
            throw new RuntimeException(
                'PageGenerator: INSERT slug_placeholder konnte nicht vorbereitet werden: '
                . $this->db->error
            );
        }

        $insertStmt->bind_param(
            's',
            $slug
        );

        if (!$insertStmt->execute()) {
            $error =
                $insertStmt->error;

            $insertStmt->close();

            throw new RuntimeException(
                'PageGenerator: Slug-Placeholder konnte nicht angelegt werden: '
                . $error
            );
        }

        $insertStmt->close();

        return $slug;
    }

    /**
     * Baut den Translation-Placeholder.
     */
    private function buildTranslationPlaceholder(
        string $slug
    ): string {
        $slug =
            strtoupper(
                preg_replace(
                    '/[^A-Z0-9]+/',
                    '_',
                    strtoupper($slug)
                ) ?? ''
            );

        $slug =
            trim(
                $slug,
                '_'
            );

        if ($slug === '') {
            throw new RuntimeException(
                'PageGenerator: Translation-Placeholder konnte nicht erzeugt werden.'
            );
        }

        return 'PAGE_' . $slug . '_LABEL';
    }

    /**
     * Baut den Page-Namen.
     */
    private function buildPageName(
        string $slug
    ): string {
        $name =
            preg_replace(
                '/[-_]+/',
                ' ',
                $slug
            );

        $name =
            trim(
                (string) $name
            );

        if ($name === '') {
            return 'PAGE';
        }

        return ucwords(
            $name
        );
    }

    /**
     * UUID v4.
     */
    private function uuidV4(): string
    {
        $data =
            random_bytes(
                16
            );

        $data[6] =
            chr(
                (ord($data[6]) & 0x0f)
                | 0x40
            );

        $data[8] =
            chr(
                (ord($data[8]) & 0x3f)
                | 0x80
            );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(
                substr(
                    $data,
                    0,
                    4
                )
            ),
            bin2hex(
                substr(
                    $data,
                    4,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    6,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    8,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    10,
                    6
                )
            )
        );
    }

    /**
     * String-Wert.
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
     * String oder NULL.
     */
    private function nullableStringValue(
        mixed $value
    ): ?string {
        $value =
            $this->stringValue(
                $value
            );

        return $value === ''
            ? null
            : $value;
    }

    /**
     * Bool-Wert.
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

        if (is_float($value)) {
            return $value !== 0.0;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                    'ja',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Integer oder NULL.
     */
    private function nullableInt(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}