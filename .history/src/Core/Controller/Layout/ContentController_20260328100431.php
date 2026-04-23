<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Plugin\PluginFormular;

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

    /**
     * Lädt alle Plugins für eine Seite und gibt die Content-Daten zurück
     */
    private function loadPluginsForPage(string $pageId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                pc.idx,
                pc.plugin_content_uuid,
                p.name AS plugin_name,
                p.table_name,
                p.handler_class
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
            $pluginName = strtolower(trim($row['plugin_name'] ?? ''));
            $uuid = $row['plugin_content_uuid'] ?? null;
            $idx  = (int)($row['idx'] ?? 0);
            $handlerClass = $row['handler_class'] ?? null;

            if (!$pluginName || !$uuid || !$handlerClass) {
                error_log("Skipping plugin block due to missing data (plugin: '{$pluginName}', uuid: '{$uuid}', handler: '{$handlerClass}')");
                continue;
            }

            if (!class_exists($handlerClass)) {
                error_log("Handler class '{$handlerClass}' not found for plugin '{$pluginName}'");
                continue;
            }

            $block = null;

            $instance = new $handlerClass();

            if (method_exists($instance, 'loadContentByLanguage')) {
                $block = $instance->loadContentByLanguage($this->db, $uuid, $this->language);
            } elseif (method_exists($instance, 'loadByUuid')) {
                $block = $instance->loadByUuid($this->db, $uuid, $this->language);
            }

            if (!empty($block)) {
                $block['idx'] = $idx;
                $blocks[] = $block;
            } else {
                error_log("Plugin '{$pluginName}' returned empty content for UUID '{$uuid}'");
            }
        }

        usort($blocks, function ($a, $b) {
            return ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0);
        });

        return $blocks;
    }
}
