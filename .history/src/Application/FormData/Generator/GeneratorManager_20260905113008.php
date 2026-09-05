<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

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
     * Reihenfolge:
     *
     * 1. Page
     * 2. Navigation
     * 3. JSON-Form
     * 4. Plugin
     * 5. PageConfig
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $config =
            $this->normalizeConfig($config);

        $this->validateConfig($config);

        $results = [];

        /*
         * ---------------------------------------------------------
         * 1. PAGE
         * ---------------------------------------------------------
         */

        if ($config['own_page']) {
            $pageGenerator =
                new PageGenerator(
                    $this->db
                );

            $pageResult =
                $pageGenerator->generate(
                    $config
                );

            $results['page'] =
                $pageResult;

            if (
                empty($pageResult['page_uuid'])
                || empty($pageResult['page_slug'])
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'PageGenerator hat keine gültige Page zurückgegeben.'
                );
            }

            $config['page_uuid'] =
                (string) $pageResult['page_uuid'];

            $config['page_slug'] =
                (string) $pageResult['page_slug'];
        } else {
            $page =
                $this->loadExistingPage(
                    $config['page']
                );

            $results['page'] = [
                'success' => true,
                'existing' => true,
                ...$page,
            ];

            /*
             * Die tatsächlichen Werte der bestehenden Page
             * übernehmen.
             *
             * Nicht die vom Generator-Formular vorgegebenen
             * Defaultwerte verwenden.
             */
            $config['page_uuid'] =
                $page['page_uuid'];

            $config['page_slug'] =
                $page['slug'];

            $config['context'] =
                $page['context'];

            $config['nav_id'] =
                $page['nav_id'];

            $config['required_permission_id'] =
                $page['required_permission_id'];

            $config['page_css_id'] =
                $page['page_css_id'];

            $config['auth_visibility'] =
                $page['auth_visibility'];

            $config['template'] =
                $page['template'];

            $config['meta_title'] =
                $page['meta_title'];

            $config['meta_description'] =
                $page['meta_description'];

            $config['enabled'] =
                ((int) $page['enabled']) === 1;

            $config['sort_order'] =
                (int) $page['sort_order'];
        }

        /*
         * ---------------------------------------------------------
         * 2. NAVIGATION
         * ---------------------------------------------------------
         */

        if ($config['create_navigation']) {
            $navigationGenerator =
                new NavigationGenerator(
                    $this->db
                );

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

            if (
                !empty(
                    $navigationResult['navigation_uuid']
                )
            ) {
                $config['navigation_uuid'] =
                    (string)
                    $navigationResult[
                        'navigation_uuid'
                    ];
            }
        }

        /*
         * ---------------------------------------------------------
         * 3. JSON FORM
         * ---------------------------------------------------------
         */

        $jsonFormGenerator =
            new JsonFormGenerator(
                $this->db
            );

        $jsonFormConfig = [
            'table' =>
                $config['table'],

            'form_type' =>
                $config['form_type'],

            'save_key' =>
                $config['save_key'],
        ];

        $jsonFormResult =
            $jsonFormGenerator->generate(
                $jsonFormConfig
            );

        $results['json_form'] =
            $jsonFormResult;

        if (
            empty(
                $jsonFormResult['content_uuid']
            )
            && empty(
                $jsonFormResult[
                    'plugin_content_uuid'
                ]
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'JsonFormGenerator hat keine Content-UUID zurückgegeben.'
            );
        }

        $pluginContentUuid =
            (string) (
                $jsonFormResult[
                    'plugin_content_uuid'
                ]
                ?? $jsonFormResult[
                    'content_uuid'
                ]
                ?? ''
            );

        if ($pluginContentUuid === '') {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Keine gültige Plugin-Content-UUID vorhanden.'
            );
        }

        $config['plugin_content_uuid'] =
            $pluginContentUuid;

        /*
         * ---------------------------------------------------------
         * 4. PLUGIN
         * ---------------------------------------------------------
         */

        $pluginGenerator =
            new PluginRegistrationGenerator(
                $this->db
            );

        $pluginConfig = [
            'table' =>
                $config['table'],

            'save_key' =>
                $config['save_key'],

            'form_type' =>
                $config['form_type'],
        ];

        $pluginResult =
            $pluginGenerator->generate(
                $pluginConfig
            );

        $results['plugin'] =
            $pluginResult;

        /*
         * Plugin registrieren.
         *
         * generate() erzeugt nur die Definition.
         * register() sorgt für den DB-Eintrag.
         */
        $registeredPlugin =
            $pluginGenerator->register(
                $pluginResult
            );

        $results['plugin_registration'] =
            $registeredPlugin;

        if (
            empty(
                $registeredPlugin['plugin_uuid']
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Plugin wurde nicht registriert.'
            );
        }

        $config['plugin_uuid'] =
            (string)
            $registeredPlugin['plugin_uuid'];

        /*
         * ---------------------------------------------------------
         * 5. PAGE CONFIG
         * ---------------------------------------------------------
         */

        $pageConfigGenerator =
            new PageConfigGenerator(
                $this->db
            );

        $pageConfig =
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
            ];

        $pageConfigResult =
            $pageConfigGenerator->generate(
                $pageConfig
            );

        $results['page_config'] =
            $pageConfigResult;

        /*
         * ---------------------------------------------------------
         * ERGEBNIS
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

        $config['page'] =
            $this->stringValue(
                $config['page']
                ?? ''
            );

        $config['page_uuid'] =
            $this->stringValue(
                $config['page_uuid']
                ?? ''
            );

        $config['page_slug'] =
            $this->stringValue(
                $config['page_slug']
                ?? ''
            );

        $config['slug_mode'] =
            $this->stringValue(
                $config['slug_mode']
                ?? 'new'
            );

        $config['slug'] =
            $this->stringValue(
                $config['slug']
                ?? ''
            );

        $config['new_slug'] =
            $this->stringValue(
                $config['new_slug']
                ?? ''
            );

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
            $this->nullableStringValue(
                $config[
                    'required_permission_id'
                ] ?? null
            );

        $config['page_css_id'] =
            $this->nullableStringValue(
                $config['page_css_id']
                ?? null
            );

        $config['template'] =
            $this->stringValue(
                $config['template']
                ?? 'default'
            );

        $config['meta_title'] =
            $this->nullableStringValue(
                $config['meta_title']
                ?? null
            );

        $config['meta_description'] =
            $this->nullableStringValue(
                $config[
                    'meta_description'
                ] ?? null
            );

        $config['enabled'] =
            $this->boolValue(
                $config['enabled']
                ?? true
            );

        $config['sort_order'] =
            $this->nullableInt(
                $config['sort_order']
                ?? 0
            );

        $config['auth_visibility'] =
            $this->stringValue(
                $config['auth_visibility']
                ?? 'public'
            );

        /*
         * form_action wird bewusst nur mitgeführt.
         *
         * Das Feld existiert noch in der DB,
         * wird vom neuen Generator aber nicht verwendet.
         */
        $config['form_action'] =
            $this->nullableStringValue(
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
                $config[
                    'create_navigation'
                ] ?? false
            );

        $config['navigation_parent_id'] =
            $this->nullableStringValue(
                $config[
                    'navigation_parent_id'
                ] ?? null
            );

        $config['navigation_title'] =
            $this->stringValue(
                $config[
                    'navigation_title'
                ] ?? ''
            );

        $config['navigation_slug'] =
            $this->stringValue(
                $config[
                    'navigation_slug'
                ] ?? ''
            );

        $config[
            'navigation_translation_placeholder'
        ] =
            $this->nullableStringValue(
                $config[
                    'navigation_translation_placeholder'
                ] ?? null
            );

        $config['navigation_sort_order'] =
            $this->nullableInt(
                $config[
                    'navigation_sort_order'
                ] ?? 0
            );

        $config['navigation_enabled'] =
            $this->boolValue(
                $config[
                    'navigation_enabled'
                ] ?? true
            );

        $config['navigation_align'] =
            $this->stringValue(
                $config[
                    'navigation_align'
                ] ?? 'left'
            );

        $config['navigation_context_id'] =
            $this->stringValue(
                $config[
                    'navigation_context_id'
                ] ?? 'admin'
            );

        $config['navigation_permission_id'] =
            $this->nullableStringValue(
                $config[
                    'navigation_permission_id'
                ] ?? null
            );

        $config['navigation_auth_visibility'] =
            $this->stringValue(
                $config[
                    'navigation_auth_visibility'
                ] ?? 'public'
            );

        /*
         * ---------------------------------------------------------
         * GENERATOR OPTIONEN
         * ---------------------------------------------------------
         */

        $config['generate_page'] =
            $this->boolValue(
                $config[
                    'generate_page'
                ] ?? $config['own_page']
            );

        $config['generate_navigation'] =
            $this->boolValue(
                $config[
                    'generate_navigation'
                ] ?? $config[
                    'create_navigation'
                ]
            );

        $config['generate_form'] =
            $this->boolValue(
                $config[
                    'generate_form'
                ] ?? true
            );

        $config['generate_plugin'] =
            $this->boolValue(
                $config[
                    'generate_plugin'
                ] ?? true
            );

        $config['generate_page_config'] =
            $this->boolValue(
                $config[
                    'generate_page_config'
                ] ?? true
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
                'GeneratorManager: Keine Datenbanktabelle angegeben.'
            );
        }

        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'GeneratorManager: Kein Form Key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_]+$/',
            $config['save_key']
        )) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger Form Key.'
            );
        }

        $allowedFormTypes = [
            'entry',
            'simple',
        ];

        if (
            !in_array(
                $config['form_type'],
                $allowedFormTypes,
                true
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger Formulartyp.'
            );
        }

        /*
         * ---------------------------------------------------------
         * PAGE
         * ---------------------------------------------------------
         */

        $allowedContexts = [
            'frontend',
            'frontend-auth',
            'backend',
        ];

        if (
            !in_array(
                $config['context'],
                $allowedContexts,
                true
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger Page-Context.'
            );
        }

        $allowedAuthVisibility = [
            'public',
            'guest',
            'logged_in',
        ];

        if (
            !in_array(
                $config['auth_visibility'],
                $allowedAuthVisibility,
                true
            )
        ) {
            throw new RuntimeException(
                'GeneratorManager: Ungültige auth_visibility.'
            );
        }

        if ($config['template'] === '') {
            throw new RuntimeException(
                'GeneratorManager: Template darf nicht leer sein.'
            );
        }

        if (
            $config['sort_order'] !== null
            && $config['sort_order'] < 0
        ) {
            throw new RuntimeException(
                'GeneratorManager: sort_order darf nicht negativ sein.'
            );
        }

        if (
            $config['meta_title'] !== null
            && mb_strlen(
                $config['meta_title']
            ) > 150
        ) {
            throw new RuntimeException(
                'GeneratorManager: meta_title ist zu lang.'
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
         * Eigene Page:
         *
         * PageGenerator benötigt nav_id.
         *
         * Für den Moment muss dieser Wert vorhanden sein.
         * Die Ableitung frontend/backend -> nav_id
         * machen wir separat.
         */
        if ($config['own_page']) {
            if ($config['nav_id'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Für eine neue Page muss nav_id gesetzt sein.'
                );
            }

            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'slug_mode=existing benötigt einen Slug.'
                );
            }

            if (
                $config['slug_mode'] === 'new'
                && $config['new_slug'] === ''
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'slug_mode=new benötigt new_slug.'
                );
            }
        } else {
            if ($config['page'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Bei einer bestehenden Page muss page angegeben werden.'
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * NAVIGATION
         * ---------------------------------------------------------
         */

        if ($config['create_navigation']) {
            if (
                $config['navigation_align'] !== 'left'
                && $config['navigation_align'] !== 'right'
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Ungültiger navigation_align.'
                );
            }

            if (
                $config['navigation_auth_visibility'] !== 'public'
                && $config['navigation_auth_visibility'] !== 'guest'
                && $config['navigation_auth_visibility'] !== 'logged_in'
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'Ungültige navigation_auth_visibility.'
                );
            }

            if (
                $config['navigation_sort_order'] !== null
                && $config['navigation_sort_order'] < 0
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'navigation_sort_order darf nicht negativ sein.'
                );
            }

            if (
                $config['navigation_context_id'] === ''
            ) {
                throw new RuntimeException(
                    'GeneratorManager: '
                    . 'navigation_context_id darf nicht leer sein.'
                );
            }
        }
    }

    /**
     * Lädt eine bestehende Page.
     *
     * @return array<string,string>
     */
    private function loadExistingPage(
        string $pageIdentifier
    ): array {
        $pageIdentifier =
            trim($pageIdentifier);

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
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'GeneratorManager: '
                . 'Prepare Page-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $pageIdentifier,
            $pageIdentifier
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'GeneratorManager: '
                . 'Page-Abfrage fehlgeschlagen: '
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
                sprintf(
                    'GeneratorManager: '
                    . 'Page "%s" wurde nicht gefunden.',
                    $pageIdentifier
                )
            );
        }

        $row =
            $result->fetch_assoc();

        $stmt->close();

        return [
            'page_uuid' =>
                (string)
                $row['page_uuid'],

            'slug' =>
                (string)
                $row['slug'],

            'name' =>
                (string)
                $row['name'],

            'required_permission_id' =>
                (string) (
                    $row[
                        'required_permission_id'
                    ] ?? ''
                ),

            'page_css_id' =>
                (string) (
                    $row[
                        'page_css_id'
                    ] ?? ''
                ),

            'fk_translation_placeholder' =>
                (string) (
                    $row[
                        'fk_translation_placeholder'
                    ] ?? ''
                ),

            'template' =>
                (string) (
                    $row['template']
                    ?? 'default'
                ),

            'meta_title' =>
                (string) (
                    $row['meta_title']
                    ?? ''
                ),

            'meta_description' =>
                (string) (
                    $row['meta_description']
                    ?? ''
                ),

            'enabled' =>
                (string) (
                    $row['enabled']
                    ?? '1'
                ),

            'sort_order' =>
                (string) (
                    $row['sort_order']
                    ?? '0'
                ),

            'context' =>
                (string) (
                    $row['context']
                    ?? 'frontend'
                ),

            'nav_id' =>
                (string) (
                    $row['nav_id']
                    ?? 'generalNav'
                ),

            'auth_visibility' =>
                (string) (
                    $row['auth_visibility']
                    ?? 'public'
                ),
        ];
    }

    /**
     * String normalisieren.
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
     * Nullable String normalisieren.
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
     * Boolean normalisieren.
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
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'yes',
                    'on',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Nullable Integer normalisieren.
     */
    private function nullableInt(
        mixed $value
    ): ?int {
        if ($value === null) {
            return null;
        }

        if (
            is_string($value)
            && trim($value) === ''
        ) {
            return null;
        }

        if (
            is_int($value)
            || is_float($value)
            || is_numeric($value)
        ) {
            return (int) $value;
        }

        return null;
    }
}