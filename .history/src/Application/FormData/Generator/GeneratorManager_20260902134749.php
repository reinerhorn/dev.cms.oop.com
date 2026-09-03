<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use InvalidArgumentException;
use mysqli;
use RuntimeException;

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
     *     'table'            => 'address',
     *     'form_type'        => 'entry',
     *     'save_key'         => 'adressen_form',
     *     'plugin_key'       => 'adressen_form',
     *     'entity'           => 'Address',
     *     'module'           => 'Address',
     *     'namespace'        => 'CMS\\Application\\Address',
     *     'output_path'      => 'src/Application/Address',
     *
     *     'generate_all'     => true,
     *     'generate_form'    => true,
     *     'generate_handler' => true,
     *     'generate_repository' => true,
     *     'generate_service' => true,
     *     'generate_controller' => true,
     *     'register_plugin' => true,
     * ]
     */
    public function generate(array $config): array
    {
        error_log('========== GENERATOR MANAGER ENTER ==========');
        error_log(
            'GENERATOR MANAGER INPUT: ' .
            print_r($config, true)
        );

        /*
         * Normalize the configuration once.
         *
         * Every child generator receives this same normalized
         * configuration. No generator should have to guess where
         * the table or entity comes from.
         */
        $config = $this->normalizeConfig($config);

        error_log(
            'GENERATOR MANAGER NORMALIZED CONFIG: ' .
            print_r($config, true)
        );

        /*
         * Validate the central configuration before any generator
         * is executed.
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

            $jsonGenerator = new JsonFormGenerator($this->db);

            $results['form'] = $jsonGenerator->generate(
                $config
            );

            error_log(
                'GENERATOR MANAGER <- JsonFormGenerator: ' .
                print_r($results['form'], true)
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

            $crudGenerator = new CrudHandlerGenerator($this->db);

            $results['handler'] = $crudGenerator->generate(
                $config
            );

            error_log(
                'GENERATOR MANAGER <- CrudHandlerGenerator: ' .
                print_r($results['handler'], true)
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

            $repositoryGenerator = new RepositoryGenerator(
                $this->db
            );

            $results['repository'] =
                $repositoryGenerator->generate($config);

            error_log(
                'GENERATOR MANAGER <- RepositoryGenerator: ' .
                print_r($results['repository'], true)
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

            $serviceGenerator = new ServiceGenerator(
                $this->db
            );

            $results['service'] =
                $serviceGenerator->generate($config);

            error_log(
                'GENERATOR MANAGER <- ServiceGenerator: ' .
                print_r($results['service'], true)
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

            $controllerGenerator = new ControllerGenerator(
                $this->db
            );

            $results['controller'] =
                $controllerGenerator->generate($config);

            error_log(
                'GENERATOR MANAGER <- ControllerGenerator: ' .
                print_r($results['controller'], true)
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
                new PluginRegistrationGenerator($this->db);

            $results['plugin'] =
                $pluginGenerator->generate($config);

            error_log(
                'GENERATOR MANAGER <- PluginRegistrationGenerator: ' .
                print_r($results['plugin'], true)
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
                'module' => $config['module'],
                'namespace' => $config['namespace'],
                'output_path' => $config['output_path'],
                'generated_files' => array_keys($results),
            ];
        }

        $response = [
            'success' => true,
            'generated' => count($results),
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => $config,
            'results' => $results,
        ];

        error_log(
            '========== GENERATOR MANAGER COMPLETE =========='
        );

        error_log(
            'GENERATOR MANAGER RESPONSE: ' .
            print_r($response, true)
        );

        return $response;
    }

    /**
     * Normalize the configuration once for all generators.
     */
    private function normalizeConfig(array $config): array
    {
        /*
         * Defaults.
         */
        $defaults = [
            'entity' => '',
            'table' => '',
            'db_table' => '',
            'namespace' => '',
            'module' => '',
            'plugin_key' => '',
            'save_key' => '',
            'output_path' => '',
            'form_type' => 'entry',

            'generate_all' => false,
            'generate_form' => false,
            'generate_handler' => false,
            'generate_repository' => false,
            'generate_service' => false,
            'generate_controller' => false,
            'register_plugin' => false,

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
         *
         * Primary source:
         *
         *     table
         *
         * Compatibility source:
         *
         *     db_table
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
         *
         * save_key is the canonical value.
         *
         * plugin_key remains supported for compatibility.
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
         * Keep plugin_key synchronized with save_key.
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
            "\\"
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
         * MULTI TABLE
         * ---------------------------------------------------------
         */
        $config['multi_table'] =
            $this->toBool(
                $config['multi_table']
            );

        return $config;
    }

    /**
     * Validate the configuration centrally.
     */
    private function validateConfig(array $config): void
    {
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

        /*
         * Table is mandatory.
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
         * Save key is mandatory.
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
         * Only supported form types.
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
