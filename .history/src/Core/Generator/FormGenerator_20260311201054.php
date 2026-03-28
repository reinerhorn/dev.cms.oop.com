<?php

namespace CMS\Core\Generator;

use mysqli;

class FormGenerator
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function getTables(): array
    {
        $tables = [];

        $result = $this->db->query("SHOW TABLES");

        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        return $tables;
    }

    private function detectFieldType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        if (str_contains($dbType, 'tinyint(1)')) {
            return 'checkbox';
        }

        if (str_contains($dbType, 'int')) {
            return 'number';
        }

        if (str_contains($dbType, 'decimal') || str_contains($dbType, 'float')) {
            return 'number';
        }

        if (str_contains($dbType, 'text')) {
            return 'textarea';
        }

        if (str_contains($dbType, 'date') && !str_contains($dbType, 'datetime')) {
            return 'date';
        }

        if (str_contains($dbType, 'datetime') || str_contains($dbType, 'timestamp')) {
            return 'datetime';
        }

        return 'text';
    }

    public function generateFormConfig(string $table): array
    {
        $fields = [];
        $primaryKey = 'id';

        $result = $this->db->query("SHOW FULL COLUMNS FROM `$table`");

        while ($row = $result->fetch_assoc()) {

            if ($row['Key'] === 'PRI') {
                $primaryKey = $row['Field'];
            }

            $field = [
                'name' => $row['Field'],
                'type' => $this->detectFieldType($row['Type'])
            ];

            if ($row['Null'] === 'NO') {
                $field['required'] = true;
            }

            if ($row['Key'] === 'PRI') {
                $field['readonly'] = true;
            }

            $fields[] = $field;
        }

        return [
            'entity' => [
                'table' => $table,
                'primary_key' => $primaryKey
            ],
            'fields' => $fields
        ];
    }

    public function createEditor(string $table): void
    {
        $config = $this->generateFormConfig($table);

        $json = json_encode($config, JSON_PRETTY_PRINT);

        $formType = $table . '_form';
        $headline = ucfirst($table) . ' Editor';

        $uuid = bin2hex(random_bytes(16));

        $stmt = $this->db->prepare("
            INSERT INTO p_content_formular
            (id, form_type, form_style, headline, config_json)
            VALUES (?, ?, 'default', ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $uuid,
            $formType,
            $headline,
            $json
        );

        $stmt->execute();
    }
}
