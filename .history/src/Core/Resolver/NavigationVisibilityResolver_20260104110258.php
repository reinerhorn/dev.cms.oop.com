<?php
declare(strict_types=1);

namespace CMS\Core\Resolver;

use CMS\Core\Service\AuthService;
use CMS\Core\Service\RoleService;

class NavigationVisibilityResolver
{
    private AuthService $authService;
    private RoleService $roleService;

    public function __construct(
        AuthService $authService,
        RoleService $roleService
    ) {
        $this->authService = $authService;
        $this->roleService = $roleService;
    }

    /**
     * Entscheidet, ob ein Navigationseintrag sichtbar ist
     *
     * Erwartete Felder im $navigationRow:
     * - visible_permission (NULL | string)
     */
    public function isVisible(array $navigationRow): bool
    {
        // Kein Permission-Eintrag → öffentlich
        if (empty($navigationRow['visible_permission'])) {
            return true;
        }

        // Rolle des aktuellen Users
        $roleId = $this->authService->getRoleId();

        // Sicherheit: keine Rolle → nichts sehen
        if ($roleId === null) {
            return false;
        }

        return $this->roleService->roleHasPermission(
            $roleId,
            $navigationRow['visible_permission']
        );
    }
}
