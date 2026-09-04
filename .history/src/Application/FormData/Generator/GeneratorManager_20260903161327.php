<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class GeneratorManager
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Führt alle Generatoren aus.
     *
     * Reihenfolge:
     * 1. JSON-Formular erzeugen
     * 2. CRUD Handler erzeugen
     * 3. Repository erzeugen
     * 4. Service erzeugen
     * 5. Controller erzeugen
     * 6. Plugin erzeugen und registrieren
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        $results = [];

        /*
         * ---------------------------------------------------------
         * 1. JSON FORMULAR
         * ---------------------------------------------------------
         */
        if ($config['generate_json_form']) {
            $generator = new JsonFormGenerator($this->db);

            $results['json_form'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 2. CRUD HANDLER
         * ---------------------------------------------------------
         */
        if ($config['generate_crud']) {
            $generator = new CrudHandlerGenerator($this->db);

            $results['crud'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 3. REPOSITORY
         * ---------------------------------------------------------
         */
        if ($config['generate_repository']) {
            $generator = new RepositoryGenerator($this->db);

            $results['repository'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 4. SERVICE
         * ---------------------------------------------------------
         */
        if ($config['generate_service']) {
            $generator = new ServiceGenerator($this->db);

            $results['service'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 5. CONTROLLER
         * ---------------------------------------------------------
         */
        if ($config['generate_controller']) {
            $generator = new ControllerGenerator($this->db);

            $results['controller'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 6. PLUGIN ERZEUGEN + REGISTRIEREN
         * ---------------------------------------------------------
         *
         * generate()
         *     ↓
         * Plugin-Array
         *
         * register()
         *     ↓
         * INSERT INTO plugin
         */
        if ($config['generate_plugin']) {
            $pluginGenerator = new PluginRegistrationGenerator($this->db);

            $plugin = $pluginGenerator->generate($config);

            /*
             * Tatsächlich in der Datenbank registrieren.
             */
            $pluginGenerator->register($plugin);

            $results['plugin'] = $plugin;
        }

        /*
         * ---------------------------------------------------------
         * SUMMARY
         * ---------------------------------------------------------
         */
        return [
            'success' => true,
            'generated' => count($results),
            'timestamp' => date('Y-m-d H:i:s'),

            'config' => [
                'table' => $config['table'],
                'form_type' => $config['form_type'],
                'save_key' => $config['save_key'],
                'own_page' => $config['own_page'],
                'page' => $config['page'],
                'slug_mode' => $config['slug_mode'],
                'slug' => $config['slug'],
                'new_slug' => $config['new_slug'],
            ],

            'results' => $results,
        ];
    }

    /**
     * Normalisiert die Generator-Konfiguration.
     */
    private function normalizeConfig(array $config): array
    {
        $defaults = [
            'table' => '',
            'db_table' => '',

            'form_type' => 'entry',
            'save_key' => '',

            'own_page' => 'no',
            'page' => '',

            'slug_mode' => 'existing',
            'slug' => '',
            'new_slug' => '',

            'multi_table' => false,
            'entity' => '',
            'module' => '',
            'namespace' => '',
            'output_path' => '',

            'generate_json_form' => true,
            'generate_crud' => true,
            'generate_repository' => true,
            'generate_service' => true,
            'generate_controller' => true,
            'generate_plugin' => true,

            'action' => '',
            'form_id' => '',
        ];

        $config = array_merge($defaults, $config);

        /*
         * db_table ist nur ein Alias für table.
         */
        if ($config['table'] === '' && $config['db_table'] !== '') {
            $config['table'] = $config['db_table'];
        }

        if ($config['db_table'] === '' && $config['table'] !== '') {
            $config['db_table'] = $config['table'];
        }

        /*
         * Boolean-Werte sauber normalisieren.
         */
        $booleanFields = [
            'multi_table',
            'generate_json_form',
            'generate_crud',
            'generate_repository',
            'generate_service',
            'generate_controller',
            'generate_plugin',
        ];

        foreach ($booleanFields as $field) {
            $config[$field] = filter_var(
                $config[$field],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        /*
         * Strings normalisieren.
         */
        foreach (
            [
                'table',
                'db_table',
                'form_type',
                'save_key',
                'own_page',
                'page',
                'slug_mode',
                'slug',
                'new_slug',
                'entity',
                'module',
                'namespace',
                'output_path',
                'action',
                'form_id',
            ] as $field
        ) {
            if (isset($config[$field])) {
                $config[$field] = trim((string) $config[$field]);
            }
        }

        return $config;
    }

    /**
     * Validiert die Generator-Konfiguration.
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
                'Ungültiger Tabellenname: ' . $config['table']
            );
        }

        /*
         * Form Key
         */
        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'Kein Form Key angegeben.'
            );
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config['save_key'])) {
            throw new RuntimeException(
                'Ungültiger Form Key: ' . $config['save_key']
            );
        }

        /*
         * Formulartyp
         */
        if (!in_array(
            $config['form_type'],
            ['entry', 'simple'],
            true
        )) {
            throw new RuntimeException(
                'Ungültiger Formulartyp: ' . $config['form_type']
            );
        }

        /*
         * Eigene Page?
         */
        if (!in_array(
            $config['own_page'],
            ['yes', 'no'],
            true
        )) {
            throw new RuntimeException(
                'Ungültiger Wert für own_page: ' . $config['own_page']
            );
        }

        /*
         * ---------------------------------------------------------
         * KEINE NEUE PAGE
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'no') {
            if ($config['page'] === '') {
                throw new RuntimeException(
                    'Bei own_page=no muss eine vorhandene Page ausgewählt werden.'
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * NEUE PAGE
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'yes') {
            if (!in_array(
                $config['slug_mode'],
                ['existing', 'new'],
                true
            )) {
                throw new RuntimeException(
                    'Ungültiger slug_mode: ' . $config['slug_mode']
                );
            }

            /*
             * Vorhandenen Slug verwenden.
             */
            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei slug_mode=existing muss ein Slug ausgewählt werden.'
                );
            }

            /*
             * Neuen Slug anlegen.
             */
            if (
                $config['slug_mode'] === 'new'
                && $config['new_slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei slug_mode=new muss ein neuer Slug angegeben werden.'
                );
            }

            if (
                $config['slug_mode'] === 'new'
                && !preg_match(
                    '/^[a-zA-Z0-9_-]+$/',
                    $config['new_slug']
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger neuer Slug: ' . $config['new_slug']
                );
            }
        }
    }
}
