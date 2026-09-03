<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class UpdateGenerator
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

        $primaryKey = 'id';
        $assignments = [];
        $params = [];
        $types = '';

        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'];
            $key = $row['Key'] ?? '';

            if ($key === 'PRI') {
                $primaryKey = $field;
                continue;
            }

            if (in_array($field, ['created_at', 'updated_at'], true)) {
                continue;
            }

            $assignments[] = "{$field} = ?";
            $params[] = "\$data['{$field}']";
            $types .= 's';
        }

        $params[] = '$id';
        $types .= 's';

        $setSql = implode(",\n        ", $assignments);
        $bindSql = implode(",\n    ", $params);

        return <<<PHP
\$stmt = \$this->db->prepare("
    UPDATE {$table}
    SET
        {$setSql}
    WHERE {$primaryKey} = ?
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