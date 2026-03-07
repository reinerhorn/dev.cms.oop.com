<?php
declare(strict_types=1);

namespace CMS\Core\Resolver;

/**
 * Entscheidet ausschließlich über die Sichtbarkeit
 * eines Navigation-Items anhand eines Rollen-Levels.
 *
 * KEINE Datenbank
 * KEIN Auth
 * KEINE Permissions
 */
class NavigationVisibilityResolver
{
    /**
     * @param int|null $visibleFromRole  Mindest-Rollenlevel (NULL = öffentlich)
     * @param int      $currentRoleLevel Aktuelles Rollenlevel (0,1,2,…)
     */
    public function isVisible(
        ?int $visibleFromRole,
        int $currentRoleLevel
    ): bool {
        // NULL = für alle sichtbar
        if ($visibleFromRole === null) {
            return true;
        }

        // Rolle ausreichend?
        return $currentRoleLevel >= $visibleFromRole;
    }
}
