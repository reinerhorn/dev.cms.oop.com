<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use RuntimeException;

final class PluginRegistrationGenerator
{
    public function __construct(
        private \mysqli $db
    ) {
    }

    /**
     * Creates the plugin definition.
     *
     * The plugin is NOT inserted automatically here.
     *
     * GeneratorManager can call register() after generation.
     */
    public function generate(array $config): array
    {
        error_log(
            '========== PLUGIN REGISTRATION GENERATOR START =========='
        );

        error_log(
            'PLUGIN GENERATOR CONFIG: ' .
            print_r($config, true)
        );

        /*
         * ---------------------------------------------------------
         * TABLE
         * ---------------------------------------------------------
         */
        $table = trim(
            (string) (
                $config['table']
                ?? ''
            )
        );

        if ($table === '') {
            throw new RuntimeException(
                'PluginRegistrationGenerator: Keine Tabelle angegeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * ENTITY / MODULE
         * ---------------------------------------------------------
         */
        $entity = trim(
            (string) (
                $config['entity']
                ?? ''
            )
        );

        if ($entity === '') {
            $entity = $this->tableToEntity(
                $table
            );
        }

        $module = trim(
            (string) (
                $config['module']
                ?? ''
            )
        );

        if ($module === '') {
            $module = $entity;
        }

        /*
         * ---------------------------------------------------------
         * SAVE KEY
         * ---------------------------------------------------------
         *
         * save_key is the canonical plugin/form key.
         */
        $pluginKey = trim(
            (string) (
                $config['save_key']
                ?? $config['plugin_key']
                ?? ''
            )
        );

        if ($pluginKey === '') {
            throw new RuntimeException(
                'PluginRegistrationGenerator: Kein Save Key angegeben.'
            );
        }

        /*
         * ---------------------------------------------------------
         * HANDLER CLASS
         * ---------------------------------------------------------
         *
         * The generated handler is located below:
         *
         * CMS\Application\FormData\Handler\
         *     Address\
         *         AddressHandler
         */
        $handlerClass =
            'CMS\\Application\\FormData\\Handler\\' .
            $module .
            '\\' .
            $module .
            'Handler';

        /*
         * ---------------------------------------------------------
         * ACTION KEY
         * ---------------------------------------------------------
         *
         * The form itself posts:
         *
         *     action=save
         *
         * FormActionDispatcher resolves the logical form/plugin
         * using form_id.
         *
         * action_key therefore identifies the plugin's save action.
         */
        $actionKey = $pluginKey . '_save';

        /*
         * ---------------------------------------------------------
         * PLUGIN UUID
         * ---------------------------------------------------------
         */
        $pluginUuid = $this->generateUuid();

        /*
         * ---------------------------------------------------------
         * PLUGIN NAME
         * ---------------------------------------------------------
         */
        $name = $entity !== ''
            ? $entity
            : $module;

        /*
         * ---------------------------------------------------------
         * PLUGIN DEFINITION
         * ---------------------------------------------------------
         */
        $plugin = [
            'plugin_uuid' => $pluginUuid,

            'plugin_key' => $pluginKey,

            'module' => $module,

            'name' => $name,

            'handler_class' => $handlerClass,

            'table_name' => $table,

            'action_key' => $actionKey,

            'is_active' => 1,
        ];

        error_log(
            'PLUGIN GENERATOR RESULT: ' .
            print_r($plugin, true)
        );

        error_log(
            '========== PLUGIN REGISTRATION GENERATOR COMPLETE =========='
        );

        return $plugin;
    }

    /**
     * Register the generated plugin in the plugin table.
     */
    public function register(
        array $plugin
    ): bool {
        error_log(
            '========== PLUGIN REGISTER START =========='
        );

        error_log(
            'PLUGIN REGISTER DATA: ' .
            print_r($plugin, true)
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
            'action_key',
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
                    'PluginRegistrationGenerator: Feld "' .
                    $field .
                    '" fehlt.'
                );
            }
        }

        /*
         * ---------------------------------------------------------
         * DUPLICATE PLUGIN KEY
         * ---------------------------------------------------------
         *
         * Do not silently create the same plugin twice.
         */
        $existing = $this->findByPluginKey(
            (string) $plugin['plugin_key']
        );

        if ($existing !== null) {
            error_log(
                'PLUGIN REGISTER: EXISTING PLUGIN FOUND: ' .
                print_r($existing, true)
            );

            /*
             * If the exact same plugin already exists, return true.
             *
             * This makes the generator idempotent with regard to
             * the plugin key.
             */
            if (
                (string) (
                    $existing['table_name']
                    ?? ''
                ) === (string) $plugin['table_name']
                &&
                (string) (
                    $existing['handler_class']
                    ?? ''
                ) === (string) $plugin['handler_class']
            ) {
                error_log(
                    'PLUGIN REGISTER: PLUGIN ALREADY REGISTERED.'
                );

                return true;
            }

            throw new RuntimeException(
                'Plugin mit dem Schlüssel "' .
                $plugin['plugin_key'] .
                '" existiert bereits mit einer anderen Konfiguration.'
            );
        }

        /*
         * ---------------------------------------------------------
         * INSERT
         * ---------------------------------------------------------
         */
        $sql = "
            INSERT INTO plugin
            (
                plugin_uuid,
                plugin_key,
                module,
                handler_class,
                table_name,
                action_key,
                is_active
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
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        if (!$stmt) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: Prepare INSERT fehlgeschlagen: ' .
                $this->db->error
            );
        }

