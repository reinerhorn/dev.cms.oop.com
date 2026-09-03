<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class RepositoryGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): string
    {
        $table = trim(
            (string) ($config['table'] ?? '')
        );

        if ($table === '') {
            throw new RuntimeException(
                'Keine Tabelle für RepositoryGenerator angegeben.'
            );
        }

        $entity = trim(
            (string) ($config['entity'] ?? '')
        );

        if ($entity === '') {
            $entity = $this->tableToEntity($table);
        }

        if ($entity === '') {
            throw new RuntimeException(
                'Keine Entity für RepositoryGenerator ermittelbar.'
            );
        }

        /*
         * Repository Namespace
         *
         * Beispiel:
         *
         * CMS\Application\FormData\Repository\Address
         */
        $namespace =
            'CMS\\Application\\FormData\\Repository\\' .
            $entity;

        $className =
            $entity . 'Repository';

        /*
         * Generator:
         *
         * __DIR__ =
         * src/Application/FormData/Generator
         *
         * dirname(__DIR__) =
         * src/Application/FormData
         */
        $basePath = dirname(__DIR__);

        $repositoryDirectory =
            $basePath .
            '/Repository/' .
            $entity;

        /*
         * Repository-Verzeichnis erstellen.
         */
        if (!is_dir($repositoryDirectory)) {
            if (
                !mkdir(
                    $repositoryDirectory,
                    0775,
                    true
                )
                && !is_dir($repositoryDirectory)
            ) {
                throw new RuntimeException(
                    'Repository-Verzeichnis konnte nicht erstellt werden: ' .
                    $repositoryDirectory
                );
            }
        }

        if (!is_writable($repositoryDirectory)) {
            throw new RuntimeException(
                'Repository-Verzeichnis ist nicht beschreibbar: ' .
                $repositoryDirectory
            );
        }

        /*
         * Primary Key ermitteln.
         */
        $fieldGenerator =
            new FieldGenerator($this->db);

        $primaryKey =
            $fieldGenerator->getPrimaryKey($table);

        if ($primaryKey === '') {
            throw new RuntimeException(
                'Primary Key konnte für Tabelle "' .
                $table .
                '" nicht ermittelt werden.'
            );
        }

        /*
         * find()
         */
        $findCode =
            $this->generateFindCode(
                $table,
                $primaryKey
            );

        /*
         * findAll()
         */
        $findAllCode =
            $this->generateFindAllCode(
                $table
            );

        /*
         * insert()
         *
         * Eigener Generator, weil das Repository
         * seine UUID selbst erzeugen und zurückgeben
         * muss.
         */
        $insertCode =
            $this->generateInsertCode(
                $table,
                $primaryKey
            );

        /*
         * update()
         */
        $updateGenerator =
            new UpdateGenerator($this->db);

        $updateCode =
            $updateGenerator->generate($table);

        /*
         * delete()
         */
        $deleteGenerator =
            new DeleteGenerator($this->db);

        $deleteCode =
            $deleteGenerator->generate($table);

        /*
         * Repository Template rendern.
         */
        $renderer =
            new TemplateRenderer();

        $content =
            $renderer->render(
                'Repository.stub',
                [
                    'namespace' =>
                        $namespace,

                    'className' =>
                        $className,

                    'findCode' =>
                        $findCode,

                    'findAllCode' =>
                        $findAllCode,

                    'insertCode' =>
                        $insertCode,

                    'updateCode' =>
                        $updateCode,

                    'deleteCode' =>
                        $deleteCode,
                ]
            );

        /*
         * Zieldatei.
         */
        $target =
            $repositoryDirectory .
            '/' .
            $className .
            '.php';

        $bytes =
            file_put_contents(
                $target,
                $content
            );

        if ($bytes === false) {
            throw new RuntimeException(
                'Repository-Datei konnte nicht geschrieben werden: ' .
                $target
            );
        }

        return $target;
    }

    /**
     * Generiert den Code für find().
     */
    private function generateFindCode(
        string $table,
        string $primaryKey
    ): string {
        return <<<PHP
\$stmt = \$this->db->prepare("
    SELECT *
    FROM {$table}
    WHERE {$primaryKey} = ?
");

if (!\$stmt) {
    throw new \\RuntimeException(
        \$this->db->error
    );
}

\$stmt->bind_param(
    "s",
    \$id
);

if (!\$stmt->execute()) {
    throw new \\RuntimeException(
        \$stmt->error
    );
}

\$result = \$stmt->get_result();

return \$result->fetch_assoc() ?? [];
PHP;
    }

    /**
     * Generiert den Code für findAll().
     */
    private function generateFindAllCode(
        string $table
    ): string {
        return <<<PHP
\$result = \$this->db->query(
    "SELECT * FROM {$table}"
);

if (!\$result) {
    throw new \\RuntimeException(
        \$this->db->error
    );
}

return \$result->fetch_all(
    MYSQLI_ASSOC
);
PHP;
    }

    /**
     * Generiert den Code für insert().
     *
     * Die Repository-Schicht erzeugt die UUID selbst.
     * Alle Werte werden zunächst in lokale Variablen
     * geschrieben, weil mysqli::bind_param()
     * Variablen per Referenz benötigt.
     */
    private function generateInsertCode(
        string $table,
        string $primaryKey
    ): string {
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

        $columns = [];
        $placeholders = [];
        $parameters = [];
        $assignments = [];
        $types = '';

        while ($row = $result->fetch_assoc()) {
            $field =
                trim(
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
             * Die UUID wird weiter unten erzeugt.
             */
            if ($field === $primaryKey) {
                $columns[] = $field;
                $placeholders[] = '?';
                $parameters[] = '$id';
                $types .= 's';

                continue;
            }

            /*
             * Zeitstempel mit DEFAULT CURRENT_TIMESTAMP
             * nicht explizit setzen.
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
            $variable =
                '$' .
                $this->fieldToVariable(
                    $field
                );

            $columns[] = $field;
            $placeholders[] = '?';
            $parameters[] = $variable;
            $types .= 's';

            /*
             * Lokale Variable für bind_param().
             */
            $assignments[] =
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

        $columnSql =
            implode(
                ",\n        ",
                $columns
            );

        $placeholderSql =
            implode(
                ', ',
                $placeholders
            );

        $parameterSql =
            implode(
                ",\n    ",
                $parameters
            );

        $assignmentSql =
            implode(
                PHP_EOL,
                $assignments
            );

        return <<<PHP
\$id = bin2hex(random_bytes(16));

{$assignmentSql}

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

return \$id;
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
         * PHP-Variablen dürfen nicht mit einer Zahl
         * beginnen.
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

    /**
     * Wandelt einen Tabellennamen in einen Entity-Namen.
     *
     * Beispiele:
     *
     * address       -> Address
     * user_address  -> UserAddress
     * blog_posts    -> BlogPosts
     */
    private function tableToEntity(
        string $table
    ): string {
        $parts =
            preg_split(
                '/[^a-zA-Z0-9]+/',
                trim($table)
            );

        if ($parts === false) {
            return '';
        }

        $entity = '';

        foreach ($parts as $part) {
            $part =
                trim(
                    (string) $part
                );

            if ($part === '') {
                continue;
            }

            $entity .= ucfirst(
                strtolower($part)
            );
        }

        return $entity;
    }
}
