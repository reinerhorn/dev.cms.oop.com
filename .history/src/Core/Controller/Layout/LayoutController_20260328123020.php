<?php
 
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;
use CMS\Repository\Navigation\Navigation;
use CMS\Core\Controller\Layout\HeaderController;
use CMS\Core\Controller\Layout\FooterController;
use CMS\Core\Controller\Layout\LanguageSelectorController;
 

/**
 * LayoutController
 *
 * Verantwortlich für:
 * - Auswahl von Header / Navigation / Footer
 * - Rollen- & Permission-basierte Steuerung
 * - Navigation über nav_id (DB-gesteuert)
 */
class LayoutController
{
 

    private string $language;
    private string $roleId;
    private string $navId;
    private string $context;

    public function __construct(
        string $language,
        string $roleId,
        string $navId,
        string $context
    ) {
        $this->language = $language;
        $this->roleId   = $roleId !== '' ? $roleId : 'guest-role-000';
        $this->navId    = $navId;
        $this->context  = $context;
    }

    /**
     * Baut das komplette Layout zusammen
     */
    public function getLayout(): array
    {
        $db = CMSApp::getDb();

        // 🔍 DEBUG: aktuelle Layout-Parameter anzeigen
        if (defined('CMS_DEBUG') && constant('CMS_DEBUG') === true) {
            echo "<pre style='background:#222;color:#0f0;padding:10px;'>";
            echo "DEBUG LayoutController\n";
            echo "Language: {$this->language}\n";
            echo "RoleId: {$this->roleId}\n";
            echo "NavId: {$this->navId}\n";
            echo "Context: {$this->context}\n";
            echo "</pre>";
        }

        // ---------------------------------
        // Audience (aus Rolle abgeleitet, KEIN Switch)
        // ---------------------------------
        $audience = $this->resolveAudience();

        // ---------------------------------
        // RoleService (ZENTRAL)
        // ---------------------------------
         $roleService = CMSApp::getRoleService();
        // ---------------------------------
        // Navigation (Rolle + Context + Permission)
        // ---------------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->resolveNavContext(),
            $roleService,
            $this->roleId
        );

        // ---------------------------------
        // NAV CSS CLASS (STRICT: CONTEXT ONLY)
        // ---------------------------------
        $navCssClass = ($this->context === 'backend')
            ? 'main-nav nav-backend'
            : 'main-nav nav-frontend';

        return [
            // HEADER
            'header' => HeaderController::getHeaderData(
                $this->language,
                $audience
            ),

            // NAVIGATION
            'navigation' => $navigationRepo->getItems(),

            'navId' => $this->navId,

            // LANGUAGE SELECTOR
            'language_selector' => (new LanguageSelectorController(
                $db,
                $this->language
            ))->getData(),

            // FOOTER
            'footer' => FooterController::getFooterData(
                $this->language,
                $audience
            ),

            'nav_css_class' => $navCssClass,
        ];
    }

    private function resolveAudience(): string
    {
        // guest oder leer = public
        if ($this->roleId === '' || $this->roleId === 'guest-role-000') {
            return 'public';
        }

        // Prefix vor dem ersten "-"
        return explode('-', $this->roleId, 2)[0];
    }

    private function resolveNavContext(): string
    {
        // Frontend bleibt Frontend
        if ($this->context === 'frontend') {
            return 'frontend';
        }

        // Backend → abhängig von Rolle
        if ($this->context === 'backend') {
            // admin-role-001 → admin
            // member-role-001 → member
            return explode('-', $this->roleId, 2)[0];
        }

        return 'frontend';
    }
}