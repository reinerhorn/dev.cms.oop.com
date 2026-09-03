<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;

final class JsonFormGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): array
    {
        // Hier kommt anschließend die komplette
        // JSON-Formular-Generierung hinein.

        return [];
    }
}
