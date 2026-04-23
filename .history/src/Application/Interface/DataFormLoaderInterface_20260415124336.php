<?php
declare(strict_types=1);

namespace CMS\Application\Interface;

interface DataformLoaderInterface
{
    public function supports(array $block): bool;

    public function hydrate(array $block, array $request): array;


}