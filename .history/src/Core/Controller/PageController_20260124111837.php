<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use Twig\Environment;
use mysqli;

class PageController
{
    private Environment $twig;
    private mysqli $db;
    private string $frontendClass;

    public function __construct(Environment $twig, mysqli $db, string $frontendClass)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->frontendClass = $frontendClass;
    }

    public function handle(string $language, string $slug): void
    {
        // 1) Page-ID holen (slug → UUID)
        $stmt = $this->db->prepare(
            "SELECT page_uuid, context FROM page WHERE slug = ? AND enabled = 1 LIMIT 1"
        );
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = $stmt->get_result();

        $pageId = null;
        $area = 'frontend';

        if ($row = $res->fetch_assoc()) {
            $pageId = $row['page_uuid'];
            $area = ($row['context'] === 'backend') ? 'backend' : 'frontend';
        }
        $stmt->close();

        if (!$pageId) {
            http_response_code(404);
            echo '404 – Seite nicht gefunden';
            return;
        }

        // 2) Delegation: Rendering + Guards anhand der Area (frontend / backend)
        $frontend = $this->frontendClass;

        echo $frontend::render(
            $this->twig,
            $pageId,
            $area
        );
    }
}