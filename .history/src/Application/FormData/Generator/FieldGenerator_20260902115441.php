<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class FieldGenerator
{
    public function __construct(
        private mysqli $db
    ) {}

    public function getPrimaryKey(string $table): string
    {
        $sql = "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'";
        $result = $this->db->query($sql);

        if (!$result || $result->num_rows === 0) {
            return 'id';
        }

        $row = $result->fetch_assoc();
        return $row['Column_name'] ?? 'id';
    }

    public function getColumns(string $table): array
    {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");

        if (!$result) {
            throw new RuntimeException($this->db->error);
        }

        $columns = [];
        while ($column = $result->fetch_assoc()) {
            $columns[] = $column['Field'];
        }

        return $columns;
    }

    public function generateAssignments(string $table): string
    {
        $columns = $this->getColumns($table);
        $primaryKey = $this->getPrimaryKey($table);

        $lines = [];

        foreach ($columns as $field) {
            if (in_array($field, [$primaryKey, 'created_at', 'updated_at'], true)) {
                continue;
            }

            $lines[] = "'{$field}' => \$postData['{$table}__{$field}'] ?? '' ,";
        }

        return implode(PHP_EOL . '            ', $lines);
    }
}