<?php
declare(strict_types=1);

namespace CMS\Application\FormData;

interface FormDataLoaderInterface
{
    public function supports(string $formAction): bool;

    public function hydrate(array $block, array $post): array;
}