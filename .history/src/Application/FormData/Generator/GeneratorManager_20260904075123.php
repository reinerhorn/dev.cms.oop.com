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
     * Führt alle aktivierten Generatoren aus.
     *
     * Reihenfolge:
     *
     * 1. Page
     * 2. JSON Form
     * 3. CRUD Handler
     * 4. Repository
     * 5. Service
     * 6. Controller
     * 7. Plugin
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
         *
         * Nur wenn eine eigene neue Page gewünscht ist.
         */
        if ($config['own_page'] === 'yes') {
            $pageGenerator = new PageGenerator($this->db);

            $page = $pageGenerator->generate($config);

            $results['page'] = $page;

            /*
             * Die neu erzeugte Page wird für alle
             * nachfolgenden Generatoren verwendet.
             */
            $config['page'] = $page['page_uuid'];

            /*
             * Zusätzlich Page-Slug speichern.
             */
            $config['page_uuid'] = $page['page_uuid'];
            $config['page_slug'] = $page['slug'];
        }

        /*
         * ---------------------------------------------------------
         * 2. JSON FORMULAR
         * ---------------------------------------------------------
         */
        if ($config['generate_json_form']) {
            $generator = new JsonFormGenerator($this->db);

            $results['json_form'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 3. CRUD HANDLER
         * ---------------------------------------------------------
         */
        if ($config['generate_crud']) {
            $generator = new CrudHandlerGenerator($this->db);

            $results['crud'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 4. REPOSITORY
         * ---------------------------------------------------------
         */
        if ($config['generate_repository']) {
            $generator = new RepositoryGenerator($this->db);

            $results['repository'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 5. SERVICE
         * ---------------------------------------------------------
         */
        if ($config['generate_service']) {
            $generator = new ServiceGenerator($this->db);

            $results['service'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 6. CONTROLLER
         * ---------------------------------------------------------
         */
        if ($config['generate_controller']) {
            $generator = new ControllerGenerator($this->db);

            $results['controller'] = $generator->generate($config);
        }

        /*
         * ---------------------------------------------------------
         * 7. PLUGIN ERZEUGEN + REGISTRIEREN
         * ---------------------------------------------------------
         */
        if ($config['generate_plugin']) {
            $pluginGenerator = new PluginRegistrationGenerator(
                $this->db
            );

            $plugin = $pluginGenerator->generate($config);

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

                'entity' => $config['entity'],
                'module' => $config['module'],
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
            /*
             * Datenbank
             */
            'table' => '',
            'db_table' => '',

            /*
             * Formular
             */
            'form_type' => 'entry',
            'save_key' => '',

            /*
             * Page
             */
            'own_page' => 'no',
            'page' => '',

            /*
             * Slug
             */
            'slug_mode' => 'existing',
            'slug' => '',
            'new_slug' => '',

            /*
             * Generator
             */
            'multi_table' => false,
            'entity' => '',
            'module' => '',
            'namespace' => '',
            'output_path' => '',

            /*
             * Generator Flags
             */
            'generate_json_form' => true,
            'generate_crud' => true,
            'generate_repository' => true,
            'generate_service' => true,
            'generate_controller' => true,
            'generate_plugin' => true,

            /*
             * Action
             */
            'action' => '',
            'form_id' => '',
        ];

        $config = array_merge(
            $defaults,
            $config
        );

        /*
         * ---------------------------------------------------------
         * COMPATIBILITY FLAGS
         * ---------------------------------------------------------
         *
         * GenerateAllGenerator verwendet teilweise
         * ältere Flag-Namen.
         */

        if (
            array_key_exists('generate_form', $config)
        ) {
            $config['generate_json_form'] = $config['generate_form'];
        }

        if (
            array_key_exists('generate_handler', $config)
        ) {
            $config['generate_crud'] = $config['generate_handler'];
        }

        if (
            array_key_exists('register_plugin', $config)
        ) {
            $config['generate_plugin'] = $config['register_plugin'];
        }

        /*
         * db_table ist Alias für table.
         */
        if (
            $config['table'] === ''
            && $config['db_table'] !== ''
        ) {
            $config['table'] = $config['db_table'];
        }

        if (
            $config['db_table'] === ''
            && $config['table'] !== ''
        ) {
            $config['db_table'] = $config['table'];
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
            $config[$field] = trim(
                (string) ($config[$field] ?? '')
            );
        }

        /*
         * Entity automatisch bestimmen.
         */
        if (
            $config['entity'] === ''
            && $config['table'] !== ''
        ) {
            $config['entity'] = $this->tableToEntity(
                $config['table']
            );
        }

        /*
         * Modul automatisch bestimmen.
         */
        if (
            $config['module'] === ''
            && $config['entity'] !== ''
        ) {
            $config['module'] = $config['entity'];
        }

        /*
         * Boolean-Werte normalisieren.
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
            $config[$field] = $this->toBool(
                $config[$field]
            );
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

        if (
            !preg_match(
                '/^[a-zA-Z0-9_]+$/',
                $config['table']
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Tabellenname: '
                . $config['table']
            );
        }

        /*
         * Save Key
         */
        if ($config['save_key'] === '') {
            throw new RuntimeException(
                'Kein Save Key angegeben.'
            );
        }

        if (
            !preg_match(
                '/^[a-zA-Z0-9_-]+$/',
                $config['save_key']
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Save Key: '
                . $config['save_key']
            );
        }

        /*
         * Form Type
         */
        if (
            !in_array(
                $config['form_type'],
                ['entry', 'simple'],
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Formulartyp: '
                . $config['form_type']
            );
        }

        /*
         * Eigene Page
         */
        if (
            !in_array(
                $config['own_page'],
                ['yes', 'no'],
                true
            )
        ) {
            throw new RuntimeException(
                'Ungültiger Wert für own_page: '
                . $config['own_page']
            );
        }

        /*
         * ---------------------------------------------------------
         * VORHANDENE PAGE
         * ---------------------------------------------------------
         */
        if (
            $config['own_page'] === 'no'
            && $config['page'] === ''
        ) {
            throw new RuntimeException(
                'Bei own_page=no muss eine vorhandene Page ausgewählt werden.'
            );
        }

        /*
         * ---------------------------------------------------------
         * NEUE PAGE
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'yes') {
            if (
                !in_array(
                    $config['slug_mode'],
                    ['existing', 'new'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Ungültiger slug_mode: '
                    . $config['slug_mode']
                );
            }

            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new RuntimeException(
                    'Bei slug_mode=existing muss ein Slug ausgewählt werden.'
                );
            }

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
                    'Ungültiger neuer Slug: '
                    . $config['new_slug']
                );
            }
        }
    }

    /**
     * Erzeugt aus einem Tabellennamen einen Entity-Namen.
     *
     * Beispiele:
     *
     * address
     *     -> Address
     *
     * customer_address
     *     -> CustomerAddress
     */
    private function tableToEntity(
        string $table
    ): string {
        $table = trim($table);

        if ($table === '') {
            return '';
        }

        $parts = preg_split(
            '/[^a-zA-Z0-9]+/',
            $table
        );

        if ($parts === false) {
            return '';
        }

        $parts = array_filter(
            $parts,
            static fn (string $part): bool =>
                $part !== ''
        );

        $parts = array_map(
            static fn (string $part): string =>
                ucfirst(strtolower($part)),
            $parts
        );

        return implode('', $parts);
    }

    /**
     * Normalisiert verschiedene Eingabeformen
     * in einen Boolean.
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
                    'on',
                    'ja',
                ],
                true
            );
        }

        return !empty($value);
    }
}