        $pluginUuid = (string) $plugin['plugin_uuid'];
        $pluginKey = (string) $plugin['plugin_key'];
        $module = (string) $plugin['module'];
        $handlerClass = (string) $plugin['handler_class'];
        $tableName = (string) $plugin['table_name'];
        $actionKey = (string) $plugin['action_key'];
        $isActive = (int) $plugin['is_active'];

        $stmt->bind_param(
            'ssssssi',
            $pluginUuid,
            $pluginKey,
            $module,
            $handlerClass,
            $tableName,
            $actionKey,
            $isActive
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: INSERT fehlgeschlagen: ' .
                $error
            );
        }

        $stmt->close();

        error_log(
            'PLUGIN REGISTER SUCCESS: ' .
            $pluginUuid
        );

        error_log(
            '========== PLUGIN REGISTER COMPLETE =========='
        );

        return true;
    }

    /**
     * Find an existing plugin by plugin_key.
     */
    private function findByPluginKey(
        string $pluginKey
    ): ?array {
        $sql = "
            SELECT
                plugin_uuid,
                plugin_key,
                module,
                handler_class,
                table_name,
                action_key,
                is_active
            FROM plugin
            WHERE plugin_key = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        if (!$stmt) {
            throw new RuntimeException(
                'PluginRegistrationGenerator: Prepare SELECT fehlgeschlagen: ' .
                $this->db->error
            );
        }

        $stmt->bind_param(
            's',
            $pluginKey
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: SELECT fehlgeschlagen: ' .
                $error
            );
        }

        $result = $stmt->get_result();

        if (!$result) {
            $error = $stmt->error;

            $stmt->close();

            throw new RuntimeException(
                'PluginRegistrationGenerator: Result konnte nicht gelesen werden: ' .
                $error
            );
        }

        $row = $result->fetch_assoc();

        $result->free();
        $stmt->close();

        if ($row === null) {
            return null;
        }

        return $row;
    }

    /**
     * Convert table name to PHP entity name.
     */
    private function tableToEntity(
        string $table
    ): string {
        $table = trim(
            $table
        );

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
     * Generate UUID v4.
     */
    private function generateUuid(): string
    {
        $data = random_bytes(
            16
        );

        /*
         * UUID version 4.
         */
        $data[6] = chr(
            (ord($data[6]) & 0x0f) | 0x40
        );

        /*
         * RFC 4122 variant.
         */
        $data[8] = chr(
            (ord($data[8]) & 0x3f) | 0x80
        );

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(
                substr($data, 0, 4)
            ),
            bin2hex(
                substr($data, 4, 2)
            ),
            bin2hex(
                substr($data, 6, 2)
            ),
            bin2hex(
                substr($data, 8, 2)
            ),
            bin2hex(
                substr($data, 10, 6)
            )
        );
    }
}