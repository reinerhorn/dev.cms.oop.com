<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;

final class GenerateAllGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Generiert aus einer Generator-Konfiguration
     * die komplette CMS-Struktur.
     *
     * Erwartete POST-/Config-Werte:
     *
     * table
     * form_type
     * save_key
     * module
     * entity
     * namespace
     * output_path
     *
     * Optional:
     * multi_table
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        /*
         * Der GeneratorManager ist die zentrale Stelle,
         * welche die einzelnen Generatoren aufruft.
         *
         * GenerateAllGenerator entscheidet NICHT selbst,
         * wie Repository, Service, Controller usw. erzeugt werden.
         */
        $manager = new GeneratorManager($this->db);

        /*
         * Alles aktivieren.
         */
        $config['generate_all'] = true;

        $config['generate_form'] = true;
        $config['generate_handler'] = true;
        $config['generate_repository'] = true;
        $config['generate_service'] = true;
        $config['generate_controller'] = true;
        $config['register_plugin'] = true;

        /*
         * Alte Generator-Architektur bekommt damit
         * die neue Konfiguration.
         */
        $result = $manager->generate($config);

        /*
         * Zusätzliche Informationen über den kompletten
         * Generierungslauf.
         */
        $result['generator'] = [
            'type' => 'generate_all',

            'entity' => $config['entity'],
            'table' => $config['table'],
            'module' => $config['module'],
            'namespace' => $config['namespace'],

            'form_type' => $config['form_type'],
            'save_key' => $config['save_key'],

            'multi_table' => $config['multi_table'],

            'output_path' => $config['output_path'],

            'generated_at' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }

    /**
     * Bringt die Formularwerte in eine einheitliche
     * Generator-Konfiguration.
     */
    private function normalizeConfig(array $config): array
    {
        $table = trim((string) (
            $config['table']
            ?? $config['db_table']
            ?? ''
        ));

        $module = trim((string) (
            $config['module']
            ?? ''
        ));

        $entity = trim((string) (
            $config['entity']
            ?? ''
        ));

        $saveKey = trim((string) (
            $config['save_key']
            ?? $config['plugin_key']
            ?? ''
        ));

        $formType = trim((string) (
            $config['form_type']
            ?? 'entry'
        ));

        $namespace = trim((string) (
            $config['namespace']
            ?? ''
        ));

        $outputPath = trim((string) (
            $config['output_path']
            ?? ''
        ));

        /*
         * Wenn keine Entity angegeben wurde,
         * wird sie aus dem Tabellennamen erzeugt.
         *
         * customer_address
         * =>
         * CustomerAddress
         */
        if ($entity === '' && $table !== '') {
            $entity = $this->tableToEntity($table);
        }

        /*
         * Wenn kein Module angegeben wurde,
         * wird ebenfalls die Entity verwendet.
         */
        if ($module === '' && $entity !== '') {
            $module = $entity;
        }

        /*
         * Form-Key kann gleichzeitig als plugin_key
         * verwendet werden.
         */
        if ($saveKey !== '') {
            $config['plugin_key'] = $saveKey;
        }

        /*
         * Standard-Namespace.
         */
        if ($namespace === '') {
            $namespace = 'CMS\\Application\\' . $module;
        }

        /*
         * Standard Output-Pfad.
         */
        if ($outputPath === '') {
            $outputPath = 'src/Application/' . $module;
        }

        $config['table'] = $table;
        $config['entity'] = $entity;
        $config['module'] = $module;
        $config['save_key'] = $saveKey;
        $config['form_type'] = $formType;
        $config['namespace'] = $namespace;
        $config['output_path'] = $outputPath;

        /*
         * Checkbox aus dem Generatorformular.
         */
        $config['multi_table'] = !empty(
            $config['multi_table']
        );

        return $config;
    }

    /**
     * Prüft die minimal notwendigen Angaben.
     */
    private function validateConfig(array $config): void
    {
        if ($config['table'] === '') {
            throw new \InvalidArgumentException(
                'Keine Datenbanktabelle angegeben.'
            );
        }

        if ($config['save_key'] === '') {
            throw new \InvalidArgumentException(
                'Kein Form Key angegeben.'
            );
        }

        if (
            $config['form_type'] !== 'entry'
            && $config['form_type'] !== 'simple'
        ) {
            throw new \InvalidArgumentException(
                'Ungültiger Formulartyp.'
            );
        }
    }

    /**
     * Erzeugt aus einem Tabellennamen eine Entity.
     *
     * customer
     * =>
     * Customer
     *
     * customer_address
     * =>
     * CustomerAddress
     *
     * cms_customer_address
     * =>
     * CmsCustomerAddress
     */
    private function tableToEntity(string $table): string
    {
        $parts = preg_split(
            '/[^a-zA-Z0-9]+/',
            trim($table, '_')
        );

        $parts = array_filter(
            $parts,
            static fn ($part): bool => $part !== ''
        );

        $entity = '';

        foreach ($parts as $part) {
            $entity .= ucfirst(
                strtolower($part)
            );
        }

        return $entity;
    }
}
