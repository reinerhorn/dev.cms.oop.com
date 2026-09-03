<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;
use RuntimeException;

final class PluginRegistrationGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): array
    {
        $table = $config['tables'][0] ?? '';

        if ($table === '') {
            throw new RuntimeException('Keine Tabelle angegeben.');
        }

        $className = str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $table))
        );

        $renderer = new TemplateRenderer();

        $content = $renderer->render(
            'Plugin.stup',
            [
                'plugin_uuid' => bin2hex(random_bytes(16)),
                'plugin_key' => strtolower($table),
                'module' => $className,
                'name' => $className,
                'handler_class' => 'CMS\\Application\\Handler\\' . $className . '\\' . $className . 'Handler',
                'table_name' => $table,
                'action_key' => strtolower($table) . '_save',
                'is_active' => 1,
            ]
        );

        $plugin = eval('?>' . $content);

        if (!is_array($plugin)) {
            throw new RuntimeException('Plugin-Template konnte nicht erzeugt werden.');
        }

        return $plugin;
    }

    public function register(array $plugin): bool
    {
        $stmt = $this->db->prepare(
            "
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
                ?,?,?,?,?,?,?
            )
            "
        );

        if (!$stmt) {
            throw new RuntimeException($this->db->error);
        }

        $stmt->bind_param(
            "ssssssi",
            $plugin['plugin_uuid'],
            $plugin['plugin_key'],
            $plugin['module'],
            $plugin['handler_class'],
            $plugin['table_name'],
            $plugin['action_key'],
            $plugin['is_active']
        );

        return $stmt->execute();
    }
}
