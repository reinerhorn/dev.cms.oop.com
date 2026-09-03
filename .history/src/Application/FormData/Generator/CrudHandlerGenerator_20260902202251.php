<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;
use RuntimeException;

final class CrudHandlerGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    /**
     * Generiert einen CRUD-Handler für die angegebene Tabelle.
     */
    public function generate(array $config): array
    {
        error_log('========== CRUD HANDLER GENERATOR START ==========');
        error_log(
            'CRUD HANDLER CONFIG: ' .
            print_r($config, true)
        );

        /*
         * Die zentrale Generator-Konfiguration verwendet
         * ausschließlich den Schlüssel "table".
         */
        $table = trim(
            (string) ($config['table'] ?? '')
        );

        if ($table === '') {
            throw new RuntimeException(
                'Keine Tabelle übergeben.'
            );
        }

        /*
         * Beispiel:
         *
         * address
         *
         * wird zu:
         *
         * Address
         */
        $className = $this->buildClassName(
            $table
        );

        /*
         * Generierter Namespace:
         *
         * CMS\Application\FormData\Handler\Address
         */
        $namespace =
            'CMS\\Application\\FormData\\Handler\\' .
            $className;

        /*
         * __DIR__:
         *
         * src/Application/FormData/Generator
         *
         * ../Handler/Address:
         *
         * src/Application/FormData/Handler/Address
         */
        $directory =
            __DIR__ .
            '/../Handler/' .
            $className;

        error_log(
            'CRUD HANDLER DIRECTORY: [' .
            $directory .
            ']'
        );

        /*
         * Zielverzeichnis automatisch erstellen.
         */
        if (!is_dir($directory)) {

            $created = mkdir(
                $directory,
                0775,
                true
            );

            /*
             * Race-Condition-sicher prüfen.
             */
            if (!$created && !is_dir($directory)) {

                $lastError = error_get_last();

                throw new RuntimeException(
                    'Handler-Verzeichnis konnte nicht erstellt werden: ' .
                    $directory .
                    ' | PHP Fehler: ' .
                    ($lastError['message'] ?? 'unbekannt')
                );
            }

            error_log(
                'CRUD HANDLER DIRECTORY CREATED: ' .
                $directory
            );
        }

        /*
         * Generierte Datei:
         *
         * src/Application/FormData/Handler/Address/AddressHandler.php
         */
        $file =
            $directory .
            '/' .
            $className .
            'Handler.php';

        /*
         * Handler-Code erzeugen.
         */
        $code = $this->buildHandlerTemplate(
            $namespace,
            $className,
            $table
        );

        /*
         * Datei schreiben.
         */
        $written = file_put_contents(
            $file,
            $code
        );

        if ($written === false) {
            throw new RuntimeException(
                'Handler-Datei konnte nicht geschrieben werden: ' .
                $file
            );
        }

        error_log(
            'CRUD HANDLER FILE CREATED: ' .
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

    /**
     * Wandelt einen Tabellennamen in einen PHP-Klassennamen um.
     *
     * Beispiele:
     *
     * address
     * → Address
     *
     * user_address
     * → UserAddress
     *
     * customer_addresses
     * → CustomerAddresses
     */
    private function buildClassName(
        string $table
    ): string {
        $table = trim($table);

        /*
         * Unterstriche und andere nicht-alphanumerische
         * Zeichen als Worttrenner behandeln.
         */
        $parts = preg_split(
            '/[^a-zA-Z0-9]+/',
            $table
        );

        if (
            $parts === false
            || $parts === []
        ) {
            throw new RuntimeException(
                'Ungültiger Tabellenname: ' .
                $table
            );
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
            throw new RuntimeException(
                'Klassennamen konnte nicht aus Tabelle erzeugt werden: ' .
                $table
            );
        }

        return $className;
    }

    /**
     * Baut den PHP-Code für den generierten Handler.
     */
    private function buildHandlerTemplate(
        string $namespace,
        string $className,
        string $table
    ): string {
        error_log(
            'CRUD HANDLER TEMPLATE TABLE: [' .
            $table .
            ']'
        );

        /*
         * Alle Generatoren befinden sich im gleichen Namespace:
         *
         * CMS\Application\FormData\Generator
         */
        $fieldGenerator = new FieldGenerator(
            $this->db
        );

        $insertGenerator = new InsertGenerator(
            $this->db
        );

        $updateGenerator = new UpdateGenerator(
            $this->db
        );

        $renderer = new TemplateRenderer();

        $primaryKey =
            $fieldGenerator->getPrimaryKey(
                $table
            );

        $fieldAssignments =
            $fieldGenerator->generateAssignments(
                $table
            );

        $insertCode =
            $insertGenerator->generate(
                $table
            );

        $updateCode =
            $updateGenerator->generate(
                $table
            );

        return $renderer->render(
            'CrudHandler.tpl.php',
            [
                /*
                 * Beispiel:
                 *
                 * CMS\Application\FormData\Handler\Address
                 */
                'namespace' => $namespace,

                /*
                 * Beispiel:
                 *
                 * AddressHandler
                 */
                'className' =>
                    $className . 'Handler',

                /*
                 * address
                 */
                'table' => $table,

                /*
                 * Address
                 */
                'entity' => $className,

                /*
                 * Primärschlüssel der Tabelle.
                 */
                'primaryKey' => $primaryKey,

                /*
                 * Feldzuweisungen.
                 */
                'fieldAssignments' =>
                    $fieldAssignments,

                /*
                 * INSERT-Code.
                 */
                'insertCode' =>
                    $insertCode,

                /*
                 * UPDATE-Code.
                 */
                'updateCode' =>
                    $updateCode,
            ]
        );
    }
}

