<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class PluginRegistrationGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Erstellt die Plugin-Konfiguration.
     *
     * Diese Methode registriert noch nichts in der Datenbank.
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public function generate(
        array $config
    ): array {
        error_log(
            '========== PLUGIN REGISTRATION GENERATOR START =========='
        );

        $table = $this->stringValue(
            $config['table']
            ?? $config['db_table']
            ?? ''
        );

        $saveKey = $this->stringValue(
            $config['save_key']
            ?? ''
        );

        $formType = $this->stringValue(
            $config['form_type']
            ?? 'entry'
        );

        /*
         * ---------------------------------------------------------
         * VALIDIERUNG
         * ---------------------------------------------------------
         */

        if ($saveKey === '') {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Kein save_key angegeben.'
            );
        }

        if (!preg_match(
            '/^[a-zA-Z0-9_]+$/',
            $saveKey
        )) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Ungültiger save_key "' .
                $saveKey .
                '".'
            );
        }

        if (
            $formType !== 'entry'
            && $formType !== 'simple'
        ) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Ungültiger form_type "' .
                $formType .
                '".'
            );
        }

        /*
         * ---------------------------------------------------------
         * TABLE NAME
         * ---------------------------------------------------------
         *
         * Bei einem normalen Formular wird die Tabelle registriert.
         *
         * Beim Generator selbst darf table_name leer sein.
         */

        $tableName = $table;

        /*
         * ---------------------------------------------------------
         * MODULE
         * ---------------------------------------------------------
         */

        $module = $this->deriveModuleName(
            $table,
            $saveKey
        );

        /*
         * Handler:
         *
         * CMS\Application\FormData\Handler\<Module>\<Module>Handler
         */

        $handlerClass =
            'CMS\\Application\\FormData\\Handler\\'
            . $module
            . '\\'
            . $module
            . 'Handler';

        /*
         * ---------------------------------------------------------
         * PLUGIN KEY
         * ---------------------------------------------------------
         *
         * save_key ist der kanonische Plugin-Key.
         */

        $pluginKey = $saveKey;

        /*
         * ---------------------------------------------------------
         * UUID
         * ---------------------------------------------------------
         */

        $pluginUuid = $this->uuidV4();

        /*
         * ---------------------------------------------------------
         * HELP TEXT
         * ---------------------------------------------------------
         *
         * Die Tabelle plugin besitzt kein name-Feld.
         * help_text ist die vorhandene Beschreibungsspalte.
         *
         * Der Generator kann optional einen help_text übernehmen.
         * Standardmäßig bleibt er leer.
         */

        $helpText = $this->stringValue(
            $config['help_text']
            ?? ''
        );

        /*
         * ---------------------------------------------------------
         * PLUGIN DEFINITION
         * ---------------------------------------------------------
         */

        $plugin = [
            'plugin_uuid' => $pluginUuid,
            'plugin_key' => $pluginKey,
            'module' => $module,
            'handler_class' => $handlerClass,
            'is_active' => 1,
            'table_name' => $tableName,
            'help_text' => $helpText,
        ];

        error_log(
            'PLUGIN GENERATOR RESULT: '
            . print_r(
                $plugin,
                true
            )
        );

        error_log(
            '========== PLUGIN REGISTRATION GENERATOR COMPLETE =========='
        );

        return $plugin;
    }

    /**
     * Registriert ein Plugin in der Tabelle plugin.
     *
     * Die Methode ist idempotent:
     *
     * - Plugin existiert mit gleicher Konfiguration:
     *   vorhandener Datensatz wird zurückgegeben.
     *
     * - Plugin existiert mit anderer Konfiguration:
     *   Exception.
     *
     * - Plugin existiert nicht:
     *   neuer Datensatz wird angelegt.
     *
     * @param array<string,mixed> $plugin
     *
     * @return array<string,mixed>
     */
    public function register(
        array $plugin
    ): array {
        error_log(
            '========== PLUGIN REGISTER START =========='
        );

        /*
         * ---------------------------------------------------------
         * REQUIRED FIELDS
         * ---------------------------------------------------------
         */

        $required = [
            'plugin_uuid',
            'plugin_key',
            'module',
            'handler_class',
            'table_name',
            'is_active',
        ];

        foreach ($required as $field) {
            if (!array_key_exists(
                $field,
                $plugin
            )) {
                throw new RuntimeException(
                    'PluginRegistrationGenerator: '
                    . 'Feld "' .
                    $field .
                    '" fehlt.'
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * VALUES
         * ---------------------------------------------------------
         */

        $pluginUuid =
            (string) $plugin['plugin_uuid'];

        $pluginKey =
            (string) $plugin['plugin_key'];

        $module =
            (string) $plugin['module'];

        $handlerClass =
            (string) $plugin['handler_class'];

        $tableName =
            (string) (
                $plugin['table_name']
                ?? ''
            );

        $isActive =
            ((int) $plugin['is_active']) === 1
                ? 1
                : 0;

        $helpText =
            (string) (
                $plugin['help_text']
                ?? ''
            );

        /*
         * ---------------------------------------------------------
         * EXISTING PLUGIN
         * ---------------------------------------------------------
         */

        $existing =
            $this->findByPluginKey(
                $pluginKey
            );

        if ($existing !== null) {
            $existingTable =
                (string) (
                    $existing['table_name']
                    ?? ''
                );

            $existingHandler =
                (string) (
                    $existing['handler_class']
                    ?? ''
                );

            /*
             * Gleicher Plugin-Key und gleiche Konfiguration.
             *
             * Nichts erneut anlegen.
             */

            if (
                $existingTable === $tableName
                && $existingHandler === $handlerClass
            ) {
                error_log(
                    'PLUGIN REGISTER: '
                    . 'Plugin bereits vorhanden.'
                );

                return $existing;
            }

            /*
             * Gleicher Key aber andere Konfiguration.
             *
             * Nicht überschreiben.
             */

            throw new RuntimeException(
                'Plugin mit dem Schlüssel "' .
                $pluginKey .
                '" existiert bereits '
                . 'mit einer anderen Konfiguration.'
            );
        }

        /*
         * ---------------------------------------------------------
         * INSERT
         * ---------------------------------------------------------
         *
         * Aktuelles Schema:
         *
         * plugin_uuid
         * ordner_type
         * module
         * handler_class
         * plugin_key
         * is_active
         * created_at
         * table_name
         * help_text
         *
         * Kein name.
         * Kein action_key.
         *
         * ordner_type wird aus dem aktuellen Generator-Kontext
         * übernommen bzw. standardmäßig auf FormData gesetzt.
         */

        $ordnerType =
            $this->stringValue(
                $plugin['ordner_type']
                ?? 'FormData'
            );

        $sql = '
            INSERT INTO plugin
            (
                plugin_uuid,
                ordner_type,
                module,
                handler_class,
                plugin_key,
                is_active,
                table_name,
                help_text
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ';

        $stmt =
            $this->db->prepare(
                $sql
            );

        if (!$stmt) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Prepare INSERT fehlgeschlagen: '
                . $this->db->error
            );
        }

        /*
         * Typen:
         *
         * s = plugin_uuid
         * s = ordner_type
         * s = module
         * s = handler_class
         * s = plugin_key
         * i = is_active
         * s = table_name
         * s = help_text
         */

        $stmt->bind_param(
            'sssssis s',
            $pluginUuid,
            $ordnerType,
            $module,
            $handlerClass,
            $pluginKey,
            $isActive,
            $tableName,
            $helpText
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'INSERT fehlgeschlagen: '
                . $error
            );
        }

        $stmt->close();

        /*
         * ---------------------------------------------------------
         * LOAD INSERTED PLUGIN
         * ---------------------------------------------------------
         *
         * Nicht einfach $plugin zurückgeben.
         *
         * Wir laden den tatsächlichen DB-Datensatz.
         */

        $registeredPlugin =
            $this->findByPluginKey(
                $pluginKey
            );

        if ($registeredPlugin === null) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Plugin wurde erfolgreich eingefügt, '
                . 'konnte danach aber nicht geladen werden.'
            );
        }

        error_log(
            'PLUGIN REGISTER COMPLETE: '
            . print_r(
                $registeredPlugin,
                true
            )
        );

        return $registeredPlugin;
    }

    /**
     * Sucht ein Plugin anhand des plugin_key.
     *
     * @return array<string,mixed>|null
     */
    private function findByPluginKey(
        string $pluginKey
    ): ?array {
        $sql = '
            SELECT
                plugin_uuid,
                ordner_type,
                module,
                handler_class,
                plugin_key,
                is_active,
                table_name,
                help_text
            FROM plugin
            WHERE plugin_key = ?
            LIMIT 1
        ';

        $stmt =
            $this->db->prepare(
                $sql
            );

        if (!$stmt) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Prepare SELECT fehlgeschlagen: '
                . $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pluginKey
        );

        if (!$stmt->execute()) {
            $error =
                $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'SELECT fehlgeschlagen: '
                . $error
            );
        }

        $result =
            $stmt->get_result();

        if (!$result) {
            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'SELECT-Ergebnis konnte nicht gelesen werden.'
            );
        }

        $row =
            $result->fetch_assoc();

        $stmt->close();

        if ($row === null) {
            return null;
        }

        return [
            'plugin_uuid' =>
                (string) (
                    $row['plugin_uuid']
                    ?? ''
                ),

            'ordner_type' =>
                (string) (
                    $row['ordner_type']
                    ?? ''
                ),

            'plugin_key' =>
                (string) (
                    $row['plugin_key']
                    ?? ''
                ),

            'module' =>
                (string) (
                    $row['module']
                    ?? ''
                ),

            'handler_class' =>
                (string) (
                    $row['handler_class']
                    ?? ''
                ),

            'is_active' =>
                (int) (
                    $row['is_active']
                    ?? 0
                ),

            'table_name' =>
                (string) (
                    $row['table_name']
                    ?? ''
                ),

            'help_text' =>
                (string) (
                    $row['help_text']
                    ?? ''
                ),
        ];
    }

    /**
     * Ermittelt den Modulnamen.
     *
     * Beispiele:
     *
     * customer
     * -> Customer
     *
     * p_customer
     * -> Customer
     *
     * customer_address
     * -> CustomerAddress
     *
     * Wenn keine Tabelle vorhanden ist, wird save_key verwendet.
     */
    private function deriveModuleName(
        string $table,
        string $saveKey
    ): string {
        $source =
            $table !== ''
                ? $table
                : $saveKey;

        /*
         * Tabellenprefix p_ entfernen.
         */

        if (str_starts_with(
            $source,
            'p_'
        )) {
            $source =
                substr(
                    $source,
                    2
                );
        }

        $parts =
            preg_split(
                '/[_-]+/',
                $source,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        if (
            $parts === false
            || $parts === []
        ) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Modulname konnte nicht ermittelt werden.'
            );
        }

        $module = '';

        foreach ($parts as $part) {
            $module .=
                ucfirst(
                    strtolower(
                        $part
                    )
                );
        }

        if ($module === '') {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Modulname konnte nicht ermittelt werden.'
            );
        }

        /*
         * Nur gültiger PHP-Klassenname.
         */

        if (!preg_match(
            '/^[A-Z][A-Za-z0-9]*$/',
            $module
        )) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: '
                . 'Ungültiger Modulname "' .
                $module .
                '".'
            );
        }

        return $module;
    }

    /**
     * Normalisiert einen Wert auf String.
     */
    private function stringValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim(
                (string) $value
            );
        }

        return '';
    }

    /**
     * Erzeugt eine UUID v4.
     */
    private function uuidV4(): string
    {
        $data =
            random_bytes(
                16
            );

        $data[6] =
            chr(
                ord(
                    $data[6]
                )
                & 0x0f
                | 0x40
            );

        $data[8] =
            chr(
                ord(
                    $data[8]
                )
                & 0x3f
                | 0x80
            );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(
                substr(
                    $data,
                    0,
                    4
                )
            ),
            bin2hex(
                substr(
                    $data,
                    4,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    6,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    8,
                    2
                )
            ),
            bin2hex(
                substr(
                    $data,
                    10,
                    6
                )
            )
        );
    }
}