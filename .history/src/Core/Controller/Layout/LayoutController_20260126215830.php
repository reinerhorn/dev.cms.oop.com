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
        // Audience (aus Rolle abgeleitet, KEIN Switch)
        // ---------------------------------
        $audience = $this->resolveAudience();

        // ---------------------------------
        // RoleService (ZENTRAL)
        // ---------------------------------
        $roleService = new RoleService($db);

        // ---------------------------------
        // Navigation (Rolle + Context + Permission)
        // ---------------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->context,
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
}