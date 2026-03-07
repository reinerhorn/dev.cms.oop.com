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
 * ----------------
 * Zentrale Orchestrierung des Seitenlayouts.
 * Lädt selbst KEINE Daten direkt aus der DB,
 * sondern delegiert strikt an spezialisierte Controller.
 */
class LayoutController
{
    private string $language;
    private string $roleId;
    private string $context;

    /**
     * @param string $language z.B. "de"
     * @param string $roleId   z.B. "guest-role-000", "admin-role-001"
     * @param string $context  z.B. "frontend", "admin", "member"
     */
    public function __construct(
        string $language,
        string $roleId,
        string $context = 'frontend'
    ) {
        $this->language = $language;
        $this->roleId   = $roleId;
        $this->context  = $context;
    }

    /**
     * Baut das komplette Layout zusammen
     */
    public function getLayout(): array
    {
        $db = CMSApp::getDb();

        // ---------------------------------
        // Navigation (rollen- & contextabhängig)
        // ---------------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->roleId,
            $this->context
        );

        // ---------------------------------
        // Rückgabe an Twig
        // ---------------------------------
        return [
            // HEADER → Sprache + Context (+ Übersetzung intern)
            'header' => HeaderController::getHeaderData(
                $this->language,
                $this->context
            ),

            // NAVIGATION → Context + Rolle
            'navigation' => $navigationRepo->getItems(),

            // LANGUAGE SELECTOR → Sprache
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