<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;
use Throwable;

/**
 * GenerateAllGenerator
 *
 * Entry point für das Generator-Formular.
 *
 * Verantwortlich für:
 * - POST-Daten normalisieren
 * - verschachtelte Formdaten abflachen
 * - Defaults setzen
 * - Eingaben validieren
 * - GeneratorManager aufrufen
 *
 * Nicht verantwortlich für:
 * - Page-Erstellung
 * - Navigation-Erstellung
 * - Formular-Erstellung
 * - CRUD-Erstellung
 * - Repository/Service/Controller-Erstellung
 * - Plugin-Registrierung
 */
final class GenerateAllGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Hauptentrypoint für den FormActionDispatcher.
     *
     * @param array<string,mixed> $postData
     * @return array<string,mixed>
     */
    public function handle(array $postData): array
    {
        try {
            $config = $this->buildConfig($postData);

            $this->validateConfig($config);

            $manager = new GeneratorManager($this->db);

            if (!method_exists($manager, 'generate')) {
                throw new RuntimeException(
                    'GeneratorManager::generate() wurde nicht gefunden.'
                );
            }

            $results = $manager->generate($config);

            return [
                'success' => true,
                'message' => 'Generator erfolgreich ausgeführt.',
                'config'  => $config,
                'results' => $results,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error'   => [
                    'class' => $e::class,
                    'code'  => $e->getCode(),
                ],
            ];
        }
    }

    /**
     * Baut die interne Generator-Konfiguration.
     *
     * @param array<string,mixed> $postData
     * @return array<string,mixed>
     */
    private function buildConfig(array $postData): array
    {
        $data = $this->flattenPostData($postData);

        /*
         * ---------------------------------------------------------
         * Grunddaten
         * ---------------------------------------------------------
         */

        $table = $this->stringValue(
            $data['table']
                ?? $data['db_table']
                ?? ''
        );

        $formType = $this->stringValue(
            $data['form_type'] ?? 'entry'
        );

        $saveKey = $this->stringValue(
            $data['save_key']
                ?? $data['form_key']
                ?? ''
        );

        /*
         * ---------------------------------------------------------
         * Page
         * ---------------------------------------------------------
         */

        $ownPage = $this->boolValue(
            $data['own_page'] ?? false
        );

        $page = $this->stringValue(
            $data['page']
                ?? $data['page_uuid']
                ?? ''
        );

        $slugMode = $this->stringValue(
            $data['slug_mode'] ?? 'new'
        );

        $slug = $this->stringValue(
            $data['slug'] ?? ''
        );

        $newSlug = $this->stringValue(
            $data['new_slug'] ?? ''
        );

        /*
         * ---------------------------------------------------------
         * Navigation
         * ---------------------------------------------------------
         *
         * Navigation wird separat durch NavigationGenerator
         * erzeugt.
         *
         * Leer = Hauptnavigation
         * UUID  = Unterpunkt unter diesem Parent
         */

        $createNavigation = $this->boolValue(
            $data['create_navigation'] ?? false
        );

        $navigationParentId = $this->stringValue(
            $data['navigation_parent_id']
                ?? $data['parent_id']
                ?? ''
        );

        $navigationTitle = $this->stringValue(
            $data['navigation_title']
                ?? $data['title']
                ?? ''
        );

        $navigationSlug = $this->stringValue(
            $data['navigation_slug'] ?? ''
        );

        $navigationTranslationPlaceholder = $this->stringValue(
            $data['navigation_translation_placeholder'] ?? ''
        );

        $navigationSortOrder = $this->nullableInt(
            $data['navigation_sort_order'] ?? null
        );

        $navigationEnabled = $this->boolValue(
            $data['navigation_enabled'] ?? true
        );

        $navigationAlign = $this->stringValue(
            $data['navigation_align'] ?? 'left'
        );

        $navigationContextId = $this->stringValue(
            $data['navigation_context_id'] ?? 'admin'
        );

        $navigationPermissionId = $this->stringValue(
            $data['navigation_permission_id']
                ?? 'perm-view-admin'
        );

        $navigationAuthVisibility = $this->stringValue(
            $data['navigation_auth_visibility'] ?? 'public'
        );

        /*
         * ---------------------------------------------------------
         * Generator Optionen
         * ---------------------------------------------------------
         */

        $multiTable = $this->boolValue(
            $data['multi_table'] ?? false
        );

        $entity = $this->stringValue(
            $data['entity'] ?? ''
        );

        $module = $this->stringValue(
            $data['module'] ?? ''
        );

        $namespace = $this->stringValue(
            $data['namespace'] ?? ''
        );

        $outputPath = $this->stringValue(
            $data['output_path'] ?? ''
        );

        /*
         * ---------------------------------------------------------
         * Aktionen / IDs
         * ---------------------------------------------------------
         */

        $action = $this->stringValue(
            $data['action'] ?? 'save'
        );

        $formId = $this->stringValue(
            $data['form_id'] ?? 'generator_form'
        );

        /*
         * ---------------------------------------------------------
         * Generation Flags
         * ---------------------------------------------------------
         *
         * Alle Generatoren werden über "generate_all" aktiviert.
         * Die einzelnen Flags bleiben trotzdem explizit, damit
         * GeneratorManager und zukünftige Erweiterungen sauber
         * damit arbeiten können.
         */

        $generateAll = true;

        return [
            /*
             * Database / Form
             */
            'table'      => $table,
            'db_table'   => $table,
            'form_type'  => $formType,
            'save_key'   => $saveKey,

            /*
             * Page
             */
            'own_page'   => $ownPage,
            'page'       => $page,
            'page_uuid'  => '',
            'page_slug'  => '',

            'slug_mode'  => $slugMode,
            'slug'       => $slug,
            'new_slug'   => $newSlug,

            /*
             * Navigation
             */
            'create_navigation' =>
                $ownPage
                    ? $createNavigation
                    : false,

            'navigation_parent_id' =>
                $ownPage
                    ? $navigationParentId
                    : '',

            'navigation_title' =>
                $ownPage
                    ? $navigationTitle
                    : '',

            'navigation_slug' =>
                $ownPage
                    ? $navigationSlug
                    : '',

            'navigation_translation_placeholder' =>
                $ownPage
                    ? $navigationTranslationPlaceholder
                    : '',

            'navigation_sort_order' =>
                $ownPage
                    ? $navigationSortOrder
                    : null,

            'navigation_enabled' =>
                $navigationEnabled,

            'navigation_align' =>
                $navigationAlign,

            'navigation_context_id' =>
                $navigationContextId,

            'navigation_permission_id' =>
                $navigationPermissionId,

            'navigation_auth_visibility' =>
                $navigationAuthVisibility,

            /*
             * Weitere Generatoroptionen
             */
            'multi_table'  => $multiTable,
            'entity'       => $entity,
            'module'       => $module,
            'namespace'    => $namespace,
            'output_path'  => $outputPath,

            /*
             * Generator flags
             */
            'generate_all'        => $generateAll,
            'generate_json_form'  => true,
            'generate_crud'       => true,
            'generate_repository' => true,
            'generate_service'    => true,
            'generate_controller' => true,
            'generate_plugin'     => true,

            /*
             * Dispatcher / Form
             */
            'action'  => $action,
            'form_id' => $formId,
        ];
    }

    /**
     * Validiert die Generator-Konfiguration.
     *
     * @param array<string,mixed> $config
     */
    private function validateConfig(array $config): void
    {
        /*
         * Tabelle
         */
        if ($config['table'] === '') {
            throw new RuntimeException(
                'Keine Datenbanktabelle angegeben.'
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['table'])) {
            throw new RuntimeException(
                'Ungültiger Tabellenname.'
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
                'Ungültiger Formulartyp.'
            );
        }

        /*
         * Save Key
         */
        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'Kein Form Key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
            $config['save_key']
        )) {
            throw new RuntimeException(
                'Ungültiger Form Key.'
            );
        }

        /*
         * Page
         */
        if ($config['own_page']) {
            if (!in_array(
                $config['slug_mode'],
                ['existing', 'new'],
                true
            )) {
                throw new RuntimeException(
                    'Ungültige Slug-Aktion.'
                );
            }

            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei bestehendem Slug muss ein Slug angegeben werden.'
                );
            }

            if (
                $config['slug_mode'] === 'new'
                && $config['new_slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei neuem Slug muss ein neuer Slug angegeben werden.'
                );
            }

            if (
                $config['slug_mode'] === 'new'
                && !preg_match(
                    '/^[a-z0-9][a-z0-9-]*$/',
                    $config['new_slug']
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger neuer Slug.'
                );
            }
        }

        /*
         * Navigation
         */
        if ($config['create_navigation']) {
            if ($config['navigation_context_id'] === '') {
                throw new RuntimeException(
                    'Für die Navigation fehlt context_id.'
                );
            }

            if ($config['navigation_title'] === '') {
                throw new RuntimeException(
                    'Für die Navigation fehlt der Navigationstitel.'
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
                    'Ungültige navigation_parent_id.'
                );
            }

            if (!in_array(
                $config['navigation_align'],
                ['left', 'right'],
                true
            )) {
                throw new RuntimeException(
                    'Ungültige Navigation-Ausrichtung.'
                );
            }

            if (!in_array(
                $config['navigation_auth_visibility'],
                ['public', 'guest', 'logged_in'],
                true
            )) {
                throw new RuntimeException(
                    'Ungültige Navigation-Sichtbarkeit.'
                );
            }

            if (
                $config['navigation_sort_order'] !== null
                && $config['navigation_sort_order'] < 0
            ) {
                throw new RuntimeException(
                    'navigation_sort_order darf nicht negativ sein.'
                );
            }
        }
    }

    /**
     * Flacht verschachtelte POST-Daten ab.
     *
     * Beispiel:
     *
     * [
     *     'generator' => [
     *         'table' => 'address'
     *     ]
     * ]
     *
     * wird zu:
     *
     * [
     *     'table' => 'address'
     * ]
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function flattenPostData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($this->flattenPostData($value) as $childKey => $childValue) {
                    if (!array_key_exists($childKey, $result)) {
                        $result[$childKey] = $childValue;
                    }
                }

                continue;
            }

            $result[(string) $key] = $value;
        }

        return $result;
    }

    /**
     * String sicher aus POST übernehmen.
     */
    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Boolean aus typischen HTML-POST-Werten.
     */
    private function boolValue(mixed $value): bool
    {
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
    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match('/^-?\d+$/', trim($value))
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            'Ungültiger numerischer Wert.'
        );
    }
}
