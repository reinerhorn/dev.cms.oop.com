<?php
declare(strict_types=1);

namespace CMS\Security;

use CMS\Application\Service\RoleService;
use CMS\Application\Repository\PageRepository;
use RuntimeException;

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
        if (($pageMeta['enabled'] ?? 0) !== 1) {
            throw new RuntimeException('Seite deaktiviert');
        }

        if (($pageMeta['auth_visibility'] ?? 'public') === 'public') {
            return;
        }

        if ($roleId === self::GUEST_ROLE) {
            throw new RuntimeException('Login erforderlich');
        }

        if (!empty($pageMeta['required_permission_id'])) {
            if (!$this->roleService->roleHasPermission(
                $roleId,
                $pageMeta['required_permission_id']
            )) {
                throw new RuntimeException('Keine Berechtigung');
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

        // PUBLIC ACTION → immer erlaubt
        if (($actionMeta['is_public'] ?? 0) === 1) {
            return;
        }

        // Ab hier: Login zwingend
        if ($roleId === self::GUEST_ROLE) {
            throw new RuntimeException('Login erforderlich');
        }

        // Permission prüfen (action === permission_id)
        if (!empty($actionMeta['permission_id'])) {
            if (!$this->roleService->roleHasPermission(
                $roleId,
                $actionMeta['permission_id']
            )) {
                throw new RuntimeException('Keine Berechtigung für diese Aktion');
            }
        }
    }
}
