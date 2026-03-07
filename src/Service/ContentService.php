<?php
namespace CMS\Service;

use CMS\Core\CMSApp;
use mysqli;

class ContentService
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = CMSApp::getDb();
    }

    /**
     * Holt die Inhalte für eine bestimmte Seite (über page_config + plaintext).
     */
    public function getPageContent(string $pageId, string $language): array
    {
        $contents = [];

        $stmt = $this->db->prepare("
            SELECT pc.page_config_uuid,
                   pc.content_label,
                   pcp.headline,
                   pcp.text
            FROM page_config pc
            LEFT JOIN p_content_plaintext pcp 
                   ON pc.plugin_content_uuid = pcp.id
            WHERE pc.fk_page_uuid = ?
        ");
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $contents[] = [
                'headline' => $row['headline'] ?? '',
                'text'     => $row['text'] ?? '',
                'label'    => $row['content_label'] ?? ''
            ];
        }

        return $contents;
    }
}