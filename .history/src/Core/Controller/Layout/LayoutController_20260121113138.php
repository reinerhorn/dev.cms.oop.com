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

    public function __construct(
        string $language,
        string $roleId,
        string $navId
    ) {
        $this->language = $language;
        $this->roleId   = $roleId !== '' ? $roleId : 'guest-role-000';
        $this->navId    = $navId;
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
        // Navigation (Rolle + Context + Permission)
        // ---------------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->navId,
            $roleService,
            $this->roleId
        );

        return [
            // HEADER
            'header' => HeaderController::getHeaderData(
                $this->language,
                'frontend'
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
                'frontend'
            ),
        ];
    }
}