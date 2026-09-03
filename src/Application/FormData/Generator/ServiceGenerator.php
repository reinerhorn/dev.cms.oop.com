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
        $entity = trim(
            (string) ($config['entity'] ?? 'Entity')
        );

        if ($entity === '') {
            $entity = 'Entity';
        }


        /*
         * Service-Namespace
         *
         * Beispiel:
         *
         * CMS\Application\FormData\Service\Address
         */
        $namespace = trim(
            (string) ($config['namespace'] ?? '')
        );

        if ($namespace === '') {
            $namespace =
                'CMS\\Application\\FormData\\Service\\' .
                $entity;
        }


        /*
         * Service-Klasse
         */
        $className = trim(
            (string) ($config['className'] ?? '')
        );

        if ($className === '') {
            $className =
                $entity . 'Service';
        }


        /*
         * Repository-Klasse
         */
        $repositoryClass = trim(
            (string) ($config['repositoryClass'] ?? '')
        );

        if ($repositoryClass === '') {
            $repositoryClass =
                $entity . 'Repository';
        }


        /*
         * Repository-Namespace
         */
        $repositoryNamespace = trim(
            (string) (
                $config['repositoryNamespace']
                ?? ''
            )
        );

        if ($repositoryNamespace === '') {
            $repositoryNamespace =
                'CMS\\Application\\FormData\\Repository\\' .
                $entity;
        }


        /*
         * output_path kommt zentral aus
         * GeneratorManager.
         */
        $outputPath = trim(
            (string) ($config['output_path'] ?? '')
        );

        if ($outputPath === '') {
            throw new RuntimeException(
                'Generator output_path fehlt.'
            );
        }


        /*
         * Service-Verzeichnis
         *
         * output_path:
         *
         * src/Application/FormData
         *
         * daraus:
         *
         * src/Application/FormData/Service/Address
         */
        $serviceDirectory =
            rtrim($outputPath, '/') .
            '/Service/' .
            $entity;


        /*
         * Verzeichnis erstellen
         */
        if (!is_dir($serviceDirectory)) {
            if (
                !mkdir(
                    $serviceDirectory,
                    0775,
                    true
                )
                && !is_dir($serviceDirectory)
            ) {
                throw new RuntimeException(
                    'Service-Verzeichnis konnte nicht erstellt werden: ' .
                    $serviceDirectory
                );
            }
        }


        /*
         * Schreibbarkeit prüfen
         */
        if (!is_writable($serviceDirectory)) {
            throw new RuntimeException(
                'Service-Verzeichnis ist nicht beschreibbar: ' .
                $serviceDirectory
            );
        }


        /*
         * Template rendern
         */
        $renderer = new TemplateRenderer();

        $content = $renderer->render(
            'Service.stub',
            [
                'namespace' =>
                    $namespace,

                'repositoryNamespace' =>
                    $repositoryNamespace,

                'repositoryClass' =>
                    $repositoryClass,

                'className' =>
                    $className,

                'entity' =>
                    $entity,
            ]
        );


        /*
         * Zieldatei
         */
        $target =
            $serviceDirectory .
            '/' .
            $className .
            '.php';


        /*
         * Datei schreiben
         */
        $bytes = file_put_contents(
            $target,
            $content
        );

        if ($bytes === false) {
            throw new RuntimeException(
                'Service-Datei konnte nicht geschrieben werden: ' .
                $target
            );
        }


        return $target;
    }
}

