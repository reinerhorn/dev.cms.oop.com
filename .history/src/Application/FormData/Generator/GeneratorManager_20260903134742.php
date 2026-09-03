<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use InvalidArgumentException;
use mysqli;

final class GeneratorManager
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Central entry point for all generators.
     *
     * Expected configuration:
     *
     * [
     *     'table'               => 'address',
     *     'form_type'           => 'entry',
     *     'save_key'            => 'adressen_form',
     *     'plugin_key'          => 'adressen_form',
     *
     *     'entity'              => 'Address',
     *     'module'              => 'Address',
     *     'namespace'           => 'CMS\\Application\\Address',
     *     'output_path'         => 'src/Application/Address',
     *
     *     'own_page'            => 'yes',
     *     'page'                => '',
     *     'slug_mode'           => 'new',
     *     'slug'                => '',
     *     'new_slug'            => 'adressen',
     *
     *     'generate_all'        => true,
     *     'generate_form'       => true,
     *     'generate_handler'    => true,
     *     'generate_repository' => true,
     *     'generate_service'    => true,
     *     'generate_controller' => true,
     *     'register_plugin'     => true,
     *
     *     'multi_table'         => false,
     * ]
     */
    public function generate(array $config): array
    {
        error_log(
            '========== GENERATOR MANAGER ENTER =========='
        );

        error_log(
            'GENERATOR MANAGER INPUT: ' .
            print_r($config, true)
        );

        /*
         * Normalize the configuration once.
         *
         * Every child generator receives exactly the
         * same normalized configuration.
         */
        $config = $this->normalizeConfig($config);

        error_log(
            'GENERATOR MANAGER NORMALIZED CONFIG: ' .
            print_r($config, true)
        );

        /*
         * Validate before executing any generator.
         */
        $this->validateConfig($config);

        error_log(
            'GENERATOR MANAGER VALIDATION OK - TABLE: [' .
            $config['table'] .
            ']'
        );

        $results = [];

        /*
         * ---------------------------------------------------------
         * FORM
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_form'])) {
            error_log(
                'GENERATOR MANAGER -> JsonFormGenerator'
            );

            $jsonGenerator = new JsonFormGenerator(
                $this->db
            );

            $results['form'] =
                $jsonGenerator->generate($config);

            error_log(
                'GENERATOR MANAGER <- JsonFormGenerator: ' .
                print_r(
                    $results['form'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * HANDLER
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_handler'])) {
            error_log(
                'GENERATOR MANAGER -> CrudHandlerGenerator'
            );

            $crudGenerator =
                new CrudHandlerGenerator(
                    $this->db
                );

            $results['handler'] =
                $crudGenerator->generate(
                    $config
                );

            error_log(
                'GENERATOR MANAGER <- CrudHandlerGenerator: ' .
                print_r(
                    $results['handler'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * REPOSITORY
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_repository'])) {
            error_log(
                'GENERATOR MANAGER -> RepositoryGenerator'
            );

            $repositoryGenerator =
                new RepositoryGenerator(
                    $this->db
                );

            $results['repository'] =
                $repositoryGenerator->generate(
                    $config
                );

            error_log(
                'GENERATOR MANAGER <- RepositoryGenerator: ' .
                print_r(
                    $results['repository'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * SERVICE
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_service'])) {
            error_log(
                'GENERATOR MANAGER -> ServiceGenerator'
            );

            $serviceGenerator =
                new ServiceGenerator(
                    $this->db
                );

            $results['service'] =
                $serviceGenerator->generate(
                    $config
                );

            error_log(
                'GENERATOR MANAGER <- ServiceGenerator: ' .
                print_r(
                    $results['service'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * CONTROLLER
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_controller'])) {
            error_log(
                'GENERATOR MANAGER -> ControllerGenerator'
            );

            $controllerGenerator =
                new ControllerGenerator(
                    $this->db
                );

            $results['controller'] =
                $controllerGenerator->generate(
                    $config
                );

            error_log(
                'GENERATOR MANAGER <- ControllerGenerator: ' .
                print_r(
                    $results['controller'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * PLUGIN REGISTRATION
         * ---------------------------------------------------------
         */
        if (!empty($config['register_plugin'])) {
            error_log(
                'GENERATOR MANAGER -> PluginRegistrationGenerator'
            );

            $pluginGenerator =
                new PluginRegistrationGenerator(
                    $this->db
                );

            $results['plugin'] =
                $pluginGenerator->generate(
                    $config
                );

            error_log(
                'GENERATOR MANAGER <- PluginRegistrationGenerator: ' .
                print_r(
                    $results['plugin'],
                    true
                )
            );
        }

        /*
         * ---------------------------------------------------------
         * SUMMARY
         * ---------------------------------------------------------
         */
        if (!empty($config['generate_all'])) {
            $results['summary'] = [
                'entity' => $config['entity'],
                'table' => $config['table'],
                'form_type' => $config['form_type'],
                'save_key' => $config['save_key'],

                'own_page' => $config['own_page'],
                'page' => $config['page'],
                'slug_mode' => $config['slug_mode'],
                'slug' => $config['slug'],
                'new_slug' => $config['new_slug'],

                'module' => $config['module'],
                'namespace' => $config['namespace'],
                'output_path' => $config['output_path'],

                'generated_files' =>
                    array_keys($results),
            ];
        }

        /*
         * ---------------------------------------------------------
         * RESPONSE
         * ---------------------------------------------------------
         */
        $response = [
            'success' => true,
            'generated' => count($results),
            'timestamp' => date(
                'Y-m-d H:i:s'
            ),
            'config' => $config,
            'results' => $results,
        ];

        error_log(
            '========== GENERATOR MANAGER COMPLETE =========='
        );

        error_log(
            'GENERATOR MANAGER RESPONSE: ' .
            print_r(
                $response,
                true
            )
        );

        return $response;
    }

    /**
     * Normalize the configuration once for all generators.
     */
    private function normalizeConfig(
        array $config
    ): array {
        /*
         * ---------------------------------------------------------
         * DEFAULTS
         * ---------------------------------------------------------
         */
        $defaults = [
            /*
             * Database / generator
             */
            'entity' => '',
            'table' => '',
            'db_table' => '',

            /*
             * PHP structure
             */
            'namespace' => '',
            'module' => '',
            'plugin_key' => '',
            'save_key' => '',
            'output_path' => '',

            /*
             * Form
             */
            'form_type' => 'entry',

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
             * Generator switches
             */
            'generate_all' => false,
            'generate_form' => false,
            'generate_handler' => false,
            'generate_repository' => false,
            'generate_service' => false,
            'generate_controller' => false,
            'register_plugin' => false,

            /*
             * Misc
             */
            'multi_table' => false,
        ];

        $config = array_merge(
            $defaults,
            $config
        );

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */
        $table = trim(
            (string) $config['table']
        );

        if ($table === '') {
            $table = trim(
                (string) $config['db_table']
            );
        }

        $config['table'] = $table;

        /*
         * Keep db_table synchronized.
         */
        $config['db_table'] = $table;

        /*
         * ---------------------------------------------------------
         * FORM TYPE
         * ---------------------------------------------------------
         */
        $config['form_type'] = trim(
            (string) $config['form_type']
        );

        if ($config['form_type'] === '') {
            $config['form_type'] = 'entry';
        }

        /*
         * ---------------------------------------------------------
         * SAVE KEY
         * ---------------------------------------------------------
         */
        $saveKey = trim(
            (string) $config['save_key']
        );

        if ($saveKey === '') {
            $saveKey = trim(
                (string) $config['plugin_key']
            );
        }

        $config['save_key'] = $saveKey;

        /*
         * Keep plugin_key synchronized.
         */
        $config['plugin_key'] = $saveKey;

        /*
         * ---------------------------------------------------------
         * ENTITY
         * ---------------------------------------------------------
         */
        $config['entity'] = trim(
            (string) $config['entity']
        );

        if (
            $config['entity'] === ''
            && $config['table'] !== ''
        ) {
            $config['entity'] =
                $this->tableToEntity(
                    $config['table']
                );
        }

        /*
         * ---------------------------------------------------------
         * MODULE
         * ---------------------------------------------------------
         */
        $config['module'] = trim(
            (string) $config['module']
        );

        if (
            $config['module'] === ''
            && $config['entity'] !== ''
        ) {
            $config['module'] =
                $config['entity'];
        }

        /*
         * ---------------------------------------------------------
         * NAMESPACE
         * ---------------------------------------------------------
         */
        $config['namespace'] = trim(
            (string) $config['namespace'],
            '\\'
        );

        if (
            $config['namespace'] === ''
            && $config['module'] !== ''
        ) {
            $config['namespace'] =
                'CMS\\Application\\' .
                $config['module'];
        }

        /*
         * ---------------------------------------------------------
         * OUTPUT PATH
         * ---------------------------------------------------------
         */
        $config['output_path'] = trim(
            (string) $config['output_path'],
            '/'
        );

        if (
            $config['output_path'] === ''
            && $config['module'] !== ''
        ) {
            $config['output_path'] =
                'src/Application/' .
                $config['module'];
        }

        /*
         * ---------------------------------------------------------
         * OWN PAGE
         * ---------------------------------------------------------
         *
         * yes = neue Page erzeugen
         * no  = vorhandene Page verwenden
         */
        $config['own_page'] =
            strtolower(
                trim(
                    (string) $config['own_page']
                )
            );

        /*
         * ---------------------------------------------------------
         * EXISTING PAGE
         * ---------------------------------------------------------
         */
        $config['page'] = trim(
            (string) $config['page']
        );

        /*
         * ---------------------------------------------------------
         * SLUG MODE
         * ---------------------------------------------------------
         *
         * existing = vorhandenen Slug verwenden
         * new      = neuen Slug erzeugen
         */
        $config['slug_mode'] =
            strtolower(
                trim(
                    (string) $config['slug_mode']
                )
            );

        /*
         * ---------------------------------------------------------
         * EXISTING SLUG
         * ---------------------------------------------------------
         */
        $config['slug'] = trim(
            (string) $config['slug']
        );

        /*
         * ---------------------------------------------------------
         * NEW SLUG
         * ---------------------------------------------------------
         */
        $config['new_slug'] = trim(
            (string) $config['new_slug']
        );

        /*
         * ---------------------------------------------------------
         * MULTI TABLE
         * ---------------------------------------------------------
         */
        $config['multi_table'] =
            $this->toBool(
                $config['multi_table']
            );

        /*
         * ---------------------------------------------------------
         * GENERATOR FLAGS
         * ---------------------------------------------------------
         */
        $config['generate_all'] =
            $this->toBool(
                $config['generate_all']
            );

        $config['generate_form'] =
            $this->toBool(
                $config['generate_form']
            );

        $config['generate_handler'] =
            $this->toBool(
                $config['generate_handler']
            );

        $config['generate_repository'] =
            $this->toBool(
                $config['generate_repository']
            );

        $config['generate_service'] =
            $this->toBool(
                $config['generate_service']
            );

        $config['generate_controller'] =
            $this->toBool(
                $config['generate_controller']
            );

        $config['register_plugin'] =
            $this->toBool(
                $config['register_plugin']
            );

        return $config;
    }

    /**
     * Validate the central configuration.
     */
    private function validateConfig(
        array $config
    ): void {
        error_log(
            'GENERATOR MANAGER VALIDATE TABLE: [' .
            ($config['table'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE SAVE KEY: [' .
            ($config['save_key'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE FORM TYPE: [' .
            ($config['form_type'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE OWN PAGE: [' .
            ($config['own_page'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE PAGE: [' .
            ($config['page'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE SLUG MODE: [' .
            ($config['slug_mode'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE SLUG: [' .
            ($config['slug'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATOR MANAGER VALIDATE NEW SLUG: [' .
            ($config['new_slug'] ?? 'NULL') .
            ']'
        );

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */
        if (
            !isset($config['table'])
            || trim(
                (string) $config['table']
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Keine Tabelle übergeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * SAVE KEY
         * ---------------------------------------------------------
         */
        if (
            !isset($config['save_key'])
            || trim(
                (string) $config['save_key']
            ) === ''
        ) {
            throw new InvalidArgumentException(
                'Kein Save Key übergeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * FORM TYPE
         * ---------------------------------------------------------
         */
        if (
            !in_array(
                $config['form_type'],
                ['entry', 'simple'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Ungültiger Formulartyp: ' .
                $config['form_type']
            );
        }

        /*
         * ---------------------------------------------------------
         * OWN PAGE
         * ---------------------------------------------------------
         */
        if (
            !in_array(
                $config['own_page'],
                ['yes', 'no'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Ungültige Auswahl für "Eigene Page anlegen?": ' .
                $config['own_page']
            );
        }

        /*
         * ---------------------------------------------------------
         * EXISTING PAGE
         * ---------------------------------------------------------
         */
        if (
            $config['own_page'] === 'no'
            && $config['page'] === ''
        ) {
            throw new InvalidArgumentException(
                'Bei einer bestehenden Page muss eine Page ausgewählt werden.'
            );
        }

        /*
         * ---------------------------------------------------------
         * NEW PAGE / SLUG
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'yes') {
            /*
             * Slug mode.
             */
            if (
                !in_array(
                    $config['slug_mode'],
                    ['existing', 'new'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Ungültige Slug-Aktion: ' .
                    $config['slug_mode']
                );
            }

            /*
             * Existing slug.
             */
            if (
                $config['slug_mode'] === 'existing'
                && $config['slug'] === ''
            ) {
                throw new InvalidArgumentException(
                    'Ein vorhandener Slug muss ausgewählt werden.'
                );
            }

            /*
             * New slug.
             */
            if (
                $config['slug_mode'] === 'new'
                && $config['new_slug'] === ''
            ) {
                throw new InvalidArgumentException(
                    'Ein neuer Slug muss angegeben werden.'
                );
            }
        }
    }

    /**
     * Convert a table name to a PHP entity name.
     *
     * Examples:
     *
     * address
     * user_address
     * cms_user_address
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
            static fn (
                string $part
            ): bool => $part !== ''
        );

        $parts = array_map(
            static fn (
                string $part
            ): string =>
                ucfirst(
                    strtolower($part)
                ),
            $parts
        );

        return implode(
            '',
            $parts
        );
    }

    /**
     * Normalize common boolean values.
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
                    'on',
                    'ja',
                ],
                true
            );
        }

        return !empty($value);
    }
}
