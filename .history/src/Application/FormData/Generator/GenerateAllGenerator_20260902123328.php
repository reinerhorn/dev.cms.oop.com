<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;

final class GenerateAllGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Einstiegspunkt für den FormData-Dispatcher.
     *
     * Der Dispatcher übergibt hier die Daten aus dem Formular.
     * Zusätzlich wird direkt auf $_POST zurückgegriffen, damit
     * die Generator-Konfiguration nicht verloren geht, wenn der
     * Dispatcher die POST-Daten nicht vollständig weiterreicht.
     */
    public function handle(array $data = []): array
    {
        /*
         * Verschachtelte Daten des Dispatchers übernehmen.
         *
         * Mögliche Strukturen:
         *
         * [
         *     'form_data' => [
         *         'table' => '...',
         *         ...
         *     ]
         * ]
         *
         * oder:
         *
         * [
         *     'form' => [
         *         ...
         *     ]
         * ]
         */
        foreach (['form_data', 'form', 'data'] as $container) {
            if (
                isset($data[$container])
                && is_array($data[$container])
            ) {
                $data = array_merge(
                    $data,
                    $data[$container]
                );
            }
        }

        /*
         * Formularwerte direkt aus POST übernehmen.
         *
         * Nur Werte übernehmen, die im Dispatcher noch nicht
         * vorhanden sind.
         */
        $postFields = [
            'table',
            'form_type',
            'save_key',
            'plugin_key',
            'multi_table',
            'entity',
            'namespace',
            'output_path',
        ];

        foreach ($postFields as $field) {
            if (
                !isset($data[$field])
                && isset($_POST[$field])
            ) {
                $data[$field] = $_POST[$field];
            }
        }

        /*
         * table kann in manchen Form-Strukturen auch unter
         * db_table übergeben werden.
         */
        if (
            (!isset($data['table']) || trim((string) $data['table']) === '')
            && isset($_POST['db_table'])
        ) {
            $data['table'] = $_POST['db_table'];
        }

        /*
         * Jetzt die eigentliche Generierung starten.
         */
        return $this->generate($data);
    }

    /**
     * Startet die vollständige Generierung.
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        /*
         * Vollständige Generierung aktivieren.
         */
        $config['generate_all'] = true;

        $config['generate_form'] = true;
        $config['generate_handler'] = true;
        $config['generate_repository'] = true;
        $config['generate_service'] = true;
        $config['generate_controller'] = true;
        $config['register_plugin'] = true;

        /*
         * GeneratorManager aus demselben Namespace verwenden.
         */
        $manager = new GeneratorManager(
            $this->db
        );

        $result = $manager->generate(
            $config
        );

        /*
         * Zusätzliche Informationen über den
         * Generierungsvorgang zurückgeben.
         */
        $result['generator'] = [
            'type' => 'generate_all',
            'table' => $config['table'],
            'form_type' => $config['form_type'],
            'save_key' => $config['save_key'],
            'multi_table' => $config['multi_table'],
            'generated_at' => date(
                'Y-m-d H:i:s'
            ),
        ];

        return $result;
    }

    /**
     * Normalisiert die Generator-Konfiguration.
     */
    private function normalizeConfig(
        array $config
    ): array {
        /*
         * Tabelle.
         *
         * Priorität:
         * table
         * db_table
         */
        $config['table'] = trim(
            (string) (
                $config['table']
                ?? $config['db_table']
                ?? ''
            )
        );

        /*
         * Formular-Typ.
         */
        $config['form_type'] = trim(
            (string) (
                $config['form_type']
                ?? 'entry'
            )
        );

        /*
         * Save Key.
         *
         * Falls kein save_key vorhanden ist,
         * plugin_key verwenden.
         */
        $config['save_key'] = trim(
            (string) (
                $config['save_key']
                ?? $config['plugin_key']
                ?? ''
            )
        );

        /*
         * GeneratorManager erwartet ebenfalls
         * plugin_key.
         */
        if ($config['save_key'] !== '') {
            $config['plugin_key'] =
                $config['save_key'];
        }

        /*
         * Entity automatisch aus der Tabelle ableiten,
         * falls keine Entity angegeben wurde.
         */
        $config['entity'] = trim(
            (string) (
                $config['entity']
                ?? ''
            )
        );

        if ($config['entity'] === '' && $config['table'] !== '') {
            $config['entity'] = $this->tableToEntity(
                $config['table']
            );
        }

        /*
         * Modul automatisch aus Entity ableiten.
         */
        $config['module'] = trim(
            (string) (
                $config['module']
                ?? ''
            )
        );

        if ($config['module'] === '' && $config['entity'] !== '') {
            $config['module'] = $config['entity'];
        }

        /*
         * Namespace automatisch erzeugen.
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
                'CMS\\Application\\'
                . $config['module'];
        }

        /*
         * Output Path automatisch erzeugen.
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
                'src/Application/'
                . $config['module'];
        }

        /*
         * Multi-Table Checkbox.
         */
        $config['multi_table'] = !empty(
            $config['multi_table']
        );

        return $config;
    }

    /**
     * Prüft die Generator-Konfiguration.
     */
    private function validateConfig(
        array $config
    ): void {
        /*
         * Ohne Tabelle kann kein Generator
         * gestartet werden.
         */
        if (
            !isset($config['table'])
            || trim((string) $config['table']) === ''
        ) {
            throw new \InvalidArgumentException(
                'Keine Tabelle übergeben.'
            );
        }

        /*
         * Ohne Save Key kann das Ergebnis
         * nicht eindeutig registriert werden.
         */
        if (
            !isset($config['save_key'])
            || trim((string) $config['save_key']) === ''
        ) {
            throw new \InvalidArgumentException(
                'Kein Save Key übergeben.'
            );
        }

        /*
         * Nur bekannte Form-Typen erlauben.
         */
        if (
            !in_array(
                $config['form_type'],
                ['entry', 'simple'],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Ungültiger Formulartyp: '
                . $config['form_type']
            );
        }
    }

    /**
     * Erzeugt aus einem Tabellenname einen
     * brauchbaren Entity-Namen.
     *
     * Beispiele:
     *
     * users       -> Users
     * user_data   -> UserData
     * cms_users   -> CmsUsers
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

        return implode(
            '',
            $parts
        );
    }
}
