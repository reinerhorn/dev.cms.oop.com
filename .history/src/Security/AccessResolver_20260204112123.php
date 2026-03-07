<?php
declare(strict_types=1);
namespace CMS\Security;
use CMS\Application\Repository\RoleRepository;
use CMS\Application\Repository\PageRepository;
final class AccessResolver
{
    public function __construct(
        private RoleRepository $roleRepo,
        private PageRepository $pageRepo
    ) {}

    public function resolveStartPage(
        string $roleId,
        string $language
    ): string {
        $pageId = $this->roleRepo->getDefaultPageId($roleId);

        if (!$pageId) {
            return '/' . $language . '/startseite';
        }

        $slug = $this->pageRepo->getSlugByPageId($pageId)
            ?? 'startseite';

        return '/' . $language . '/' . $slug;
    }
}
