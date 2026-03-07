<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\CMSApp;
use CMS\Application\Repository\PageRepository;
use Twig\Environment;
use mysqli;
use CMS\Application\Service\RoleService;
use CMS\Security\AccessResolver;

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

    public function handle(string $language, string $slug): void
    {
        // 1) Page-Metadaten holen
        $stmt = $this->db->prepare(
            "SELECT page_uuid, context, nav_id
             FROM page
             WHERE slug = ?
               AND enabled = 1
             LIMIT 1"
        );
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$row = $res->fetch_assoc()) {
            http_response_code(404);
            echo '404 – Seite nicht gefunden';
            return;
        }

        $stmt->close();

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
        $area   = ($row['context'] === 'backend') ? 'backend' : 'frontend';
        $navId  = $row['nav_id'] ?: 'generalNav';

        // 2) Delegation – ALLE Guards passieren dort
error_log('PAGE CONTROLLER: ' . $pageId);
        echo \CMS\Core\CMSAppFrontend::render(
            $this->twig,
            $pageId
        );
    }
}