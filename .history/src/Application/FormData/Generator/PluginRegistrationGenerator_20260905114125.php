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
     * Erstellt die Plugin-Definition.
     *
     * Diese Methode schreibt noch nichts in die Datenbank.
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

        $table =
            $this->stringValue(
                $config['table']
                ?? $config['db_table']
                ?? ''
            );

        $saveKey =
            $this->stringValue(
                $config['save_key']
                ?? ''
            );

        $formType =
            $this->stringValue(
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
         * TABLE
         * ---------------------------------------------------------
         *
         * Für normale Tabellen-basierte Generatoren wird die Tabelle
         * als table_name registriert.
         *
         * Der Generator selbst darf aber auch ohne physische
         * Content-Tabelle registriert werden. Deshalb ist eine leere
         * table_name grundsätzlich erlaubt.
         */

        $tableName =
            $table;

        /*
         * ---------------------------------------------------------
         * MODULE
         * ---------------------------------------------------------
         *
         * Der Handler liegt unter:
         *
         * CMS\Application\FormData\Handler\<Module>\<Module>Handler
         *
         * Beispiel:
         *
         * address
         * ->
         * Address
         * ->
         * CMS\Application\FormData\Handler\Address\AddressHandler
         */

        $module =
            $this->deriveModuleName(
                $table,
                $saveKey
            );

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
         * Der save_key ist der kanonische Plugin-Key.
         */

        $pluginKey =
            $saveKey;

        /*
         * ---------------------------------------------------------
         * UUID
         * ---------------------------------------------------------
         */

        $pluginUuid =
            $this->uuidV4();

        /*
         * ---------------------------------------------------------
         * RESULT
         * ---------------------------------------------------------
         */

        $plugin = [
            'plugin_uuid' =>
                $pluginUuid,

            'plugin_key' =>
                $pluginKey,

            'module' =>
                $module,

            'handler_class' =>
                $handlerClass,

            'table_name' =>
                $tableName,

            'is_active' =>
                1,
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
     * Registriert das Plugin in der plugin-Tabelle.
     *
     * Gibt immer den tatsächlichen Plugin-Datensatz zurück.
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

        error_log(
            'PLUGIN REGISTER DATA: '
            . print_r(
                $plugin,
                true
            )
        );

        /*
         * ---------------------------------------------------------
         * REQUIRED VALUES
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
            if (
                !array_key_exists(
                    $field,
                    $plugin
                )
            ) {
                throw new RuntimeException(
                    'PluginRegistrationGenerator: '
                    . 'Feld "' .
                    $field .
                    '" fehlt.'
                );
            }
        }

        $pluginUuid =
            (string)
            $plugin['plugin_uuid'];

        $pluginKey =
            (string)
            $plugin['plugin_key'];

        $module =
            (string)
            $plugin['module'];

        $handlerClass =
            (string)
            $plugin['handler_class'];

        $tableName =
            (string)
            ($plugin['table_name'] ?? '');

        $isActive =
            ((int)
            $plugin['is_active']) === 1
                ? 1
                : 0;

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
            error_log(
                'PLUGIN REGISTER: EXISTING PLUGIN FOUND: '
                . print_r(
                    $existing,
                    true
                )
            );

            /*
             * Gleicher Plugin-Key + gleiche Konfiguration:
             *
             * Plugin existiert bereits.
             * Wir geben den vorhandenen Datensatz zurück.
             */

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

            if (
                $existingTable === $tableName
                && $existingHandler === $handlerClass
            ) {
                error_log(
                    'PLUGIN REGISTER: '
                    . 'PLUGIN ALREADY REGISTERED.'
                );

                return $existing;
            }

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
         */

        $sql = '
            INSERT INTO plugin
            (
                plugin_uuid,
                plugin_key,
                module,
                handler_class,
                name,
                is_active,
                table_name
            )
            VALUES
            (
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
         * name:
         *
         * Der Plugin-Key ist hier die neutrale Bezeichnung.
         * Ein separates Name-System können wir später ergänzen.
         */

        $name =
            $pluginKey;

        $stmt->bind_param(
            'sssss is',
            $pluginUuid,
            $pluginKey,
            $module,
            $handlerClass,
            $name,
            $isActive,
            $tableName
        );
    }

    /**
     * Sucht ein Plugin über seinen plugin_key.
     *
     * @return array<string,mixed>|null
     */
    private function findByPluginKey(
        string $pluginKey
    ): ?array {
        $sql = '
            SELECT
                plugin_uuid,
                plugin_key,
                module,
                handler_class,
                name,
                is_active,
                table_name
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
                . 'Prepare Plugin-Abfrage fehlgeschlagen: '
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
                . 'Plugin-Abfrage fehlgeschlagen: '
                . $error
            );
        }

        $result =
            $stmt->get_result();

        if (
            $result === false
            || $result->num_rows === 0
        ) {
            $stmt->close();

            return null;
        }

        $row =
            $result->fetch_assoc();

        $stmt->close();

        if (!is_array($row)) {
            return null;
        }

        return [
            'plugin_uuid' =>
                (string)
                ($row['plugin_uuid'] ?? ''),

            'plugin_key' =>
                (string)
                ($row['plugin_key'] ?? ''),

            'module' =>
                (string)
                ($row['module'] ?? ''),

            'handler_class' =>
                (string)
                ($row['handler_class'] ?? ''),

            'name' =>
                (string)
                ($row['name'] ?? ''),

            'is_active' =>
                (int)
                ($row['is_active'] ?? 0),

            'table_name' =>
                (string)
                ($row['table_name'] ?? ''),
        ];
    }

    /**
     * Ermittelt den Modulnamen.
     */
    private function deriveModuleName(
        string $table,
        string $saveKey
    ): string {
        /*
         * Wenn eine Tabelle vorhanden ist:
         *
         * p_customer
         * ->
         * Customer
         *
         * customer
         * ->
         * Customer
         */

        $source =
            $table !== ''
                ? $table
                : $saveKey;

        $source =
            preg_replace(
                '/^p_/',
                '',
                $source
            ) ?? $source;

        $parts =
            preg_split(
                '/[_\-]+/',
                $source
            );

        if (!is_array($parts)) {
            $parts = [
                $source,
            ];
        }

        $module = '';

        foreach ($parts as $part) {
            $part =
                trim(
                    (string) $part
                );

            if ($part === '') {
                continue;
            }

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

        return $module;
    }

    /**
     * String normalisieren.
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
     * UUID v4 erzeugen.
     */
    private function uuidV4(): string
    {
        $data =
            random_bytes(
                16
            );

        /*
         * Version 4.
         */
        $data[6] =
            chr(
                ord(
                    $data[6]
                )
                & 0x0f
                | 0x40
            );

        /*
         * RFC 4122 Variant.
         */
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