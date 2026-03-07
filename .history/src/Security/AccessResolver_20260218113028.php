<?php
declare(strict_types=1);

namespace CMS\Security;

use CMS\Application\Service\RoleService;
use CMS\Application\Repository\PageRepository;
use RuntimeException;
use CMS\Security\Exception\ForbiddenException;

final class AccessResolver
{
    private const GUEST_ROLE = 'guest-role-000';

    public function __construct(
        private RoleService $roleService,
        private PageRepository $pageRepository
    ) {}

    /* =========================
       PAGE ACCESS
       ========================= */
    public function assertPageAccess(array $pageMeta, string $roleId): void
    {
    error_log('ASSERT ACCESS HIT: ' . $pageMeta['page_uuid']); 
        if (($pageMeta['enabled'] ?? 0) !== 1) {
            throw new RuntimeException('Seite deaktiviert');
        }

        if (($pageMeta['auth_visibility'] ?? 'public') === 'public') {
            return;
        }

        if ($roleId === self::GUEST_ROLE) {
            throw new ForbiddenException('Login erforderlich');
        }

        if (!empty($pageMeta['required_permission_id'])) {
            if (!$this->roleService->roleHasPermission(
                $roleId,
                $pageMeta['required_permission_id']
            )) {
                throw new ForbiddenException('Keine Berechtigung');
            }
        }
    }

    /* =========================
       FORM ACTION ACCESS
       ========================= */
    public function assertFormActionAllowed(array $actionMeta, string $roleId): void
    {
        if (empty($actionMeta['action'])) {
            throw new RuntimeException('Ungültige Action');
        }

        // KEINE Permission definiert → PUBLIC ACTION
        if (empty($actionMeta['permission_id'])) {
            return;
        }

        // Ab hier: Permission existiert → Login erforderlich
        if ($roleId === self::GUEST_ROLE) {
            throw new ForbiddenException('Login erforderlich');
        }

        if (!$this->roleService->roleHasPermission(
            $roleId,
            $actionMeta['permission_id']
        )) {
            throw new ForbiddenException('Keine Berechtigung für diese Aktion');
        }
    }

    public function resolveStartPage(string $roleId, string $language): string
    {
        $pageId = $this->roleService->getDefaultPageId($roleId);

        if (!$pageId) {
            return $this->buildUrl($language, 'startseite');
        }

        $slug = $this->pageRepository->getSlugByPageId($pageId);

        return $this->buildUrl(
            $language,
            $slug ?? 'startseite'
        );
    }

    private function buildUrl(string $language, string $slug): string
    {
        return '/' . $language . '/' . ltrim($slug, '/');
    }
}
