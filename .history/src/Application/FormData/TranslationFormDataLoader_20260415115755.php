<?php
declare(strict_types=1);

namespace CMS\Application\FormData;

use CMS\Application\Interface\DataFormLoaderInterface;
 
use mysqli;
final class TranslationFormDataLoader implements DataFormLoaderInterface
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * This loader is deprecated.
     * Hydration is now handled generically by EntityFormDataLoader
     * based on the presence of an "entity" configuration in the form JSON.
     */
    public function supports(array $block): bool
    {
        // Disable this specialized loader
        return false;
    }

    public function hydrate(array $block, array $request): array
    {
        // No-op – handled by generic EntityFormDataLoader
        return $block;
    }
}
