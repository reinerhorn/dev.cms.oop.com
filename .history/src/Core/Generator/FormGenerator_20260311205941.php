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

        if (str_contains($dbType, 'json')) {
            return 'json';
        }

        if (str_contains($dbType, 'longtext') || str_contains($dbType, 'mediumtext')) {
            return 'wysiwyg';
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

    private function detectForeignKey(string $table, string $column): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND COLUMN_NAME = ?
             AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return [
                'table' => $row['REFERENCED_TABLE_NAME'],
                'column' => $row['REFERENCED_COLUMN_NAME']
            ];
        }

        return null;
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

            $fieldName = $row['Field'];

            // Smart UI detection based on column name
            $lowerName = strtolower($fieldName);

            $field = [
                'name' => $fieldName,
                'type' => $this->detectFieldType($row['Type'])
            ];

            // Detect real foreign key from database schema
            $fk = $this->detectForeignKey($table, $fieldName);

            if ($fk !== null) {
                $field['type'] = 'select';
                $field['relation'] = $fk['table'];
                $field['relation_key'] = $fk['column'];
            }

            if (str_starts_with($fieldName, 'fk_') && !isset($field['relation'])) {
                $field['type'] = 'select';
                $field['relation'] = str_replace('fk_', '', $fieldName);
            }

            if (str_contains($lowerName, 'email')) {
                $field['type'] = 'email';
            }

            if (str_contains($lowerName, 'password')) {
                $field['type'] = 'password';
            }

            if (str_contains($lowerName, 'image') || str_contains($lowerName, 'img')) {
                $field['type'] = 'image';
            }

            if (str_contains($lowerName, 'url')) {
                $field['type'] = 'url';
            }

            // Toggle switches
            if (
                $lowerName === 'is_active' ||
                $lowerName === 'active' ||
                str_starts_with($lowerName, 'is_') ||
                str_starts_with($lowerName, 'has_')
            ) {
                $field['type'] = 'toggle';
            }

            // Sort order fields
            if ($lowerName === 'sort_order' || $lowerName === 'position' || $lowerName === 'sort') {
                $field['type'] = 'number';
                $field['ui'] = 'sortable';
            }

            // Content editors
            if (
                $lowerName === 'content' ||
                $lowerName === 'body' ||
                $lowerName === 'html'
            ) {
                $field['type'] = 'wysiwyg';
            }

            // JSON editor
            if (str_contains($lowerName, 'json') || str_contains($lowerName, 'config')) {
                $field['type'] = 'json';
            }

            // Slug / URL key
            if ($lowerName === 'slug' || $lowerName === 'url_key') {
                $field['type'] = 'slug';
            }

            if ($fieldName === 'created_at' || $fieldName === 'updated_at') {
                $field['readonly'] = true;
            }

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
