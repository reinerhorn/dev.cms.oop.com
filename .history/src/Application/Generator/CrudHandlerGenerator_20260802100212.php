<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;

final class CrudHandlerGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): array
    {
        $table = $config['table'][0] ?? null;

        if ($table === null || $table === '') {
            throw new \RuntimeException('Keine Tabelle übergeben.');
        }

        $className = $this->buildClassName($table);
        $namespace = 'CMS\\Application\\Handler\\' . $className;

        $directory = dirname(__DIR__) . '/Handler/' . $className;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Handler-Verzeichnis konnte nicht erstellt werden: ' . $directory);
        }

        $file = $directory . '/' . $className . 'Handler.php';

        $code = $this->buildHandlerTemplate($namespace, $className, $table);

        file_put_contents($file, $code);

        return [
            'success' => true,
            'class' => $className . 'Handler',
            'namespace' => $namespace,
            'file' => $file,
            'table' => $table,
        ];
    }

    private function buildClassName(string $table): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($table))));
    }

    private function buildHandlerTemplate(string $namespace, string $className, string $table): string
    {
        $renderer = new TemplateRenderer();

        return $renderer->render(
            'CrudHandler.tpl.php',
            [
                'namespace' => $namespace,
                'className' => $className . 'Handler',
                'table' => $table,
                'entity' => $className,
                'primaryKey' => 'id',
                'fieldAssignments' => '// wird vom Generator erzeugt',
                'insertCode' => '// INSERT-Code wird erzeugt',
                'updateCode' => '// UPDATE-Code wird erzeugt',
            ]
        );
    }
}