<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

final class TemplateRenderer
{
    private string $templatePath;

    public function __construct(?string $templatePath = null)
    {
        $this->templatePath = $templatePath
            ?? dirname(__DIR__, 3) . '/Templates/Generator';
    }

    public function render(string $templateFile, array $variables = []): string
    {
        $file = $this->templatePath . '/' . $templateFile;

        if (!is_file($file)) {
            throw new \RuntimeException(
                'Template nicht gefunden: ' . $file
            );
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException(
                'Template konnte nicht gelesen werden.'
            );
        }

        foreach ($variables as $key => $value) {
            $content = str_replace(
                '{{' . strtoupper($key) . '}}',
                (string)$value,
                $content
            );
        }

        return $content;
    }
}
