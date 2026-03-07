<?php
declare(strict_types=1);

namespace CMS\Security;

use CMS\Application\Service\RoleService;
use CMS\Application\Repository\PageRepository;

final class AccessResolver
{
    private const DEFAULT_SLUG = 'startseite';

    public function __construct(
        private RoleService $roleService,
        private PageRepository $pageRepository
    ) {}

    /**
     * Ermittelt die Startseite anhand Rolle + Sprache
     *
     * Ablauf:
     * 1. role → default_page_id (DB)
     * 2. page_id → slug (DB)
     * 3. fallback → startseite
     */
    public function resolveStartPage(
        string $roleId,
        string $language
    ): string {
        // 1) Page-ID aus der Rolle holen
        $pageId = $this->roleService->getDefaultPageId($roleId);

        if (!$pageId) {
            return $this->buildUrl($language, self::DEFAULT_SLUG);
        }

        // 2) Slug aus der Page holen
        $slug = $this->pageRepository->getSlugByPageId($pageId);

        if (!$slug) {
            return $this->buildUrl($language, self::DEFAULT_SLUG);
        }

        // 3) Finale URL
        return $this->buildUrl($language, $slug);
    }

    /**
     * Baut konsistent /{lang}/{slug}
     */
    private function buildUrl(string $language, string $slug): string
    {
        return '/' . $language . '/' . ltrim($slug, '/');
    }
}
