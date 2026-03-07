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
     * Zentrale Zugriffsprüfung für Pages
     *
     * Regeln:
     * - public           → immer erlaubt
     * - auth             → nur eingeloggt
     * - required_permission_id → Permission nötig
     */
    public function assertPageAccess(
        array $pageMeta,
        string $roleId
    ): void {
        // Seite deaktiviert
        if (($pageMeta['enabled'] ?? 0) !== 1) {
            throw new \RuntimeException('Seite deaktiviert');
        }

        // Public → immer erlaubt
        if (($pageMeta['auth_visibility'] ?? 'public') === 'public') {
            return;
        }

        // Ab hier: Login nötig
        if ($roleId === 'guest-role-000') {
            throw new \RuntimeException('Login erforderlich');
        }

        // Permission prüfen (falls gesetzt)
        $requiredPermission = $pageMeta['required_permission_id'] ?? null;

        if ($requiredPermission) {
            if (!$this->roleService->roleHasPermission($roleId, $requiredPermission)) {
                throw new \RuntimeException('Keine Berechtigung');
            }
        }
    }

    /**
     * Ermittelt die Startseite anhand Rolle + Sprache
     * DB-gesteuert (roles.default_page_id)
     */
    public function resolveStartPage(
        string $roleId,
        string $language
    ): string {
        $pageId = $this->roleService->getDefaultPageId($roleId);

        if (!$pageId) {
            return $this->buildUrl($language, self::DEFAULT_SLUG);
        }

        $slug = $this->pageRepository->getSlugByPageId($pageId);

        return $this->buildUrl(
            $language,
            $slug ?? self::DEFAULT_SLUG
        );
    }

    private function buildUrl(string $language, string $slug): string
    {
        return '/' . $language . '/' . ltrim($slug, '/');
    }
}
