<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Application\Service\ButtonService;
use CMS\Application\FormData\FormDataLoaderResolver;

class ContentController1
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

        error_log('PAGE SLUG: startseite');
        error_log('PAGE ID: ' . $pageId);

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

        $table = $row['table_name'] ?? null;

        if (!$table) {
            continue;
        }

        // 🔥 DIREKTES LADEN DES JSON CONFIGS (KEIN HANDLER NOTWENDIG)
        $stmt2 = $this->db->prepare("
            SELECT config_json
            FROM `$table`
            WHERE plugin_content_uuid = ?
            LIMIT 1
        ");

        $stmt2->bind_param('s', $uuid);
        $stmt2->execute();
        $result = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if (!$result) {
            error_log("NO DATA FOUND IN TABLE: {$table} FOR UUID: {$uuid}");
            continue;
        }

        $config = json_decode($result['config_json'] ?? '{}', true);

        if (!is_array($config)) {
            error_log("INVALID JSON IN TABLE: {$table} FOR UUID: {$uuid}");
            continue;
        }

        $block = [
            'type'   => $pluginName,
            'config' => $config,
            'idx'    => $idx,
        ];

        $blocks[] = $block;
        continue;
    }

    usort($blocks, fn($a, $b) => ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0));

    return $blocks;
}


}
