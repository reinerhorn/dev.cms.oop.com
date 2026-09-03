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
        /*
         * Grunddaten aus der Generator-Konfiguration
         */
        $entity = trim(
            (string) ($config['entity'] ?? '')
        );

        $namespace = trim(
            (string) ($config['namespace'] ?? '')
        );

        $className = trim(
            (string) ($config['className'] ?? '')
        );

        /*
         * Fallbacks
         */
        if ($entity === '') {
            $entity = 'Entity';
        }

        if ($namespace === '') {
            $namespace =
                'CMS\\Application\\FormData\\Service\\' .
                $entity;
        }

        if ($className === '') {
            $className =
                $entity . 'Service';
        }

        /*
         * Repository-Klasse
         */
        $repositoryClass = trim(
            (string) (
                $config['repositoryClass']
                ?? ($entity . 'Repository')
            )
        );

        if ($repositoryClass === '') {
            $repositoryClass =
                $entity . 'Repository';
        }

        /*
         * Repository-Namespace
         *
         * Beispiel:
         *
         * CMS\Application\FormData\Repository\Address
         */
        $repositoryNamespace = trim(
            (string) (
                $config['repositoryNamespace']
                ?? (
                    'CMS\\Application\\FormData\\Repository\\' .
                    $entity
                )
            )
        );

        if ($repositoryNamespace === '') {
            throw new RuntimeException(
                'Repository-Namespace konnte nicht bestimmt werden.'
            );
        }

        /*
         * Zielpfad
         */
        $targetPath = rtrim(
            (string) ($config['targetPath'] ?? ''),
            '/'
        );

        if ($targetPath === '') {
            throw new RuntimeException(
                'Service-Zielpfad fehlt.'
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
            $targetPath .
            '/' .
            $className .
            '.php';

        /*
         * Prüfen, ob das Zielverzeichnis existiert.
         */
        if (!is_dir($targetPath)) {
            if (
                !mkdir(
                    $targetPath,
                    0775,
                    true
                )
                && !is_dir($targetPath)
            ) {
                throw new RuntimeException(
                    'Service-Verzeichnis konnte nicht erstellt werden: ' .
                    $targetPath
                );
            }
        }

        /*
         * Prüfen, ob das Zielverzeichnis beschreibbar ist.
         */
        if (!is_writable($targetPath)) {
            throw new RuntimeException(
                'Service-Verzeichnis ist nicht beschreibbar: ' .
                $targetPath
            );
        }

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

