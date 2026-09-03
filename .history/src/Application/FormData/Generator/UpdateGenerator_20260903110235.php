<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class InsertGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Erzeugt den PHP-Code für einen INSERT.
     *
     * Der generierte Code ist für den CrudHandler gedacht.
     *
     * Der Primary Key wird dabei nicht aus $data gelesen,
     * sondern als UUID über die bereits vorhandene $id-Variable
     * verwendet.
     *
     * Beispiel:
     *
     * $id = $this->uuid();
     *
     * $user_id = $data['user_id'] ?? '';
     * ...
     *
     * $stmt->bind_param(
     *     "sssssssss",
     *     $id,
     *     $user_id,
     *     ...
     * );
     */
    public function generate(string $table): string
    {
        $table = trim($table);

        if ($table === '') {
            throw new RuntimeException(
                'Keine Tabelle für InsertGenerator angegeben.'
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

        $columns = [];
        $placeholders = [];
        $variables = [];
        $parameters = [];
        $types = '';

        /*
         * Zuerst Primary Key ermitteln.
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

            if (($row['Key'] ?? '') === 'PRI') {
                $primaryKey = $field;
                break;
            }
        }

        /*
         * Result-Set erneut ausführen, weil der Cursor
         * nach der Primary-Key-Suche am Ende steht.
         */
        $result = $this->db->query(
            "SHOW COLUMNS FROM `{$table}`"
        );

        if (!$result) {
            throw new RuntimeException(
                'Spalten von Tabelle "' .
                $table .
                '" konnten nicht erneut gelesen werden: ' .
                $this->db->error
            );
        }

        /*
         * Alle INSERT-Felder erzeugen.
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
             * Primary Key:
             *
             * Der CrudHandler erzeugt vorher:
             *
             * $id = $this->uuid();
             *
             * Diese Variable wird direkt verwendet.
             */
            if ($field === $primaryKey) {
                $columns[] = $field;
                $placeholders[] = '?';
                $parameters[] = '$id';
                $types .= 's';

                continue;
            }

            /*
             * Zeitstempel mit DB-Default nicht explizit setzen.
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
             * Sicheren PHP-Variablennamen erzeugen.
             */
            $variableName =
                $this->fieldToVariable($field);

            $variable =
                '$' .
                $variableName;

            /*
             * SQL-Spalte.
             */
            $columns[] = $field;

            /*
             * Platzhalter.
             */
            $placeholders[] = '?';

            /*
             * Variable für bind_param().
             */
            $parameters[] = $variable;

            /*
             * Alle aktuellen Generator-Felder
             * werden als String behandelt.
             */
            $types .= 's';

            /*
             * Lokale Variable erzeugen.
             *
             * Beispiel:
             *
             * $name = $data['name'] ?? '';
             */
            $variables[] =
                $variable .
                " = \$data['{$field}'] ?? '';";
        }

        if ($columns === []) {
            throw new RuntimeException(
                'Keine INSERT-Felder für Tabelle "' .
                $table .
                '" gefunden.'
            );
        }

        /*
         * SQL-Spaltenliste.
         */
        $columnSql = implode(
            ",\n        ",
            $columns
        );

        /*
         * SQL-Platzhalter.
         */
        $placeholderSql = implode(
            ', ',
            $placeholders
        );

        /*
         * Lokale Variablen.
         */
        $variableSql = implode(
            PHP_EOL,
            $variables
        );

        /*
         * bind_param()-Parameter.
         */
        $parameterSql = implode(
            ",\n    ",
            $parameters
        );

        /*
         * Fertigen PHP-Code zurückgeben.
         *
         * Wichtig:
         *
         * $id wird hier absichtlich NICHT erzeugt.
         *
         * Der CrudHandler erzeugt die ID bereits mit:
         *
         * $id = $this->uuid();
         *
         * Dadurch bleibt die Verantwortung für die UUID
         * beim Handler.
         */
        return <<<PHP
{$variableSql}

\$stmt = \$this->db->prepare("
    INSERT INTO {$table} (
        {$columnSql}
    )
    VALUES ({$placeholderSql})
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
PHP;
    }

    /**
     * Erzeugt einen gültigen PHP-Variablennamen
     * aus einem Datenbankfeld.
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