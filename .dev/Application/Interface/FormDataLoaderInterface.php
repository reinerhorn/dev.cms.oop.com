<?php
declare(strict_types=1);

namespace CMS\Application\Interface;

interface FormDataLoaderInterface
{
    public function supports(string $formAction): bool;

    public function hydrate(array $block, array $request): array;
}