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

        // -----------------------------
        // Navigation
        // -----------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->roleId,
            $this->context
        );

        // -----------------------------
        // Layout zusammensetzen
        // -----------------------------
        return [
            // HEADER → Sprache + Rolle
            'header' => HeaderController::getHeaderData(
                $this->language,
                $this->roleId
            ),

            // NAVIGATION → rollen- & contextabhängig
            'navigation' => $navigationRepo->getItems(),

            // LANGUAGE SELECTOR
            'language_selector' => (new LanguageSelectorController(
                $db,
                $this->language
            ))->getData(),

            // FOOTER → Sprache + Context
            'footer' => FooterController::getFooterData(
                $this->language,
                $this->context
            ),
        ];
    }
}