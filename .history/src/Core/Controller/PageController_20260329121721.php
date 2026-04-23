<?php
declare(strict_types=1);

namespace CMS\Controller;

use CMS\Core\CMSApp;
use CMS\Application\Repository\PageRepository;
use Twig\Environment;
use mysqli;
use CMS\Application\Service\RoleService;
use CMS\Security\AccessResolver;
use CMS\Routing\PageResolver;
use CMS\Core\CMSAppFrontend;

class PageController
{
    private Environment $twig;
    private mysqli $db;
    private PageRepository $pageRepository;
    private RoleService $roleService;
    private AccessResolver $accessResolver;

   public function __construct(
    Environment $twig,
    mysqli $db
) {
    $this->twig = $twig;
    $this->db   = $db;

    $this->pageRepository = CMSApp::getPageRepository();
    $this->roleService    = CMSApp::getRoleService();
    $this->accessResolver = CMSApp::getAccessResolver();
    
}

    public function handle(string $language, ?string $slug): void
    {
        // Guard: kein Slug übergeben → 404
        if ($slug === null) {
            http_response_code(404);
            echo '404 – Seite nicht gefunden';
            return;
        }
        // 1) Resolver nutzen (sauber)
        $uri = $_SERVER['REQUEST_URI'];

        try {
            $resolved = PageResolver::resolve($this->db, $uri);
        } catch (\Throwable $e) {
            http_response_code(404);
            echo '404 – Seite nicht gefunden';
            return;
        }

        $row = $resolved['meta'];

        // 1.5) Login-Guard: eingeloggte User nicht auf Login-Seite lassen
        $roleId = $this->roleService->getCurrentRoleId();

        if ($slug === 'login' && $roleId !== 'guest-role-000') {
            $redirect = $this->accessResolver->resolveStartPage(
                $roleId,
                $language
            );

            header('Location: ' . $redirect);
            exit;
        }

        $pageId = $row['page_uuid'];

        // 2) Delegation – ALLE Guards passieren dort
        error_log('PAGE CONTROLLER: ' . $pageId);
        echo CMSAppFrontend::render(
            $this->twig,
            $pageId
        );
    }
}