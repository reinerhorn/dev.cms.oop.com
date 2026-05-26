<?php

declare(strict_types=1);

namespace CMS\Plugin\Admin;

final class PluginJsonEditor
{
    public static function loadByUuid(
        \mysqli $db,
        string $uuid,
        string $language
    ): array {

        $stmt = $db->prepare("
            SELECT
                id,
                headline,
                text,
                config_json
            FROM p_content_formular
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $uuid);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$row) {
            return [];
        }

        $config = json_decode(
            $row['config_json'] ?? '{}',
            true
        );

        if (!is_array($config)) {
            $config = [];
        }

        $fields = $config['fields'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | OPTIONS RESOLVER
        |--------------------------------------------------------------------------
        */

        foreach ($fields as &$field) {

            $ui = $field['ui'] ?? [];

            if (
                ($ui['type'] ?? null) === 'select'
                && isset($ui['options_source'])
            ) {

                $source = $ui['options_source'];

                // -------------------------------------------------
                // MYSQL TABLES
                // -------------------------------------------------

                if (($source['type'] ?? null) === 'tables') {

                    $tables = [];

                    $result = $db->query("SHOW TABLES");

                    if ($result) {

                        while ($rowTable = $result->fetch_array()) {

                            $tableName = $rowTable[0];

                            $tables[] = [
                                'value' => $tableName,
                                'label' => $tableName
                            ];
                        }
                    }

                    $field['ui']['options'] = $tables;
                }
            }
        }

        unset($field);

        return [

            'plugin_key' => 'json_editor',

            'headline' => $row['headline'] ?? '',
            'text' => $row['text'] ?? '',

            'editor' => [

                'form_id' => $config['form_id'] ?? null,

                'method' => $config['method'] ?? 'POST',

                'entity' => $config['entity'] ?? [],

                'fields' => $fields
            ]
        ];
    }
}