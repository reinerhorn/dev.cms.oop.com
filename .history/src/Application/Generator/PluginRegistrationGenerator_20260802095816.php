<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;

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
            throw new \RuntimeException('Keine Tabelle angegeben.');
        }

        $className = str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $table))
        );

        $handlerClass =
            'CMS\\Application\\Handler\\'
            . $className
            . '\\'
            . $className
            . 'Handler';

        $pluginUuid = bin2hex(random_bytes(16));

        return [
            'plugin_uuid'  => $pluginUuid,
            'plugin_key'   => strtolower($table),
            'module'       => $className,
            'handler_class'=> $handlerClass,
            'table_name'   => $table,
            'action_key'   => strtolower($table) . '_save',
            'is_active'    => 1,
        ];
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
            throw new \RuntimeException($this->db->error);
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
