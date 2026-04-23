<?php
declare(strict_types=1);

namespace CMS\Application\Interface;



interface FormActionHandlerInterface
{
    public function supports(array $block): bool;

    public function handle(array $postData, array $pageMeta): array;
}
