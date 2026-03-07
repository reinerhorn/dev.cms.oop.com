<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Core\Controller\Layout\HeaderController;
use CMS\Repository\Navigation\Navigation;
use CMS\Core\Controller\Layout\FooterController;
use CMS\Core\CMSApp;
use CMS\Core\Controller\Layout\LanguageSelectorController;
 
/**
 * LayoutController
 * ----------------
 * Orchestrator für das komplette Seitenlayout.
 * Lädt KEINE Daten selbst aus der DB, sondern delegiert strikt an:
 *  - HeaderController
 *  - NavigationController
 *  - FooterController
 */
class LayoutController
{
    private string $language;
    private string $roleId;
    private string $context;

    public function __construct(
        mysqli $db,
        string $language,
        string $roleId,
        string $context = 'frontend'
    ) {
        $this->language = $language;
        $this->roleId   = $roleId;
        $this->context  = $context;
    }

    public function getLayout(): array
    {
        $db = CMSApp::getDb();

        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->roleId,
            $this->context
        );

        $navigation = $navigationRepo->getItems();

        return [
            // HEADER → Sprache + Rolle
            'header' => HeaderController::getHeaderData(
                $this->language,
                $this->roleId
            ),

            // NAVIGATION → rollenabhängig (Frontend / Admin / Member)
            'navigation' => $navigation,

            // LANGUAGE SELECTOR → Sprache
            'language_selector' => (new LanguageSelectorController(
                $db,
                $this->language
            ))->getData(),

            // FOOTER → Sprache
            'footer' => FooterController::getFooterData(
                $this->language
            ),
        ];
    }
}