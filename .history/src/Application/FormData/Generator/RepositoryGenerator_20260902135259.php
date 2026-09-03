<?php

declare(strict_types=1);

namespace CMS\Application\FormData\Generator;

use mysqli;

final class RepositoryGenerator
{
    public function __construct(
        private mysqli $db
    ) {
    }

    public function generate(array $config): string
    {
        // folgt im nächsten Schritt
        return '';
    }
}
