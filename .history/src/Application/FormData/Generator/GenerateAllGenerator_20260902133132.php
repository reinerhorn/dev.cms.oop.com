<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use InvalidArgumentException;
use mysqli;
use RuntimeException;

final class GenerateAllGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Entry point for FormActionDispatcher.
     *
     * The dispatcher passes:
     *   handle($postData, $pageMeta)
     */
    public function handle(
        array $data = [],
        array $pageMeta = []
    ): array {
        error_log('========== GENERATE ALL HANDLE ==========');
        error_log('HANDLE DATA: ' . print_r($data, true));
        error_log('HANDLE PAGE META: ' . print_r($pageMeta, true));
        error_log('GLOBAL POST: ' . print_r($_POST, true));

        /*
         * The dispatcher normally gives us the complete POST array.
         *
         * Nevertheless, support nested form containers as well because
         * different form renderers may submit their values differently.
         */
        $data = $this->flattenFormData($data);

        error_log(
            'HANDLE DATA AFTER FLATTEN: ' . print_r($data, true)
        );

        /*
         * If the dispatcher did not contain the actual form value,
         * fall back to the native POST data.
         */
        $data = $this->mergePostFallback($data);

        error_log(
            'HANDLE DATA AFTER POST FALLBACK: ' . print_r($data, true)
        );

        /*
         * Normalize the generator configuration.
         */
        $config = $this->normalizeConfig($data);

        error_log(
            'GENERATE ALL NORMALIZED CONFIG: ' . print_r($config, true)
        );

        /*
         * Validate before handing the configuration to GeneratorManager.
         */
        $this->validateConfig($config);

        /*
         * Generate everything.
         */
        return $this->generate($config);
    }

    /**
     * Generate all requested components.
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        /*
         * Tell GeneratorManager what the requested operation is.
         */
        $config['generate_all'] = true;
        $config['generate_form'] = true;
        $config['generate_handler'] = true;
        $config['generate_repository'] = true;
        $config['generate_service'] = true;
        $config['generate_controller'] = true;
        $config['register_plugin'] = true;

        error_log(
            '========== GENERATOR MANAGER START =========='
        );

        error_log(
            'GENERATOR CONFIG: ' . print_r($config, true)
        );

        /*
         * GeneratorManager lives in the same Generator namespace.
         */
        $manager = new GeneratorManager($this->db);

        if (!method_exists($manager, 'generate')) {
            throw new RuntimeException(
                'GeneratorManager besitzt keine generate() Methode.'
            );
        }

        $result = $manager->generate($config);

        error_log(
            'GENERATOR MANAGER RESULT: ' . print_r($result, true)
        );

        /*
         * Add information about this generator operation.
         */
        $result['generator'] = [
            'type' => 'generate_all',
            'table' => $config['table'],
            'form_type' => $config['form_type'],
            'save_key' => $config['save_key'],
            'entity' => $config['entity'],
            'module' => $config['module'],
            'namespace' => $config['namespace'],
            'output_path' => $config['output_path'],
            'multi_table' => $config['multi_table'],
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }

    /**
     * Flatten possible form-data containers.
     *
     * Supported structures:
     *
     * [
     *     'table' => 'address'
     * ]
     *
     * or:
     *
     * [
     *     'form_data' => [
     *         'table' => 'address'
     *     ]
     * ]
     *
     * or:
     *
     * [
     *     'form' => [
     *         'table' => 'address'
     *     ]
     * ]
     */
    private function flattenFormData(array $data): array
    {
        $containers = [
            'form_data',
            'form',
            'data',
            'values',
            'fields',
        ];

        foreach ($containers as $container) {
            if (
                isset($data[$container])
                && is_array($data[$container])
            ) {
                $nested = $data[$container];

                /*
                 * Nested values must not overwrite explicit top-level
                 * values that were already supplied.
                 */
                foreach ($nested as $key => $value) {
                    if (
                        !array_key_exists($key, $data)
                        || $data[$key] === ''
                        || $data[$key] === null
                    ) {
                        $data[$key] = $value;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Add values directly from $_POST if they are missing.
     *
     * This is deliberately limited to known generator fields.
     */
    private function mergePostFallback(array $data): array
    {
        $fields = [
            'table',
            'db_table',
            'form_type',
            'save_key',
            'plugin_key',
            'multi_table',
            'entity',
            'module',
            'namespace',
            'output_path',
            'action',
            'form_id',
        ];

        foreach ($fields as $field) {
            if (
                (
                    !array_key_exists($field, $data)
                    || $data[$field] === ''
                    || $data[$field] === null
                )
                && isset($_POST[$field])
            ) {
                $data[$field] = $_POST[$field];
            }
        }

        return $data;
    }

    /**
     * Normalize incoming generator data.
     */
    private function normalizeConfig(array $config): array
    {
        /*
         * Table
         *
         * Accept:
         *   table
         *   db_table
         */
        $table = $config['table'] ?? '';

        if (
            ($table === '' || $table === null)
            && isset($config['db_table'])
        ) {
            $table = $config['db_table'];
        }

        $config['table'] = trim((string) $table);

        /*
         * Form type.
         */
        $config['form_type'] = trim(
            (string) ($config['form_type'] ?? 'entry')
        );

        /*
         * Save key.
         *
         * The generator form contains save_key.
         * plugin_key is accepted as backwards-compatible fallback.
         */
        $saveKey = $config['save_key'] ?? '';

        if (
            ($saveKey === '' || $saveKey === null)
            && isset($config['plugin_key'])
        ) {
            $saveKey = $config['plugin_key'];
        }

        $config['save_key'] = trim((string) $saveKey);

        /*
         * Keep plugin_key synchronized with save_key.
         */
        $config['plugin_key'] = $config['save_key'];

        /*
         * Entity.
         */
        $config['entity'] = trim(
            (string) ($config['entity'] ?? '')
        );

        if (
            $config['entity'] === ''
            && $config['table'] !== ''
        ) {
            $config['entity'] = $this->tableToEntity(
                $config['table']
            );
        }

        /*
         * Module.
         */
        $config['module'] = trim(
            (string) ($config['module'] ?? '')
        );

        if (
            $config['module'] === ''
            && $config['entity'] !== ''
        ) {
            $config['module'] = $config['entity'];
        }

        /*
         * Namespace.
         */
        $config['namespace'] = trim(
            (string) ($config['namespace'] ?? ''),
            '\\'
        );

        if ($config['namespace'] === '') {
            $config['namespace'] =
                'CMS\\Application\\' . $config['module'];
        }

        /*
         * Output path.
         */
        $config['output_path'] = trim(
            (string) ($config['output_path'] ?? ''),
            '/'
        );

        if ($config['output_path'] === '') {
            $config['output_path'] =
                'src/Application/' . $config['module'];
        }

        /*
         * Multi-table.
         *
         * Handle:
         *   true
         *   1
         *   "1"
         *   "on"
         *   "true"
         */
        $config['multi_table'] = $this->toBool(
            $config['multi_table'] ?? false
        );

        return $config;
    }

    /**
     * Validate the generator configuration.
     */
    private function validateConfig(array $config): void
    {
        error_log(
            'GENERATE ALL VALIDATE TABLE: [' .
            ($config['table'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE FORM TYPE: [' .
            ($config['form_type'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE SAVE KEY: [' .
            ($config['save_key'] ?? 'NULL') .
            ']'
        );

        /*
         * Table is mandatory.
         */
        if (
            !isset($config['table'])
            || trim((string) $config['table']) === ''
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
            || trim((string) $config['save_key']) === ''
        ) {
            throw new InvalidArgumentException(
                'Kein Save Key übergeben.'
            );
        }

        /*
         * Only supported generator form types.
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
     * Convert a database table name into an entity name.
     *
     * Examples:
     *
     * address
     *     -> Address
     *
     * user_address
     *     -> UserAddress
     *
     * cms_user_address
     *     -> CmsUserAddress
     */
    private function tableToEntity(string $table): string
    {
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
     * Convert common POST checkbox values to bool.
     */
    private function toBool(mixed $value): bool
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
                    'on',
                    'ja',
                ],
                true
            );
        }

        return !empty($value);
    }
}
