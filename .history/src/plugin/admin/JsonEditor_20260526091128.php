<?php

declare(strict_types=1);

namespace CMS\Plugin\Admin;

final class JsonEditor
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

        return [

            'plugin_key' => 'json_editor',

            'headline' => $row['headline'] ?? '',
            'text' => $row['text'] ?? '',

            'editor' => [

                'form_id' => $config['form_id'] ?? null,

                'method' => $config['method'] ?? 'POST',

                'entity' => $config['entity'] ?? [],

                'fields' => $config['fields'] ?? []
            ]
        ];
    }
}