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
     * Führt die Generator-Pipeline aus.
     *
     * Reihenfolge:
     *
     * 1. Page
     * 2. Navigation
     * 3. JSON-Formular
     * 4. CRUD
     * 5. Repository
     * 6. Service
     * 7. Controller
     * 8. Plugin
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        $results = [];

        /*
         * ---------------------------------------------------------
         * 1. PAGE
         * ---------------------------------------------------------
         */

        if ($config['own_page']) {
            $pageGenerator = new PageGenerator(
                $this->db
            );

            $pageResult = $pageGenerator->generate(
                $config
            );

            $results['page'] = $pageResult;

            if (
                empty($pageResult['page_uuid'])
                || empty($pageResult['page_slug'])
            ) {
                throw new RuntimeException(
                    'GeneratorManager: PageGenerator hat keine gültige Page zurückgegeben.'
                );
            }

            $config['page_uuid'] =
                (string) $pageResult['page_uuid'];

            $config['page_slug'] =
                (string) $pageResult['page_slug'];
        } else {
            /*
             * -----------------------------------------------------
             * Bestehende Page laden
             * -----------------------------------------------------
             */

            $page = $this->loadExistingPage(
                $config['page']
            );

            $results['page'] = [
                'success' => true,
                'existing' => true,
                ...$page,
            ];

            $config['page_uuid'] =
                $page['page_uuid'];

            $config['page_slug'] =
                $page['slug'];
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

            /*
             * Der NavigationGenerator bekommt die Page-Daten,
             * die gerade vom PageGenerator erzeugt bzw. geladen
             * wurden.
             */
            $navigationConfig = $config;

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
                    $navigationResult['navigation_uuid'];
            }
        }

        /*
         * ---------------------------------------------------------
         * 3. JSON FORM
         * ---------------------------------------------------------
         */

        if ($config['generate_json_form']) {
            $jsonFormGenerator =
                new JsonFormGenerator(
                    $this->db
                );

            $formResult =
                $jsonFormGenerator->generate(
                    $config
                );

            $results['json_form'] =
                $formResult;

            /*
             * Das ist später wichtig für page_config.
             */
            if (
                !empty(
                    $formResult['plugin_content_uuid']
                )
            ) {
                $config['plugin_content_uuid'] =
                    $formResult['plugin_content_uuid'];
            }

            if (
                !empty(
                    $formResult['content_uuid']
                )
            ) {
                $config['plugin_content_uuid'] =
                    $formResult['content_uuid'];
            }
        }

        /*
         * ---------------------------------------------------------
         * 4. CRUD
         * ---------------------------------------------------------
         */

        if ($config['generate_crud']) {
            $generator =
                new CrudHandlerGenerator(
                    $this->db
                );

            $results['crud'] =
                $generator->generate(
                    $config
                );
        }

        /*
         * ---------------------------------------------------------
         * 5. REPOSITORY
         * ---------------------------------------------------------
         */

        if ($config['generate_repository']) {
            $generator =
                new RepositoryGenerator(
                    $this->db
                );

            $results['repository'] =
                $generator->generate(
                    $config
                );
        }

        /*
         * ---------------------------------------------------------
         * 6. SERVICE
         * ---------------------------------------------------------
         */

        if ($config['generate_service']) {
            $generator =
                new ServiceGenerator(
                    $this->db
                );

            $results['service'] =
                $generator->generate(
                    $config
                );
        }

        /*
         * ---------------------------------------------------------
         * 7. CONTROLLER
         * ---------------------------------------------------------
         */

        if ($config['generate_controller']) {
            $generator =
                new ControllerGenerator(
                    $this->db
                );

            $results['controller'] =
                $generator->generate(
                    $config
                );
        }

        /*
         * ---------------------------------------------------------
         * 8. PLUGIN
         * ---------------------------------------------------------
         */

        if ($config['generate_plugin']) {
            $pluginGenerator =
                new PluginRegistrationGenerator(
                    $this->db
                );

            $pluginResult =
                $pluginGenerator->generate(
                    $config
                );

            $results['plugin'] =
                $pluginResult;

            /*
             * Plugin registrieren.
             */
            $results['plugin_registration'] =
                $pluginGenerator->register(
                    $pluginResult
                );

            /*
             * Für spätere page_config-Erzeugung merken.
             */
            if (
                !empty(
                    $pluginResult['plugin_uuid']
                )
            ) {
                $config['plugin_uuid'] =
                    $pluginResult['plugin_uuid'];
            }
        }

        /*
         * ---------------------------------------------------------
         * Ergebnis
         * ---------------------------------------------------------
         */

        return [
            'success' => true,

            'table' =>
                $config['table'],

            'form_type' =>
                $config['form_type'],

            'save_key' =>
                $config['save_key'],

            'page_uuid' =>
                $config['page_uuid'],

            'page_slug' =>
                $config['page_slug'],

            'navigation_uuid' =>
                $config['navigation_uuid'] ?? null,

            'plugin_content_uuid' =>
                $config['plugin_content_uuid'] ?? null,

            'plugin_uuid' =>
                $config['plugin_uuid'] ?? null,

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
        $table =
            $this->stringValue(
                $config['table']
                    ?? $config['db_table']
                    ?? ''
            );

        $config['table'] =
            $table;

        $config['db_table'] =
            $table;

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
         * Page
         */
        $config['own_page'] =
            $this->toBool(
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

        /*
         * Navigation
         */
        $config['create_navigation'] =
            $this->toBool(
                $config['create_navigation']
                    ?? false
            );

        $config['navigation_parent_id'] =
            $this->stringValue(
                $config['navigation_parent_id']
                    ?? ''
            );

        $config['navigation_title'] =
            $this->stringValue(
                $config['navigation_title']
                    ?? ''
            );

        $config['navigation_slug'] =
            $this->stringValue(
                $config['navigation_slug']
                    ?? ''
            );

        $config['navigation_translation_placeholder'] =
            $this->stringValue(
                $config[
                    'navigation_translation_placeholder'
                ] ?? ''
            );

        $config['navigation_sort_order'] =
            $this->nullableInt(
                $config['navigation_sort_order']
                    ?? null
            );

        $config['navigation_enabled'] =
            $this->toBool(
                $config['navigation_enabled']
                    ?? true
            );

        $config['navigation_align'] =
            $this->stringValue(
                $config['navigation_align']
                    ?? 'left'
            );

        $config['navigation_context_id'] =
            $this->stringValue(
                $config['navigation_context_id']
                    ?? 'admin'
            );

        $config['navigation_permission_id'] =
            $this->stringValue(
                $config[
                    'navigation_permission_id'
                ] ?? 'perm-view-admin'
            );

        $config['navigation_auth_visibility'] =
            $this->stringValue(
                $config[
                    'navigation_auth_visibility'
                ] ?? 'public'
            );

        /*
         * Weitere Generatoroptionen
         */
        $config['multi_table'] =
            $this->toBool(
                $config['multi_table']
                    ?? false
            );

        $config['entity'] =
            $this->stringValue(
                $config['entity']
                    ?? ''
            );

        $config['module'] =
            $this->stringValue(
                $config['module']
                    ?? ''
            );

        $config['namespace'] =
            $this->stringValue(
                $config['namespace']
                    ?? ''
            );

        $config['output_path'] =
            $this->stringValue(
                $config['output_path']
                    ?? ''
            );

        /*
         * Generation flags
         */
        $config['generate_all'] =
            $this->toBool(
                $config['generate_all']
                    ?? true
            );

        if ($config['generate_all']) {
            $config['generate_json_form'] = true;
            $config['generate_crud'] = true;
            $config['generate_repository'] = true;
            $config['generate_service'] = true;
            $config['generate_controller'] = true;
            $config['generate_plugin'] = true;
        } else {
            $config['generate_json_form'] =
                $this->toBool(
                    $config['generate_json_form']
                        ?? true
                );

            $config['generate_crud'] =
                $this->toBool(
                    $config['generate_crud']
                        ?? true
                );

            $config['generate_repository'] =
                $this->toBool(
                    $config['generate_repository']
                        ?? true
                );

            $config['generate_service'] =
                $this->toBool(
                    $config['generate_service']
                        ?? true
                );

            $config['generate_controller'] =
                $this->toBool(
                    $config['generate_controller']
                        ?? true
                );

            $config['generate_plugin'] =
                $this->toBool(
                    $config['generate_plugin']
                        ?? true
                );
        }

        /*
         * Dispatcher
         */
        $config['action'] =
            $this->stringValue(
                $config['action']
                    ?? 'save'
            );

        $config['form_id'] =
            $this->stringValue(
                $config['form_id']
                    ?? 'generator_form'
            );

        /*
         * Wenn keine eigene Page erzeugt wird,
         * kann auch keine neue Navigation aus
         * diesem Generator erzeugt werden.
         */
        if (!$config['own_page']) {
            $config['create_navigation'] = false;
            $config['navigation_parent_id'] = '';
        }

        return $config;
    }

    /**
     * Validiert die Konfiguration.
     *
     * @param array<string,mixed> $config
     */
    private function validateConfig(
        array $config
    ): void {
        /*
         * Tabelle
         */
        if ($config['table'] === '') {
            throw new RuntimeException(
                'GeneratorManager: Keine Tabelle angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_]+$/',
            $config['table']
        )) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger Tabellenname.'
            );
        }

        /*
         * Form Type
         */
        if (!in_array(
            $config['form_type'],
            ['entry', 'simple'],
            true
        )) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger Formulartyp.'
            );
        }

        /*
         * Save Key
         */
        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'GeneratorManager: Kein save_key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
            $config['save_key']
        )) {
            throw new RuntimeException(
                'GeneratorManager: Ungültiger save_key.'
            );
        }

        /*
         * Existing Page
         */
        if (!$config['own_page']) {
            if ($config['page'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: Bei own_page=no muss eine bestehende Page angegeben werden.'
                );
            }
        }

        /*
         * Navigation
         */
        if ($config['create_navigation']) {
            if ($config['navigation_title'] === '') {
                throw new RuntimeException(
                    'GeneratorManager: navigation_title fehlt.'
                );
            }

            if (
                $config['navigation_parent_id'] !== ''
                && !preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $config['navigation_parent_id']
                )
            ) {
                throw new RuntimeException(
                    'GeneratorManager: Ungültige navigation_parent_id.'
                );
            }

            if (!in_array(
                $config['navigation_align'],
                ['left', 'right'],
                true
            )) {
                throw new RuntimeException(
                    'GeneratorManager: Ungültige navigation_align.'
                );
            }

            if (!in_array(
                $config['navigation_auth_visibility'],
                [
                    'public',
                    'guest',
                    'logged_in',
                ],
                true
            )) {
                throw new RuntimeException(
                    'GeneratorManager: Ungültige auth_visibility.'
                );
            }

            if (
                $config['navigation_sort_order'] !== null
                && $config['navigation_sort_order'] < 0
            ) {
                throw new RuntimeException(
                    'GeneratorManager: navigation_sort_order darf nicht negativ sein.'
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
                enabled,
                sort_order
            FROM page
            WHERE page_uuid = ?
               OR slug = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException(
                'GeneratorManager: Prepare Page-Abfrage fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            'ss',
            $pageIdentifier,
            $pageIdentifier
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'GeneratorManager: Page-Abfrage fehlgeschlagen: '
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
                    'GeneratorManager: Page "%s" wurde nicht gefunden.',
                    $pageIdentifier
                )
            );
        }

        $row =
            $result->fetch_assoc();

        $stmt->close();

        return [
            'page_uuid' =>
                (string) $row['page_uuid'],

            'slug' =>
                (string) $row['slug'],

            'name' =>
                (string) $row['name'],

            'required_permission_id' =>
                (string) (
                    $row['required_permission_id']
                    ?? ''
                ),

            'page_css_id' =>
                (string) (
                    $row['page_css_id']
                    ?? ''
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
     * Boolean normalisieren.
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
                strtolower(
                    trim($value)
                ),
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
        if (
            $value === null
            || $value === ''
        ) {
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
            'GeneratorManager: Ungültiger Integer-Wert.'
        );
    }
}