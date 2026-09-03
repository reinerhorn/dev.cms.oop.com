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

        /*
         * Tabellenstruktur auslesen.
         */
        $result = $this->db->query(
            "SHOW COLUMNS FROM `{$table}`"
        );

        if (!$result) {
            throw new RuntimeException(
                'Spalten von Tabelle "' .
                $table .
                '" konnten nicht gelesen werden: ' .
                $this->db->error
            );
        }

        $primaryKey = 'id';

        $assignments = [];
        $variables = [];
        $parameters = [];
        $types = '';

        /*
         * Felder analysieren.
         */
        while ($row = $result->fetch_assoc()) {
            $field = trim(
                (string) (
                    $row['Field'] ?? ''
                )
            );

            if ($field === '') {
                continue;
            }

            /*
             * Primary Key nicht aktualisieren.
             */
            if (($row['Key'] ?? '') === 'PRI') {
                $primaryKey = $field;
                continue;
            }

            /*
             * Zeitstempel nicht manuell aktualisieren,
             * sofern diese vom DB-Schema verwaltet werden.
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

            /*
             * Gültigen PHP-Variablennamen erzeugen.
             */
            $variableName =
                $this->fieldToVariable($field);

            $variable =
                '$' . $variableName;

            /*
             * SQL SET-Klausel.
             */
            $assignments[] =
                "{$field} = ?";

            /*
             * Lokale Variable erzeugen.
             *
             * Beispiel:
             *
             * $user_id = $data['user_id'] ?? '';
             */
            $variables[] =
                $variable .
                " = \$data['{$field}'] ?? '';";

            /*
             * Variable für bind_param().
             */
            $parameters[] =
                $variable;

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
         * ID als letztes bind_param()-Argument.
         */
        $parameters[] = '$id';
        $types .= 's';

        /*
         * SQL SET erzeugen.
         */
        $setSql = implode(
            ",\n        ",
            $assignments
        );

        /*
         * Lokale Variablen erzeugen.
         */
        $variableSql = implode(
            PHP_EOL,
            $variables
        );

        /*
         * bind_param()-Parameter erzeugen.
         */
        $parameterSql = implode(
            ",\n    ",
            $parameters
        );

        /*
         * Fertigen PHP-Code für die generierte
         * Repository-/Handler-Methode zurückgeben.
         */
        return <<<PHP
{$variableSql}

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
    {$parameterSql}
);

if (!\$stmt->execute()) {
    throw new \\RuntimeException(
        \$stmt->error
    );
}

return true;
PHP;
    }

    /**
     * Erzeugt aus einem Datenbankfeld einen gültigen
     * PHP-Variablennamen.
     *
     * Beispiele:
     *
     * user_id      -> user_id
     * postal_code  -> postal_code
     * first-name   -> first_name
     * 123field     -> field_123field
     */
    private function fieldToVariable(
        string $field
    ): string {
        $variable =
            preg_replace(
                '/[^a-zA-Z0-9_]/',
                '_',
                $field
            );

        $variable =
            trim(
                (string) $variable,
                '_'
            );

        if ($variable === '') {
            throw new RuntimeException(
                'Ungültiger Feldname für PHP-Variable: ' .
                $field
            );
        }

        /*
         * PHP-Variablennamen dürfen nicht mit
         * einer Zahl beginnen.
         */
        if (
            isset($variable[0])
            && ctype_digit($variable[0])
        ) {
            $variable =
                'field_' .
                $variable;
        }

        return $variable;
    }
}
