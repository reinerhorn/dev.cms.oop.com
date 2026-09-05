<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class GeneratorManager
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Führt die komplette Generator-Kette aus.
     *
     * Ablauf:
     *
     * GeneratorManager
     *     ↓
     * PageGenerator
     *     ↓
     * NavigationGenerator
     *     ↓
     * JsonFormGenerator
     *     ↓
     * PluginRegistrationGenerator
     *     ↓
     * PageConfigGenerator
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(
        array $config
    ): array {
        /*
         * ---------------------------------------------------------
         * 1. CONFIG NORMALISIEREN
         * ---------------------------------------------------------
         */

        $config =
            $this->normalizeConfig(
                $config
            );

        /*
         * ---------------------------------------------------------
         * 2. CONFIG VALIDIEREN
         * ---------------------------------------------------------
         */

        $this->validateConfig(
            $config
        );

        /*
         * ---------------------------------------------------------
         * RESULT
         * ---------------------------------------------------------
         */

        $results = [
            'page' => null,
            'navigation' => null,
            'form' => null,
            'plugin' => null,
            'page_config' => null,
        ];

        /*
         * ---------------------------------------------------------
         * 3. PAGE
         * ---------------------------------------------------------
         */

        if (
            $config['generate_page'] === true
        ) {
            $pageGenerator =
                new PageGenerator(
                    $this->db
                );

            $pageResult =
                $pageGenerator->generate(
                    $config
                );

            if (
                !isset(
                    $pageResult['page_uuid']
                )
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'PageGenerator hat keine '
                    . 'page_uuid zurückgegeben.'
                );
            }

            $config['page_uuid'] =
                (string)
                $pageResult['page_uuid'];

            $config['page_slug'] =
                (string)
                (
                    $pageResult['page_slug']
                    ?? $pageResult['slug']
                    ?? ''
                );

            if ($config['page_slug'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'PageGenerator hat keinen '
                    . 'page_slug zurückgegeben.'
                );
            }

            $results['page'] =
                $pageResult;
        } else {
            /*
             * -----------------------------------------------------
             * VORHANDENE PAGE LADEN
             * -----------------------------------------------------
             */

            $pageResult =
                $this->loadExistingPage(
                    $config['page']
                );

            $config['page_uuid'] =
                (string)
                $pageResult['page_uuid'];

            $config['page_slug'] =
                (string)
                $pageResult['slug'];

            /*
             * Die tatsächlichen Page-Einstellungen verwenden.
             *
             * Damit überschreiben wir nicht versehentlich
             * Einstellungen einer vorhandenen Page mit Werten
             * aus dem Generator-Formular.
             */

            $config['context'] =
                (string)
                $pageResult['context'];

            $config['nav_id'] =
                (string)
                $pageResult['nav_id'];

            $config['required_permission_id'] =
                $this->nullableString(
                    $pageResult[
                        'required_permission_id'
                    ]
                    ?? null
                );

            $config['page_css_id'] =
                $this->nullableString(
                    $pageResult[
                        'page_css_id'
                    ]
                    ?? null
                );

            $config['auth_visibility'] =
                (string)
                $pageResult['auth_visibility'];

            $config['template'] =
                (string)
                $pageResult['template'];

            $config['meta_title'] =
                $this->nullableString(
                    $pageResult[
                        'meta_title'
                    ]
                    ?? null
                );

            $config['meta_description'] =
                $this->nullableString(
                    $pageResult[
                        'meta_description'
                    ]
                    ?? null
                );

            $config['enabled'] =
                ((int)
                $pageResult['enabled']) === 1;

            $config['sort_order'] =
                (int)
                $pageResult['sort_order'];

            $results['page'] =
                $pageResult;
        }

        /*
         * ---------------------------------------------------------
         * 4. NAVIGATION
         * ---------------------------------------------------------
         *
         * Navigation ist optional.
         */

        if (
            $config['create_navigation'] === true
        ) {
            $navigationGenerator =
                new NavigationGenerator(
                    $this->db
                );

            /*
             * Die tatsächlich erzeugte Page verwenden.
             */

            $navigationConfig =
                $config;

            $navigationConfig['page_uuid'] =
                $config['page_uuid'];

            $navigationConfig['page_slug'] =
                $config['page_slug'];

            $navigationResult =
                $navigationGenerator->generate(
                    $navigationConfig
                );

            $results['navigation'] =
                $navigationResult;
        }

        /*
         * ---------------------------------------------------------
         * 5. JSON FORM
         * ---------------------------------------------------------
         */

        $jsonFormGenerator =
            new JsonFormGenerator(
                $this->db
            );

        $formResult =
            $jsonFormGenerator->generate(
                [
                    'table' =>
                        $config['table'],

                    'form_type' =>
                        $config['form_type'],

                    'save_key' =>
                        $config['save_key'],
                ]
            );

        if (
            !isset(
                $formResult['content_uuid']
            )
            && !isset(
                $formResult['plugin_content_uuid']
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'JsonFormGenerator hat keine '
                . 'Content-UUID zurückgegeben.'
            );
        }

        $pluginContentUuid =
            (string)
            (
                $formResult['plugin_content_uuid']
                ?? $formResult['content_uuid']
                ?? ''
            );

        if ($pluginContentUuid === '') {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültige Plugin-Content-UUID.'
            );
        }

        $config['plugin_content_uuid'] =
            $pluginContentUuid;

        $results['form'] =
            $formResult;

        /*
         * ---------------------------------------------------------
         * 6. PLUGIN ERZEUGEN
         * ---------------------------------------------------------
         */

        $pluginGenerator =
            new PluginRegistrationGenerator(
                $this->db
            );

        $plugin =
            $pluginGenerator->generate(
                [
                    'table' =>
                        $config['table'],

                    'form_type' =>
                        $config['form_type'],

                    'save_key' =>
                        $config['save_key'],
                ]
            );

        /*
         * ---------------------------------------------------------
         * 7. PLUGIN REGISTRIEREN
         * ---------------------------------------------------------
         *
         * register() liefert jetzt ein ARRAY zurück.
         */

        $registeredPlugin =
            $pluginGenerator->register(
                $plugin
            );

        if (
            !isset(
                $registeredPlugin['plugin_uuid']
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'PluginRegistrationGenerator hat '
                . 'keine plugin_uuid zurückgegeben.'
            );
        }

        $config['plugin_uuid'] =
            (string)
            $registeredPlugin['plugin_uuid'];

        $results['plugin'] =
            $registeredPlugin;

        /*
         * ---------------------------------------------------------
         * 8. PAGE CONFIG
         * ---------------------------------------------------------
         */

        $pageConfigGenerator =
            new PageConfigGenerator(
                $this->db
            );

        $pageConfigResult =
            $pageConfigGenerator->generate(
                [
                    'page_uuid' =>
                        $config['page_uuid'],

                    'plugin_uuid' =>
                        $config['plugin_uuid'],

                    'plugin_content_uuid' =>
                        $config[
                            'plugin_content_uuid'
                        ],

                    'content_label' =>
                        $config['save_key'],
                ]
            );

        $results['page_config'] =
            $pageConfigResult;

        /*
         * ---------------------------------------------------------
         * 9. RESULT
         * ---------------------------------------------------------
         */

        return [
            'success' => true,

            'message' =>
                'Generator erfolgreich ausgeführt.',

            'config' =>
                $config,

            'results' =>
                $results,
        ];
    }

    /**
     * Normalisiert die Generator-Konfiguration.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    private function normalizeConfig(
        array $config
    ): array {
        /*
         * ---------------------------------------------------------
         * BASIS
         * ---------------------------------------------------------
         */

        $config['table'] =
            $this->stringValue(
                $config['table']
                ?? $config['db_table']
                ?? ''
            );

        $config['form_type'] =
            $this->stringValue(
                $config['form_type']
                ?? 'entry'
            );

        $config['save_key'] =
            $this->stringValue(
                $config['save_key']
                ?? ''
            );

        /*
         * ---------------------------------------------------------
         * PAGE
         * ---------------------------------------------------------
         */

        $config['own_page'] =
            $this->boolValue(
                $config['own_page']
                ?? false
            );

        $config['generate_page'] =
            $this->boolValue(
                $config['generate_page']
                ?? $config['own_page']
                ?? false
            );

        $config['page'] =
            $this->nullableString(
                $config['page']
                ?? null
            );

        $config['slug_mode'] =
            $this->stringValue(
                $config['slug_mode']
                ?? 'new'
            );

        $config['slug'] =
            $this->nullableString(
                $config['slug']
                ?? null
            );

        $config['new_slug'] =
            $this->nullableString(
                $config['new_slug']
                ?? null
            );

        /*
         * ---------------------------------------------------------
         * PAGE CONFIG
         * ---------------------------------------------------------
         */

        $config['context'] =
            $this->stringValue(
                $config['context']
                ?? 'frontend'
            );

        $config['nav_id'] =
            $this->stringValue(
                $config['nav_id']
                ?? ''
            );

        $config['required_permission_id'] =
            $this->nullableString(
                $config[
                    'required_permission_id'
                ]
                ?? null
            );

        $config['auth_visibility'] =
            $this->stringValue(
                $config['auth_visibility']
                ?? 'public'
            );

        $config['page_css_id'] =
            $this->nullableString(
                $config['page_css_id']
                ?? null
            );

        $config['template'] =
            $this->stringValue(
                $config['template']
                ?? 'default'
            );

        $config['meta_title'] =
            $this->nullableString(
                $config['meta_title']
                ?? null
            );

        $config['meta_description'] =
            $this->nullableString(
                $config['meta_description']
                ?? null
            );

        $config['enabled'] =
            $this->boolValue(
                $config['enabled']
                ?? true
            );

        $config['sort_order'] =
            $this->intValue(
                $config['sort_order']
                ?? 0
            );

        /*
         * form_action wird bewusst nur übernommen.
         *
         * Das Feld existiert noch in page,
         * wird vom Generator aber nicht verarbeitet.
         */

        $config['form_action'] =
            $this->nullableString(
                $config['form_action']
                ?? null
            );

        /*
         * ---------------------------------------------------------
         * NAVIGATION
         * ---------------------------------------------------------
         */

        $config['create_navigation'] =
            $this->boolValue(
                $config['create_navigation']
                ?? false
            );

        $config['navigation_parent_id'] =
            $this->nullableString(
                $config[
                    'navigation_parent_id'
                ]
                ?? null
            );

        $config['navigation_title'] =
            $this->nullableString(
                $config[
                    'navigation_title'
                ]
                ?? null
            );

        $config['navigation_slug'] =
            $this->nullableString(
                $config[
                    'navigation_slug'
                ]
                ?? null
            );

        $config[
            'navigation_translation_placeholder'
        ] =
            $this->nullableString(
                $config[
                    'navigation_translation_placeholder'
                ]
                ?? null
            );

        $config['navigation_sort_order'] =
            $this->intValue(
                $config[
                    'navigation_sort_order'
                ]
                ?? 0
            );

        $config['navigation_enabled'] =
            $this->boolValue(
                $config[
                    'navigation_enabled'
                ]
                ?? true
            );

        $config['navigation_align'] =
            $this->stringValue(
                $config[
                    'navigation_align'
                ]
                ?? 'left'
            );

        $config['navigation_context_id'] =
            $this->stringValue(
                $config[
                    'navigation_context_id'
                ]
                ?? 'admin'
            );

        $config['navigation_permission_id'] =
            $this->nullableString(
                $config[
                    'navigation_permission_id'
                ]
                ?? null
            );

        $config['navigation_auth_visibility'] =
            $this->stringValue(
                $config[
                    'navigation_auth_visibility'
                ]
                ?? 'public'
            );

        /*
         * ---------------------------------------------------------
         * RESULT / INTERNAL VALUES
         * ---------------------------------------------------------
         */

        $config['page_uuid'] =
            $this->nullableString(
                $config['page_uuid']
                ?? null
            );

        $config['page_slug'] =
            $this->nullableString(
                $config['page_slug']
                ?? null
            );

        $config['plugin_uuid'] =
            $this->nullableString(
                $config['plugin_uuid']
                ?? null
            );

        $config['plugin_content_uuid'] =
            $this->nullableString(
                $config[
                    'plugin_content_uuid'
                ]
                ?? null
            );

        return $config;
    }

    /**
     * Validiert die Generator-Konfiguration.
     *
     * @param array<string,mixed> $config
     */
    private function validateConfig(
        array $config
    ): void {
        /*
         * ---------------------------------------------------------
         * BASIS
         * ---------------------------------------------------------
         */

        if ($config['table'] === '') {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Keine Datenbanktabelle angegeben.'
            );
        }

        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Kein save_key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_]+$/',
            $config['save_key']
        )) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültiger save_key "' .
                $config['save_key'] .
                '".'
            );
        }

        if (
            $config['form_type'] !== 'entry'
            && $config['form_type'] !== 'simple'
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültiger form_type "' .
                $config['form_type'] .
                '".'
            );
        }

        /*
         * ---------------------------------------------------------
         * PAGE
         * ---------------------------------------------------------
         */

        if (
            $config['context'] !== 'frontend'
            && $config['context'] !== 'frontend-auth'
            && $config['context'] !== 'backend'
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültiger Page-Kontext "' .
                $config['context'] .
                '".'
            );
        }

        if (
            $config['auth_visibility'] !== 'public'
            && $config['auth_visibility'] !== 'guest'
            && $config['auth_visibility'] !== 'logged_in'
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültige auth_visibility "' .
                $config['auth_visibility'] .
                '".'
            );
        }

        if ($config['template'] === '') {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Kein Template angegeben.'
            );
        }

        if ($config['sort_order'] < 0) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'sort_order darf nicht negativ sein.'
            );
        }

        if (
            $config['meta_title'] !== null
            && mb_strlen(
                $config['meta_title']
            ) > 150
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'meta_title ist zu lang.'
            );
        }

        if (
            $config['meta_description'] !== null
            && mb_strlen(
                $config['meta_description']
            ) > 255
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'meta_description ist zu lang.'
            );
        }

        /*
         * ---------------------------------------------------------
         * OWN PAGE
         * ---------------------------------------------------------
         */

        if ($config['generate_page'] === true) {
            if (
                $config['slug_mode'] === 'existing'
            ) {
                if (
                    $config['slug'] === null
                    || $config['slug'] === ''
                ) {
                    throw new RuntimeException(
                        'GeneratorManager: '
                        . 'Bei slug_mode=existing '
                        . 'muss ein Slug angegeben werden.'
                    );
                }
            } elseif (
                $config['slug_mode'] === 'new'
            ) {
                if (
                    $config['new_slug'] === null
                    || $config['new_slug'] === ''
                ) {
                    throw new RuntimeException(
                        'GeneratorManager: '
                        . 'Bei slug_mode=new '
                        . 'muss ein neuer Slug angegeben werden.'
                    );
                }
            } else {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Ungültiger slug_mode "' .
                    $config['slug_mode'] .
                    '".'
                );
            }

            /*
             * PageGenerator benötigt aktuell nav_id.
             *
             * Dieser Wert darf deshalb bei einer neuen Page
             * nicht leer sein.
             */

            if ($config['nav_id'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Für eine neue Page muss nav_id '
                    . 'angegeben werden.'
                );
            }
        } else {
            /*
             * Vorhandene Page.
             */

            if (
                $config['page'] === null
                || $config['page'] === ''
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Keine vorhandene Page angegeben.'
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * NAVIGATION
         * ---------------------------------------------------------
         */

        if (
            $config['create_navigation'] === true
        ) {
            if (
                $config['navigation_sort_order'] < 0
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'navigation_sort_order '
                    . 'darf nicht negativ sein.'
                );
            }

            if (
                $config['navigation_align'] !== 'left'
                && $config['navigation_align'] !== 'right'
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'navigation_align muss '
                    . 'left oder right sein.'
                );
            }

            if (
                $config['navigation_auth_visibility']
                !== 'public'
                && $config['navigation_auth_visibility']
                !== 'guest'
                && $config['navigation_auth_visibility']
                !== 'logged_in'
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Ungültige Navigation '
                    . 'auth_visibility.'
                );
            }

            if (
                $config['navigation_context_id'] === ''
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'navigation_context_id darf '
                    . 'nicht leer sein.'
                );
            }
        }
    }

    /**
     * Lädt eine vorhandene Page vollständig.
     *
     * @return array<string,mixed>
     */
    private function loadExistingPage(
        ?string $page
    ): array {
        if (
            $page === null
            || $page === ''
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Keine Page zum Laden angegeben.'
            );
        }

        $sql = '
            SELECT
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
                auth_visibility
            FROM page
            WHERE page_uuid = ?
               OR slug = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare(
                $sql
            );

        if (!$stmt) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Prepare Page SELECT fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $page,
            $page
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'GeneratorManager: '
                . 'Page SELECT fehlgeschlagen: '
                . $error
            );
        }

        $result =
            $stmt->get_result();

        if (
            $result === false
            || $result->num_rows === 0
        ) {
            $stmt->close();

            throw new RuntimeException(
                'GeneratorManager: '
                . 'Page "' .
                $page .
                '" wurde nicht gefunden.'
            );
        }

        $row =
            $result->fetch_assoc();

        $stmt->close();

        if (!is_array($row)) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Ungültige Page-Daten.'
            );
        }

        return [
            'page_uuid' =>
                (string)
                ($row['page_uuid'] ?? ''),

            'slug' =>
                (string)
                ($row['slug'] ?? ''),

            'name' =>
                (string)
                ($row['name'] ?? ''),

            'required_permission_id' =>
                $row[
                    'required_permission_id'
                ] !== null
                    ? (string)
                    $row[
                        'required_permission_id'
                    ]
                    : null,

            'page_css_id' =>
                $row['page_css_id'] !== null
                    ? (string)
                    $row['page_css_id']
                    : null,

            'fk_translation_placeholder' =>
                $row[
                    'fk_translation_placeholder'
                ] !== null
                    ? (string)
                    $row[
                        'fk_translation_placeholder'
                    ]
                    : null,

            'template' =>
                (string)
                ($row['template'] ?? 'default'),

            'meta_title' =>
                $row['meta_title'] !== null
                    ? (string)
                    $row['meta_title']
                    : null,

            'meta_description' =>
                $row['meta_description'] !== null
                    ? (string)
                    $row['meta_description']
                    : null,

            'enabled' =>
                (int)
                ($row['enabled'] ?? 0),

            'sort_order' =>
                (int)
                ($row['sort_order'] ?? 0),

            'context' =>
                (string)
                ($row['context'] ?? 'frontend'),

            'nav_id' =>
                (string)
                ($row['nav_id'] ?? 'generalNav'),

            'auth_visibility' =>
                (string)
                (
                    $row['auth_visibility']
                    ?? 'public'
                ),
        ];
    }

    /**
     * Stringwert.
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
     * Nullable String.
     */
    private function nullableString(
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
     * Booleanwert.
     */
    private function boolValue(
        mixed $value
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (
            is_int($value)
            || is_float($value)
        ) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $value =
                strtolower(
                    trim($value)
                );

            return in_array(
                $value,
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
     * Integerwert.
     */
    private function intValue(
        mixed $value
    ): int {
        if (
            is_int($value)
            || is_float($value)
            || is_string($value)
        ) {
            return (int) $value;
        }

        return 0;
    }
}