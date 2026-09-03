<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class ServiceGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): string
    {
        $renderer = new TemplateRenderer();

        $stub = dirname(__DIR__, 3) . '/Templates/Generator/Service.stub';

        if (!file_exists($stub)) {
            throw new RuntimeException("Service.stub nicht gefunden: {$stub}");
        }

        $content = $renderer->render('Service.stub', [
            'namespace' => $config['namespace'] ?? '',
            'className' => $config['className'] ?? 'GeneratedService',
            'entity' => $config['entity'] ?? 'Entity',
            'repositoryClass' => $config['repositoryClass'] ?? 'GeneratedRepository',
        ]);

        $target = rtrim($config['targetPath'] ?? '', '/') . '/' . ($config['className'] ?? 'GeneratedService') . '.php';

        file_put_contents($target, $content);

        return $target;
    }
}