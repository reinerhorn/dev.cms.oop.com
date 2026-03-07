<?php

namespace CMS\Core\PageBuilder;

use CMS\Core\Controller\Layout\LayoutController;
use CMS\Core\Controller\Layout\HeaderController;
use CMS\Core\Controller\Layout\FooterController;
use CMS\Repository\Navigation\Navigation;
use mysqli;

class PageBuilder
{
    private mysqli $db;
    private string $language;
    private string $role;

    public function __construct(mysqli $db, string $language, string $role)
    {
        $this->db = $db;
        $this->language = $language;
        $this->role = $role;
    }

    /**
     * Baut eine komplette Seite auf.
     */
    public function build(string $pageId): array
    {
        // --- Header ---
        $header = HeaderController::getHeaderData($this->language, $this->role);

        // --- Navigation ---
        // Navigation repository expects role as string (role id), so pass original value
        $navRepo = new Navigation($this->db, $this->language, $this->role);
        $navigation = $navRepo->getItems();

        // --- Content ---
        $layout = new LayoutController(
            $this->language,
            $this->role,
            'generalNav',
            'frontend'
        );
        $layout->getLayout();

        $contentTemplate = 'pages/default.twig';
        $contentData = [];

        // --- Footer ---
        $footer = FooterController::getFooterData($this->language, 'frontend');

        return [
            'meta' => [
                'language' => $this->language,
                'role' => $this->role,
            ],
            'header' => $header,
            'navigation' => $navigation,
            'content_template' => $contentTemplate,
            'content_data' => $contentData,
            'footer' => $footer,
        ];
    }
}
