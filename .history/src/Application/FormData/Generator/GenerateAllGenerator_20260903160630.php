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
     * Einstiegspunkt für den FormActionDispatcher.
     *
     * Erwartete POST-Daten:
     *
     * - table
     * - form_type
     * - save_key
     * - own_page
     * - page
     * - slug_mode
     * - slug
     * - new_slug
     */
    public function handle(
        array $data = [],
        array $pageMeta = []
    ): array {
        error_log('========== GENERATE ALL HANDLE ==========');
        error_log(
            'HANDLE DATA: ' .
            print_r($data, true)
        );
        error_log(
            'HANDLE PAGE META: ' .
            print_r($pageMeta, true)
        );
        error_log(
            'GLOBAL POST: ' .
            print_r($_POST, true)
        );

        /*
         * Formulardaten können je nach Aufrufer
         * verschachtelt übergeben werden.
         */
        $data = $this->flattenFormData($data);

        error_log(
            'HANDLE DATA AFTER FLATTEN: ' .
            print_r($data, true)
        );

        /*
         * POST dient als Fallback, falls ein Feld
         * nicht bereits in $data vorhanden ist.
         */
        $data = $this->mergePostFallback($data);

        error_log(
            'HANDLE DATA AFTER POST FALLBACK: ' .
            print_r($data, true)
        );

        /*
         * Konfiguration vereinheitlichen.
         */
        $config = $this->normalizeConfig($data);

        error_log(
            'GENERATE ALL NORMALIZED CONFIG: ' .
            print_r($config, true)
        );

        /*
         * Eingaben prüfen, bevor irgendeine
         * Generierung gestartet wird.
         */
        $this->validateConfig($config);

        return $this->generate($config);
    }

    /**
     * Führt die komplette Generierung aus.
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        /*
         * Generate-All-Modus aktivieren.
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
            'GENERATOR CONFIG: ' .
            print_r($config, true)
        );

        $manager = new GeneratorManager($this->db);

        if (!method_exists($manager, 'generate')) {
            throw new RuntimeException(
                //'GeneratorManager besitzt keine generate() Methode.'
                 
                 
                 

$results['plugin'] = $plugin;

            );
        }

        $result = $manager->generate($config);

        error_log(
            'GENERATOR MANAGER RESULT: ' .
            print_r($result, true)
        );

        /*
         * Zusatzinformationen für den Dispatcher
         * bzw. die spätere Generator-Integration.
         */
        $result['generator'] = [
            'type' => 'generate_all',
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
            'namespace' => $config['namespace'],
            'output_path' => $config['output_path'],
            'multi_table' => $config['multi_table'],

            'generated_at' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }

    /**
     * Holt verschachtelte Formulardaten nach oben.
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
     * Übernimmt fehlende Werte direkt aus $_POST.
     */
    private function mergePostFallback(array $data): array
    {
        $fields = [
            /*
             * Generator-Grunddaten
             */
            'table',
            'db_table',
            'form_type',
            'save_key',
            'plugin_key',

            /*
             * Page-Konfiguration
             */
            'own_page',
            'page',

            /*
             * Slug-Konfiguration
             */
            'slug_mode',
            'slug',
            'new_slug',

            /*
             * Weitere Generator-Konfiguration
             */
            'multi_table',
            'entity',
            'module',
            'namespace',
            'output_path',

            /*
             * Dispatcher/Form
             */
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
     * Vereinheitlicht die Generator-Konfiguration.
     */
    private function normalizeConfig(array $config): array
    {
        /*
         * ---------------------------------------------------------
         * Tabelle
         * ---------------------------------------------------------
         */
        $table = $config['table'] ?? '';

        if (
            ($table === '' || $table === null)
            && isset($config['db_table'])
        ) {
            $table = $config['db_table'];
        }

        $config['table'] = trim(
            (string) $table
        );

        /*
         * ---------------------------------------------------------
         * Formulartyp
         * ---------------------------------------------------------
         */
        $config['form_type'] = trim(
            (string) (
                $config['form_type']
                ?? 'entry'
            )
        );

        /*
         * ---------------------------------------------------------
         * Save Key
         * ---------------------------------------------------------
         */
        $saveKey = $config['save_key'] ?? '';

        if (
            ($saveKey === '' || $saveKey === null)
            && isset($config['plugin_key'])
        ) {
            $saveKey = $config['plugin_key'];
        }

        $config['save_key'] = trim(
            (string) $saveKey
        );

        /*
         * save_key und plugin_key verwenden aktuell
         * denselben technischen Schlüssel.
         */
        $config['plugin_key'] = $config['save_key'];

        /*
         * ---------------------------------------------------------
         * Entity
         * ---------------------------------------------------------
         */
        $config['entity'] = trim(
            (string) (
                $config['entity']
                ?? ''
            )
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
         * ---------------------------------------------------------
         * Module
         * ---------------------------------------------------------
         */
        $config['module'] = trim(
            (string) (
                $config['module']
                ?? ''
            )
        );

        if (
            $config['module'] === ''
            && $config['entity'] !== ''
        ) {
            $config['module'] = $config['entity'];
        }

        /*
         * ---------------------------------------------------------
         * Namespace
         * ---------------------------------------------------------
         */
        $config['namespace'] = trim(
            (string) (
                $config['namespace']
                ?? ''
            ),
            '\\'
        );

        if ($config['namespace'] === '') {
            $config['namespace'] =
                'CMS\\Application\\' .
                $config['module'];
        }

        /*
         * ---------------------------------------------------------
         * Output Path
         * ---------------------------------------------------------
         */
        $config['output_path'] = trim(
            (string) (
                $config['output_path']
                ?? ''
            ),
            '/'
        );

        if ($config['output_path'] === '') {
            $config['output_path'] =
                'src/Application/' .
                $config['module'];
        }

        /*
         * ---------------------------------------------------------
         * Page-Konfiguration
         * ---------------------------------------------------------
         */

        /*
         * own_page:
         *
         * yes = neue Page anlegen
         * no  = vorhandene Page verwenden
         */
        $config['own_page'] = strtolower(
            trim(
                (string) (
                    $config['own_page']
                    ?? 'no'
                )
            )
        );

        /*
         * Bestehende Page.
         *
         * Kann später eine UUID oder einen
         * anderen technischen Page-Key enthalten.
         */
        $config['page'] = trim(
            (string) (
                $config['page']
                ?? ''
            )
        );

        /*
         * ---------------------------------------------------------
         * Slug-Konfiguration
         * ---------------------------------------------------------
         */

        /*
         * existing = vorhandenen Slug verwenden
         * new      = neuen Slug anlegen
         */
        $config['slug_mode'] = strtolower(
            trim(
                (string) (
                    $config['slug_mode']
                    ?? 'existing'
                )
            )
        );

        /*
         * Bereits vorhandener Slug.
         */
        $config['slug'] = trim(
            (string) (
                $config['slug']
                ?? ''
            )
        );

        /*
         * Neuer Slug.
         */
        $config['new_slug'] = trim(
            (string) (
                $config['new_slug']
                ?? ''
            )
        );

        /*
         * ---------------------------------------------------------
         * Multi Table
         * ---------------------------------------------------------
         */
        $config['multi_table'] = $this->toBool(
            $config['multi_table'] ?? false
        );

        return $config;
    }

    /**
     * Validiert die vollständige Generator-Konfiguration.
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

        error_log(
            'GENERATE ALL VALIDATE OWN PAGE: [' .
            ($config['own_page'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE PAGE: [' .
            ($config['page'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE SLUG MODE: [' .
            ($config['slug_mode'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE SLUG: [' .
            ($config['slug'] ?? 'NULL') .
            ']'
        );

        error_log(
            'GENERATE ALL VALIDATE NEW SLUG: [' .
            ($config['new_slug'] ?? 'NULL') .
            ']'
        );

        /*
         * ---------------------------------------------------------
         * Tabelle
         * ---------------------------------------------------------
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
         * ---------------------------------------------------------
         * Save Key
         * ---------------------------------------------------------
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
         * ---------------------------------------------------------
         * Form Type
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
         * Eigene Page
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
         * Bestehende Page
         * ---------------------------------------------------------
         *
         * Wenn keine neue Page erzeugt werden soll,
         * muss eine vorhandene Page ausgewählt sein.
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
         * Neue Page
         * ---------------------------------------------------------
         */
        if ($config['own_page'] === 'yes') {
            /*
             * Slug-Modus prüfen.
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
             * Vorhandenen Slug verwenden.
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
             * Neuen Slug anlegen.
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
     * Wandelt einen Tabellennamen in einen Entity-Namen um.
     *
     * Beispiel:
     *
     * address
     *     -> Address
     *
     * login_users
     *     -> LoginUsers
     *
     * customer_address
     *     -> CustomerAddress
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
     * Wandelt verschiedene Eingabeformen in bool um.
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
