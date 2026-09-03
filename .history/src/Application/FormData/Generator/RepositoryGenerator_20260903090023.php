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
         * Ziel:
         *
         * src/Application/FormData/Repository/Address
         */
        $basePath = dirname(
            __DIR__,
            
        );

        $repositoryDirectory =
            $basePath .
            '/Repository/' .
            $entity;

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
         * Primärschlüssel ermitteln.
         */
        $fieldGenerator =
            new FieldGenerator($this->db);

        $primaryKey =
            $fieldGenerator->getPrimaryKey($table);

        /*
         * CRUD-Code erzeugen.
         */
        $findCode =
            $this->generateFindCode(
                $table,
                $primaryKey
            );

        $findAllCode =
            $this->generateFindAllCode(
                $table
            );

        $insertGenerator =
            new InsertGenerator($this->db);

        $insertCode =
            $insertGenerator->generate($table);

        /*
         * InsertGenerator erzeugt momentan
         * noch kein return der erzeugten ID.
         *
         * Deshalb ergänzen wir hier die ID-Rückgabe.
         */
        $insertCode .= PHP_EOL . PHP_EOL . 'return $id;';

        $updateGenerator =
            new UpdateGenerator($this->db);

        $updateCode =
            $updateGenerator->generate($table);

        $deleteGenerator =
            new DeleteGenerator($this->db);

        $deleteCode =
            $deleteGenerator->generate($table);

        /*
         * Template rendern.
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
         * Datei schreiben.
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

return \$stmt
    ->get_result()
    ->fetch_assoc() ?? [];
PHP;
    }

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

    private function tableToEntity(
        string $table
    ): string {
        $parts = preg_split(
            '/[^a-zA-Z0-9]+/',
            trim($table)
        );

        if ($parts === false) {
            return '';
        }

        $parts = array_filter(
            $parts,
            static fn (
                string $part
            ): bool => $part !== ''
        );

        $parts = array_map(
            static fn (
                string $part
            ): string =>
                ucfirst(
                    strtolower($part)
                ),
            $parts
        );

        return implode(
            '',
            $parts
        );
    }
}
