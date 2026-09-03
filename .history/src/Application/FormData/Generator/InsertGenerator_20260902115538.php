<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class InsertGenerator
{
    public function __construct(
        private mysqli $db
    ) {}

    public function generate(string $table): string
    {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}`");

        if (!$result) {
            throw new RuntimeException($this->db->error);
        }

        $columns = [];
        $placeholders = [];
        $params = [];
        $types = '';

        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'];

            if (in_array($field, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $columns[] = $field;
            $placeholders[] = '?';
            $params[] = "\$data['{$field}']";
            $types .= 's';
        }

        $columnSql = implode(",\n                ", $columns);
        $placeholderSql = implode(', ', $placeholders);
        $bindSql = implode(",\n            ", $params);

        return <<<PHP
\$stmt = \$this->db->prepare("
    INSERT INTO {$table} (
                {$columnSql}
    )
    VALUES ({$placeholderSql})
");

\$stmt->bind_param(
    "{$types}",
    {$bindSql}
);

if (!\$stmt->execute()) {
    throw new \\RuntimeException(\$stmt->error);
}
PHP;
    }
}