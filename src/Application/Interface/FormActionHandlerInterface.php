<?php
declare(strict_types=1);

namespace CMS\Application\Interface;



interface FormActionHandlerInterface
{
    public function supports(string $formAction): bool;

    public function handle(array $postData, array $pageMeta): array;
}
