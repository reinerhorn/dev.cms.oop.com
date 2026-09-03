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
     * Einstiegspunkt für den FormData-Handler-Dispatcher.
     *
     * Der Dispatcher erwartet eine handle()-Methode.
     * Die eigentliche Generierungslogik bleibt in generate().
     */
    public function handle(array $data): array
    {
        return $this->generate($data);
    }

    /**
     * Startet die vollständige Generierung.
     *
     * Erwartete Werte:
     *
     * table
     * form_type
     * save_key
     * multi_table
     */
    public function generate(array $config): array
    {
        $config = $this->normalizeConfig($config);

        $this->validateConfig($config);

        /*
         * Die ausgewählte Datenbanktabelle ist die
         * zentrale Information für die Generatoren.
         */
        $config['generate_all'] = true;

        $config['generate_form'] = true;
        $config['generate_handler'] = true;
        $config['generate_repository'] = true;
        $config['generate_service'] = true;
        $config['generate_controller'] = true;
        $config['register_plugin'] = true;

        /*
         * Zentraler GeneratorManager.
         */
        $manager = new GeneratorManager($this->db);

        $result = $manager->generate($config);

        /*
         * Informationen über den Generierungslauf.
         */
        $result['generator'] = [
            'type' => 'generate_all',
            'table' => $config['table'],
            'form_type' => $config['form_type'],
            'save_key' => $config['save_key'],
            'multi_table' => $config['multi_table'],
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        return $result;
    }

    /**
     * Vereinheitlicht die Werte aus dem Formular.
     */
    private function normalizeConfig(array $config): array
    {
        /*
         * Datenbanktabelle.
         */
        $config['table'] = trim(
            (string) (
                $config['table']
                ?? $config['db_table']
                ?? ''
            )
        );

        /*
         * Formulartyp.
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
         * save_key ist die zentrale Kennung für
         * das zu erzeugende Formular / Plugin.
         */
        $config['save_key'] = trim(
            (string) (
                $config['save_key']
                ?? $config['plugin_key']
                ?? ''
            )
        );

        /*
         * Der GeneratorManager arbeitet ebenfalls
         * mit plugin_key.
         */
        $config['plugin_key'] = $config['save_key'];

        /*
         * Checkbox normalisieren.
         *
         * Dadurch werden z.B.
         * "1", "on", true usw. als true behandelt.
         */
        $config['multi_table'] = !empty(
            $config['multi_table']
        );

        return $config;
    }

    /**
     * Grundlegende Validierung der Generator-Konfiguration.
     */
    private function validateConfig(array $config): void
    {
        /*
         * Ohne Tabelle kann nichts generiert werden.
         */
        if ($config['table'] === '') {
            throw new \InvalidArgumentException(
                'Keine Datenbanktabelle ausgewählt.'
            );
        }

        /*
         * Ohne Save Key kann das generierte Formular /
         * Plugin nicht eindeutig registriert werden.
         */
        if ($config['save_key'] === '') {
            throw new \InvalidArgumentException(
                'Kein Form Key angegeben.'
            );
        }

        /*
         * Nur die vorgesehenen Formulartypen zulassen.
         */
        if (!in_array(
            $config['form_type'],
            ['entry', 'simple'],
            true
        )) {
            throw new \InvalidArgumentException(
                'Ungültiger Formulartyp.'
            );
        }
    }
}
