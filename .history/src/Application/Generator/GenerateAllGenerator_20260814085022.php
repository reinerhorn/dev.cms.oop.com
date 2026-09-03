<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;
use RuntimeException;

final class GenerateAllGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): array
    {
        /*
         * ---------------------------------------------------------
         * 1. Standardwerte
         * ---------------------------------------------------------
         */

        $config = array_merge([
            'entity'              => '',
            'table'               => '',
            'namespace'           => '',
            'module'              => '',
            'plugin_key'          => '',
            'action'              => '',
            'save_key'            => '',
            'form_type'           => 'entry',
            'use_entry_loader'    => false,
            'output_path'         => '',
            'button_key'          => '',

            'generate_form'       => false,
            'generate_handler'    => false,
            'generate_repository' => false,
            'generate_service'    => false,
            'generate_controller' => false,
            'register_plugin'     => false,
            'generate_all'        => true,
        ], $config);


        /*
         * ---------------------------------------------------------
         * 2. Werte aus dem Generator-Formular
         * ---------------------------------------------------------
         */

        $table = trim(
            (string) $config['table']
        );

        $module = trim(
            (string) $config['module']
        );

        $formType = trim(
            (string) $config['form_type']
        );

        $saveKey = trim(
            (string) $config['save_key']
        );


        /*
         * ---------------------------------------------------------
         * 3. Pflichtfelder prüfen
         * ---------------------------------------------------------
         */

        if ($table === '') {
            throw new RuntimeException(
                'Keine Datenbanktabelle ausgewählt.'
            );
        }

        if ($module === '') {
            throw new RuntimeException(
                'Kein Modul angegeben.'
            );
        }

        if (!in_array(
            $formType,
            ['entry', 'simple'],
            true
        )) {
            throw new RuntimeException(
                "Ungültiger Formulartyp '{$formType}'. "
                . "Erlaubt sind 'entry' und 'simple'."
            );
        }


        /*
         * ---------------------------------------------------------
         * 4. Tabelle prüfen
         * ---------------------------------------------------------
         */

        $this->assertValidTableName($table);

        $this->assertTableExists($table);


        /*
         * ---------------------------------------------------------
         * 5. Save-Key automatisch erzeugen
         * ---------------------------------------------------------
         */

        if ($saveKey === '') {
            $saveKey = $this->toSnakeCase($table);
        }


        /*
         * ---------------------------------------------------------
         * 6. Entity automatisch bestimmen
         *
         * z.B.
         *
         * customer
         *      -> Customer
         *
         * customer_address
         *      -> CustomerAddress
         *
         * shop_products
         *      -> ShopProducts
         * ---------------------------------------------------------
         */

        $entity = trim(
            (string) $config['entity']
        );

        if ($entity === '') {
            $entity = $this->toEntityName(
                $table
            );
        }


        /*
         * ---------------------------------------------------------
         * 7. Namespace automatisch bestimmen
         * ---------------------------------------------------------
         */

        $namespace = trim(
            (string) $config['namespace']
        );

        if ($namespace === '') {
            $namespace =
                'CMS\\Application\\Generated\\'
                . $module;
        }


        /*
         * ---------------------------------------------------------
         * 8. Plugin-Key bestimmen
         * ---------------------------------------------------------
         */

        $pluginKey = trim(
            (string) $config['plugin_key']
        );

        if ($pluginKey === '') {
            $pluginKey = $saveKey;
        }


        /*
         * ---------------------------------------------------------
         * 9. Action bestimmen
         * ---------------------------------------------------------
         */

        $action = trim(
            (string) $config['action']
        );

        if ($action === '') {
            $action = $saveKey;
        }


        /*
         * ---------------------------------------------------------
         * 10. Externe Buttons
         *
         * Der Generator erzeugt KEINE Buttons.
         *
         * Buttons werden weiterhin über das bestehende
         * Button-System aus der Datenbank geladen.
         * ---------------------------------------------------------
         */

        $config['button_key'] = trim(
            (string) $config['button_key']
        );


        /*
         * ---------------------------------------------------------
         * 11. Finale Generator-Konfiguration
         * ---------------------------------------------------------
         */

        $config['entity'] = $entity;

        $config['table'] = $table;

        $config['namespace'] = $namespace;

        $config['module'] = $module;

        $config['plugin_key'] = $pluginKey;

        $config['action'] = $action;

        $config['save_key'] = $saveKey;

        $config['form_type'] = $formType;


        /*
         * ---------------------------------------------------------
         * Entry Loader
         *
         * entry  -> Entry-System verwenden
         * simple -> kein Entry Loader
         * ---------------------------------------------------------
         */

        $config['use_entry_loader'] =
            $formType === 'entry';


        /*
         * ---------------------------------------------------------
         * 12. GenerateAll
         *
         * Alle vorhandenen Generatoren einschalten.
         * ---------------------------------------------------------
         */

        $config['generate_form'] = true;

        $config['generate_handler'] = true;

        $config['generate_repository'] = true;

        $config['generate_service'] = true;

        $config['generate_controller'] = true;

        $config['register_plugin'] = true;

        $config['generate_all'] = true;


        /*
         * ---------------------------------------------------------
         * 13. Bestehenden GeneratorManager verwenden
         *
         * GenerateAll erzeugt die Klassen NICHT selbst.
         *
         * Die eigentliche Generierung bleibt in:
         *
         * GeneratorManager
         *      ↓
         * JsonFormGenerator
         * CrudHandlerGenerator
         * RepositoryGenerator
         * ServiceGenerator
         * ControllerGenerator
         * PluginRegistrationGenerator
         * ---------------------------------------------------------
         */

        $manager = new GeneratorManager(
            $this->db
        );

        $result = $manager->generate(
            $config
        );


        /*
         * ---------------------------------------------------------
         * 14. Generator-Informationen zurückgeben
         * ---------------------------------------------------------
         */

        $result['generator'] = [

            'entity' => $entity,

            'table' => $table,

            'module' => $module,

            'namespace' => $namespace,

            'form_type' => $formType,

            'use_entry_loader' =>
                $config['use_entry_loader'],

            'save_key' => $saveKey,

            'plugin_key' => $pluginKey,

            'action' => $action,

            /*
             * Nur Information.
             *
             * Es wird hier KEIN Button erzeugt.
             */
            'button_key' =>
                $config['button_key'],

            'generated_at' =>
                date('Y-m-d H:i:s'),
        ];


        return $result;
    }


    /*
     * -------------------------------------------------------------
     * Tabellenname absichern
     * -------------------------------------------------------------
     */

    private function assertValidTableName(
        string $table
    ): void {

        if (!preg_match(
            '/^[A-Za-z0-9_]+$/',
            $table
        )) {
            throw new RuntimeException(
                "Ungültiger Tabellenname '{$table}'."
            );
        }
    }


    /*
     * -------------------------------------------------------------
     * Tabelle in der Datenbank prüfen
     * -------------------------------------------------------------
     */

    private function assertTableExists(
        string $table
    ): void {

        $escaped =
            $this->db->real_escape_string(
                $table
            );

        $result = $this->db->query(
            "SHOW TABLES LIKE '{$escaped}'"
        );

        if (
            !$result
            || $result->num_rows === 0
        ) {
            throw new RuntimeException(
                "Die Datenbanktabelle "
                . "'{$table}' wurde nicht gefunden."
            );
        }
    }


    /*
     * -------------------------------------------------------------
     * Tabelle -> Entity
     *
     * customer
     *     -> Customer
     *
     * customer_address
     *     -> CustomerAddress
     * -------------------------------------------------------------
     */

    private function toEntityName(
        string $table
    ): string {

        $name = str_replace(
            ['-', '_'],
            ' ',
            $table
        );

        $name = preg_replace(
            '/\s+/',
            ' ',
            $name
        ) ?? $name;

        $name = ucwords(
            strtolower(
                trim($name)
            )
        );

        return str_replace(
            ' ',
            '',
            $name
        );
    }


    /*
     * -------------------------------------------------------------
     * Name -> snake_case
     *
     * CustomerAddress
     *     -> customer_address
     *
     * customer-address
     *     -> customer_address
     * -------------------------------------------------------------
     */

    private function toSnakeCase(
        string $value
    ): string {

        $value = preg_replace(
            '/([a-z0-9])([A-Z])/',
            '$1_$2',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/[^A-Za-z0-9]+/',
            '_',
            $value
        ) ?? $value;

        return strtolower(
            trim(
                $value,
                '_'
            )
        );
    }
}
