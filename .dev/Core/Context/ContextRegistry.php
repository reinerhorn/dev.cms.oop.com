<?php
declare(strict_types=1);

namespace CMS\Core\Context;

final class ContextRegistry
{
    public const MAP = [
        // Öffentliche Standardseiten
        'frontend' => [
            'requires_login' => false,
            'layout' => 'frontend',
        ],

        // Login / Register / Verify – immer öffentlich
        'frontend-auth' => [
            'requires_login' => false,
            'layout' => 'frontend',
            'is_auth_page' => true,
        ],

        // Geschützter Bereich
        'backend' => [
            'requires_login' => true,
            'layout' => 'backend',
        ],
    ];

    public static function get(string $context): array
    {
        return self::MAP[$context]
            ?? throw new \RuntimeException('Invalid context: ' . $context);
    }
}
