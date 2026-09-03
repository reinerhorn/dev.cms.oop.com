<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class UpdateGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }


    public function generate(string $table): string
    {
        $table = trim($table);

        if ($table === '') {
            throw new RuntimeException(
                'Keine Tabelle für UpdateGenerator angegeben.'
            );
        }


        $result = $this->db->query(
            "SHOW COLUMNS FROM `{$table}`"
        );

        if (!$result) {
            throw new RuntimeException(
                $this->db->error
            );
        }


        $primaryKey = 'id';

        $assignments = [];

        $params = [];

        $types = '';


        while ($row = $result->fetch_assoc()) {
            $field = $row['Field'] ?? '';
            $key = $row['Key'] ?? '';


            if ($field === '') {
                continue;
            }


            /*
             * Primary Key nicht aktualisieren.
             */
            if ($key === 'PRI') {
                $primaryKey = $field;
                continue;
            }


            /*
             * Zeitstempel werden nicht automatisch
             * über diesen Generator aktualisiert.
             */
            if (
                in_array(
                    $field,
                    [
                        'created_at',
                        'updated_at',
                    ],
                    true
                )
            ) {
                continue;
            }


            $assignments[] =
                "{$field} = ?";

            $params[] =
                "\$data['{$field}']";

            $types .= 's';
        }


        if ($assignments === []) {
            throw new RuntimeException(
                'Keine aktualisierbaren Felder für Tabelle "' .
                $table .
                '" gefunden.'
            );
        }


        /*
         * ID für WHERE-Bedingung
         */
        $params[] = '$id';

        $types .= 's';


        $setSql = implode(
            ",\n        ",
            $assignments
        );


        $bindSql = implode(
            ",\n    ",
            $params
        );


        return <<<PHP
\$stmt = \$this->db->prepare("
    UPDATE {$table}
    SET
        {$setSql}
    WHERE {$primaryKey} = ?
");

if (!\$stmt) {
    throw new \\RuntimeException(
        \$this->db->error
    );
}

\$stmt->bind_param(
    "{$types}",
    {$bindSql}
);

if (!\$stmt->execute()) {
    throw new \\RuntimeException(
        \$stmt->error
    );
}

return true;
PHP;
    }
}

