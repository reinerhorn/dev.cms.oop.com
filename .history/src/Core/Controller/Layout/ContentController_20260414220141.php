<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Application\Service\ButtonService;
use CMS\Application\FormData\FormDataLoaderResolver;

class ContentController
{

    private mysqli $db;
    private string $language;

    public function __construct(mysqli $db, string $language)
    {
        $this->db = $db;
        $this->language = strtolower(substr($language, 0, 2));
    }

    /**
     * Liefert strukturierte Content-Daten (Blocks) für eine Seite
     */
    public function getContent(?string $pageId): array
    {
        if (!$pageId) {
            return [
                'content_data' => [[
                    'type'     => 'plaintext',
                    'headline' => 'Fehler: Seite unbekannt',
                    'text'     => '',
                    'lang'     => $this->language,
                ]]
            ];
        }

        $contentBlocks = $this->loadPluginsForPage($pageId);

        return [
            'content_data' => $contentBlocks
        ];
    }

    /**
     * Wrapper für getContent, falls benötigt
     */
    public function getPageContent(?string $pageId): array
    {
        return $this->getContent($pageId);
    }
private function loadPluginsForPage(string $pageId): array
{
    $stmt = $this->db->prepare("
        SELECT 
            pc.idx,
            pc.plugin_content_uuid,
            p.name AS plugin_name,
            p.table_name,
            p.handler_class,
            p.module
        FROM page_config pc
        JOIN plugin p 
            ON pc.fk_plugin_uuid = p.plugin_uuid
        WHERE pc.fk_page_uuid = ?
          AND p.is_active = 1
        ORDER BY pc.idx ASC
    ");

    $stmt->bind_param('s', $pageId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $blocks = [];

    foreach ($rows as $row) {

        $pluginName = strtolower(trim((string)($row['plugin_name'] ?? '')));
        $uuid       = $row['plugin_content_uuid'] ?? null;
        $idx        = (int)($row['idx'] ?? 0);

        if (!$uuid) {
            continue;
        }

        /**
         * 🔵 PURE DATA BLOCK
         * Kein Handler, kein FormData, keine Logik
         */
        $blocks[] = [
            'plugin_name'   => $pluginName,
            'uuid'          => $uuid,
            'idx'           => $idx,
            'handler_class' => $row['handler_class'] ?? null,
            'module'        => $row['module'] ?? null,
            'table_name'    => $row['table_name'] ?? null,
            'type'          => $pluginName,
        ];
    }

    usort($blocks, fn($a, $b) => ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0));

    return $blocks;
}


}
