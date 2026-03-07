<?php
declare(strict_types=1);

namespace CMS\Core\Context;

final class ContextRegistry
{
    public const MAP = [
        'frontend' => [
            'requires_login' => false,
            'layout' => 'frontend',
        ],
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
