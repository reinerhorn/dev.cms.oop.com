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
         * Die Datenbanktabelle ist die zentrale Information.
         *
         * Die einzelnen Generatoren können daraus
         * Spalten, Primary Key, Datentypen usw. bestimmen.
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
         * Informationen zum Generierungslauf.
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
     * Formularwerte vereinheitlichen.
     */
    private function normalizeConfig(array $config): array
    {
        $config['table'] = trim(
            (string) (
                $config['table']
                ?? $config['db_table']
                ?? ''
            )
        );

        $config['form_type'] = trim(
            (string) (
                $config['form_type']
                ?? 'entry'
            )
        );

        $config['save_key'] = trim(
            (string) (
                $config['save_key']
                ?? $config['plugin_key']
                ?? ''
            )
        );

        /*
         * save_key bleibt die zentrale Kennung
         * für das generierte Formular / Plugin.
         */
        $config['plugin_key'] = $config['save_key'];

        /*
         * Checkbox normalisieren.
         */
        $config['multi_table'] = !empty(
            $config['multi_table']
        );

        return $config;
    }

    /**
     * Grundlegende Validierung.
     */
    private function validateConfig(array $config): void
    {
        if ($config['table'] === '') {
            throw new \InvalidArgumentException(
                'Keine Datenbanktabelle ausgewählt.'
            );
        }

        if ($config['save_key'] === '') {
            throw new \InvalidArgumentException(
                'Kein Form Key angegeben.'
            );
        }

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
