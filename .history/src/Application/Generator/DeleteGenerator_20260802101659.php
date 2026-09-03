

<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;
use RuntimeException;

final class DeleteGenerator
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

        while ($row = $result->fetch_assoc()) {
            if (($row['Key'] ?? '') === 'PRI') {
                $primaryKey = $row['Field'];
                break;
            }
        }

        return <<<PHP
\$stmt = \$this->db->prepare("
    DELETE FROM {$table}
    WHERE {$primaryKey} = ?
");

if (!\$stmt) {
    throw new \\RuntimeException(\$this->db->error);
}

\$stmt->bind_param(
    "s",
    \$id
);

if (!\$stmt->execute()) {
    throw new \\RuntimeException(\$stmt->error);
}

return true;
PHP;
    }
}