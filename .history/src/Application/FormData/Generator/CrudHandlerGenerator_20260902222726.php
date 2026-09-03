<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class CrudHandlerGenerator
{
    private string $projectRoot;

    public function __construct(
        private mysqli $db
    ) {
        /*
         * Datei:
         *
         * src/Application/FormData/Generator/CrudHandlerGenerator.php
         *
         * __DIR__:
         *
         * src/Application/FormData/Generator
         *
         * ../../../ führt zurück zum Projektverzeichnis.
         */
        $this->projectRoot = dirname(__DIR__, 4);
    }

    public function generate(array $config): array
    {
        error_log('========== CRUD HANDLER GENERATOR START ==========');
        error_log(
            'CRUD HANDLER CONFIG: ' .
            print_r($config, true)
        );

        /*
         * Die Tabelle wird zentral vom GeneratorManager
         * als String übergeben.
         *
         * Beispiel:
         *
         * $config['table'] = 'address';
         */
        $table = trim(
            (string) ($config['table'] ?? '')
        );

        /*
         * Kompatibilität:
         *
         * Falls ältere Aufrufe ein Array verwenden.
         */
        if ($table === '' && isset($config['table'])) {
            $tables = $config['table'];

            if (is_array($tables)) {
                $table = trim(
                    (string) ($tables[0] ?? '')
                );
            } else {
                $table = trim(
                    (string) $tables
                );
            }
        }

        if ($table === '') {
            throw new RuntimeException(
                'Keine Tabelle übergeben.'
            );
        }

        error_log(
            'CRUD HANDLER TABLE: [' .
            $table .
            ']'
        );

        /*
         * Beispiel:
         *
         * address
         *
         * wird:
         *
         * Address
         */
        $className = $this->buildClassName($table);

        /*
         * Namespace für den generierten Handler.
         */
        $namespace =
            'CMS\\Application\\FormData\\Handler\\' .
            $className;

        /*
         * Expliziter Zielpfad.
         *
         * Ergebnis:
         *
         * /Projekt/src/Application/FormData/Handler/Address
         */
        $handlerBaseDirectory =
            $this->projectRoot .
            '/src/Application/FormData/Handler';

        $directory =
            $handlerBaseDirectory .
            '/' .
            $className;

        error_log(
            'CRUD HANDLER PROJECT ROOT: ' .
            $this->projectRoot
        );

        error_log(
            'CRUD HANDLER BASE DIRECTORY: ' .
            $handlerBaseDirectory
        );

        error_log(
            'CRUD HANDLER TARGET DIRECTORY: ' .
            $directory
        );

        /*
         * Prüfen, ob das Basisverzeichnis existiert.
         */
        if (!is_dir($handlerBaseDirectory)) {
            if (
                !mkdir(
                    $handlerBaseDirectory,
                    0775,
                    true
                )
                && !is_dir($handlerBaseDirectory)
            ) {
                throw new RuntimeException(
                    'Handler-Basisverzeichnis konnte nicht erstellt werden: ' .
                    $handlerBaseDirectory
                );
            }
        }

        /*
         * Das Entity-Verzeichnis anlegen.
         *
         * Beispiel:
         *
         * src/Application/FormData/Handler/Address
         */
        if (!is_dir($directory)) {
            error_log(
                'CRUD HANDLER CREATE DIRECTORY: ' .
                $directory
            );

            if (
                !mkdir(
                    $directory,
                    0775,
                    true
                )
                && !is_dir($directory)
            ) {
                throw new RuntimeException(
                    'Handler-Verzeichnis konnte nicht erstellt werden: ' .
                    $directory
                );
            }

            error_log(
                'CRUD HANDLER DIRECTORY CREATED'
            );
        } else {
            error_log(
                'CRUD HANDLER DIRECTORY ALREADY EXISTS'
            );
        }

        /*
         * Prüfen, ob der Zielordner jetzt wirklich existiert.
         */
        if (!is_dir($directory)) {
            throw new RuntimeException(
                'Handler-Verzeichnis existiert nach mkdir() nicht: ' .
                $directory
            );
        }

        /*
         * Schreibrechte prüfen.
         */
        if (!is_writable($directory)) {
            throw new RuntimeException(
                'Handler-Verzeichnis ist nicht beschreibbar: ' .
                $directory
            );
        }

        /*
         * Dateiname.
         *
         * Beispiel:
         *
         * AddressHandler.php
         */
        $file =
            $directory .
            '/' .
            $className .
            'Handler.php';

        error_log(
            'CRUD HANDLER FILE: ' .
            $file
        );

        /*
         * PHP-Code erzeugen.
         */
        $code = $this->buildHandlerTemplate(
            $namespace,
            $className,
            $table
        );

        /*
         * Datei schreiben.
         */
        $bytes = file_put_contents(
            $file,
            $code
        );

        if ($bytes === false) {
            throw new RuntimeException(
                'Handler-Datei konnte nicht geschrieben werden: ' .
                $file
            );
        }

        error_log(
            'CRUD HANDLER FILE WRITTEN: ' .
            $file
        );

        error_log(
            '========== CRUD HANDLER GENERATOR COMPLETE =========='
        );

        return [
            'success' => true,
            'class' => $className . 'Handler',
            'namespace' => $namespace,
            'file' => $file,
            'directory' => $directory,
            'table' => $table,
        ];
    }

    private function buildClassName(
        string $table
    ): string {
        /*
         * address
         * → Address
         *
         * customer_address
         * → CustomerAddress
         */
        $parts = preg_split(
            '/[^a-zA-Z0-9]+/',
            $table
        );

        if ($parts === false) {
            return 'Generated';
        }

        $className = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $className .= ucfirst(
                strtolower($part)
            );
        }

        if ($className === '') {
            $className = 'Generated';
        }

        return $className;
    }

    private function buildHandlerTemplate(
        string $namespace,
        string $className,
        string $table
    ): string {
        /*
         * FieldGenerator, InsertGenerator und UpdateGenerator
         * befinden sich alle im selben Namespace:
         *
         * CMS\Application\FormData\Generator
         */
        $fieldGenerator =
            new FieldGenerator($this->db);

        $insertGenerator =
            new InsertGenerator($this->db);

        $updateGenerator =
            new UpdateGenerator($this->db);

        $renderer =
            new TemplateRenderer();

        return $renderer->render(
            'CrudHandler.stub',
            [
                'namespace' => $namespace,

                'className' =>
                    $className . 'Handler',

                'table' => $table,

                'entity' => $className,

                'primaryKey' =>
                    $fieldGenerator->getPrimaryKey(
                        $table
                    ),

                'fieldAssignments' =>
                    $fieldGenerator->generateAssignments(
                        $table
                    ),

                'insertCode' =>
                    $insertGenerator->generate(
                        $table
                    ),

                'updateCode' =>
                    $updateGenerator->generate(
                        $table
                    ),
            ]
        );
    }
}

