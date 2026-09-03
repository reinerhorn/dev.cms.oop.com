<?php

declare(strict_types=1);

namespace CMS\Application\Generator;

use mysqli;
use RuntimeException;

final class ControllerGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): string
    {
        $renderer = new TemplateRenderer();

        $stub = dirname(__DIR__, 2) . '/Templates/Generator/Controller.stub';

        if (!file_exists($stub)) {
            throw new RuntimeException("Controller.stub nicht gefunden: {$stub}");
        }

        $className = $config['className'] ?? 'GeneratedController';

        $content = $renderer->render($stub, [
            'namespace' => $config['namespace'] ?? '',
            'className' => $className,
            'entity' => $config['entity'] ?? 'Entity',
            'serviceNamespace' => $config['serviceNamespace'] ?? ($config['namespace'] ?? ''),
            'serviceClass' => $config['serviceClass'] ?? 'GeneratedService',
        ]);

        $target = rtrim($config['targetPath'] ?? '', '/') . '/' . $className . '.php';

        file_put_contents($target, $content);

        return $target;
    }
}