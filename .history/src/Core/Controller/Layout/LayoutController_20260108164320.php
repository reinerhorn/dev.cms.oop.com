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
 * ----------------
 * Orchestriert das Seitenlayout.
 * Lädt selbst KEINE Daten direkt,
 * sondern delegiert strikt an spezialisierte Controller.
 *
 * FIXED:
 * - Header/Footer bekommen exakt (language, context)
 * - Keine Legacy-Wrapper mit falscher Signatur
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

    /**
     * Baut das komplette Layout zusammen
     */
    public function getLayout(): array
    {
        $db = CMSApp::getDb();

        // -----------------------------
        // ROLE / PERMISSION GUARD
        // -----------------------------
        $roleService = new RoleService($db);

        // Wenn Admin-Context angefordert ist, aber Rolle keine Admin-Permission hat,
        // automatisch auf Frontend-Context zurückfallen
        if ($this->context === 'admin' && !$roleService->roleHasPermission($this->roleId, 'perm-view-admin')) {
            $this->context = 'frontend';
        }

        // Sicherheits-Fallback: Rolle MUSS existieren
        if ($this->roleId === '') {
            $this->roleId = 'guest-role-000';
        }

        // Rolle wird IMMER an Navigation durchgereicht
        // → Filterung erfolgt über role_id + context in der DB
        // -----------------------------
        // Navigation (rollen + context)
        // -----------------------------
        $navigationRepo = new Navigation(
            $db,
            $this->language,
            $this->context,
            $roleService,
            $this->roleId
        );

        return [
            // HEADER → Sprache + Context (Übersetzung intern über fk_translation_placeholder)
            'header' => HeaderController::getHeaderData(
                $this->language,
                $this->context
            ),

            // NAVIGATION → Rolle + Context
            'navigation' => $navigationRepo->getItems(),

            // LANGUAGE SELECTOR → Sprache
            'language_selector' => (new LanguageSelectorController(
                $db,
                $this->language
            ))->getData(),

            // FOOTER → Sprache + Context (analog Header)
            'footer' => FooterController::getFooterData(
                $this->language,
                $this->context
            ),
        ];
    }
}