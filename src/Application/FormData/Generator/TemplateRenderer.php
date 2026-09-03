<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use RuntimeException;

final class TemplateRenderer
{
    private string $templatePath;


    public function __construct(
        ?string $templatePath = null
    ) {
        /*
         * Standardpfad:
         *
         * src/Application/FormData/Generator
         *
         * zurück nach src:
         *
         * dirname(__DIR__, 3)
         *
         * anschließend:
         *
         * src/Templates/Generator
         */
        $this->templatePath =
            $templatePath
            ?? dirname(__DIR__, 3) .
            '/Templates/Generator';
    }


    public function render(
        string $templateFile,
        array $variables = []
    ): string {
        /*
         * Vollständiger Dateipfad
         */
        $file =
            $this->templatePath .
            '/' .
            $templateFile;


        /*
         * Prüfen, ob das Template existiert
         */
        if (!is_file($file)) {
            throw new RuntimeException(
                'Template nicht gefunden: ' .
                $file
            );
        }


        /*
         * Template lesen
         */
        $content = file_get_contents($file);


        if ($content === false) {
            throw new RuntimeException(
                'Template konnte nicht gelesen werden: ' .
                $file
            );
        }


        /*
         * Alle übergebenen Variablen ersetzen.
         *
         * Unterstützt beide Varianten:
         *
         * {{table}}
         * {{TABLE}}
         *
         * {{className}}
         * {{CLASSNAME}}
         *
         * Dadurch können ältere und neuere
         * Generator-Templates gleichzeitig
         * verwendet werden.
         */
        foreach ($variables as $key => $value) {
            $value = (string) $value;


            $placeholders = [
                '{{' . $key . '}}',
                '{{' . strtoupper($key) . '}}',
            ];


            $content = str_replace(
                $placeholders,
                $value,
                $content
            );
        }


        /*
         * Prüfen, ob noch Platzhalter
         * im generierten Code vorhanden sind.
         */
        if (
            preg_match(
                '/{{[A-Za-z0-9_]+}}/',
                $content,
                $matches
            )
        ) {
            throw new RuntimeException(
                'Nicht ersetzter Template-Platzhalter: ' .
                $matches[0] .
                ' in Template: ' .
                $templateFile
            );
        }


        return $content;
    }
}

