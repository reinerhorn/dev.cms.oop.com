<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;

class ContentController
{
    private mysqli $db;
    private string $language;

    public function __construct(mysqli $db, string $language)
    {
        $this->db = $db;
        $this->language = strtolower(substr($language, 0, 2));
    }

    public function getContent(?string $pageId): array
    {
        if (!$pageId) {
            return [
                'content_data' => [[
                    'plugin_key' => 'plaintext',
                    'idx' => 0,
                    'data' => [
                        'headline' => 'Fehler: Seite unbekannt',
                        'text' => '',
                        'lang' => $this->language,
                    ]
                ]]
            ];
        }

        return [
            'content_data' => $this->loadPluginsForPage($pageId)
        ];
    }

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
                p.plugin_key,
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

            $pluginKey = strtolower(trim($row['plugin_key'] ?? ''));
            $uuid = $row['plugin_content_uuid'] ?? null;
            $idx = (int)($row['idx'] ?? 0);

            if (!$uuid) {
                error_log("Missing UUID for plugin: {$pluginKey}");
                continue;
            }

            $module = ucfirst(strtolower(trim($row['module'] ?? '')));
            $class  = trim($row['handler_class'] ?? '');

            $className = "\\CMS\\Plugin\\{$module}\\{$class}";

            if (!$class || !class_exists($className)) {
                error_log("Missing plugin class: {$className}");
                continue;
            }

            $block = null;

            if (method_exists($className, 'loadContentByLanguage')) {
                $block = $className::loadContentByLanguage(
                    $this->db,
                    $uuid,
                    $this->language
                );
            } elseif (method_exists($className, 'loadByUuid')) {
                $block = $className::loadByUuid(
                    $this->db,
                    $uuid,
                    $this->language
                );
            }

            if (!$block) {
                error_log("Plugin returned empty block: {$pluginKey}");
                continue;
            }

            // ✔ nur Meta-Daten setzen (KEINE Business-Logik!)
            $block['plugin_key'] = $block['plugin_key'] ?? $pluginKey;
            $block['idx'] = $block['idx'] ?? $idx;

            $blocks[] = $block;
        }

        usort($blocks, fn($a, $b) => ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0));

        return $blocks;
    }
}
