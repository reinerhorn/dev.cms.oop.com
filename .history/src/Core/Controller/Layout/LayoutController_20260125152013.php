<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;
use CMS\Repository\Navigation\Navigation;
use CMS\Core\Controller\Layout\HeaderController;
use CMS\Core\Controller\Layout\FooterController;
use CMS\Core\Controller\Layout\LanguageSelectorController;
use CMS\Core\Service\RoleService;

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

        // ---------------------------------
        // RoleService (ZENTRAL)
        // ---------------------------------
        $roleService = new RoleService($db);

        // ---------------------------------
        // UI-CONTEXT (entscheidet Header & Navigation)
        // ---------------------------------
        $uiContext = $this->context;

        // Backend → UI-Kontext aus Rolle ableiten
        if ($this->context === 'backend') {
            $uiContext = match ($this->roleId) {
                'admin-role-001'  => 'admin',
                'member-role-001' => 'member',
                'shop-role-001'   => 'shop',
                default           => 'frontend', // Fallback
            };
        }

        // ---------------------------------
        // Navigation (Rolle + Context + Permission)
        // ---------------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $uiContext,
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
                $uiContext
            ),

            // NAVIGATION
            'navigation' => $navigationRepo->getItems(),

            // LANGUAGE SELECTOR
            'language_selector' => (new LanguageSelectorController(
                $db,
                $this->language
            ))->getData(),

            // FOOTER
            'footer' => FooterController::getFooterData(
                $this->language,
                $uiContext
            ),

            'nav_css_class' => $navCssClass,
        ];
    }
}