<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Application\Service\ButtonService;

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
                    'plugin_key' => 'plaintext',
                    'idx'        => 0,
                    'data'       => [
                        'headline' => 'Fehler: Seite unbekannt',
                        'text'     => '',
                        'lang'     => $this->language,
                    ]
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
                p.plugin_key,
                p.handler_class,
                p.module,
                p.table_name
            FROM page_config pc
            JOIN plugin p 
                ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ?
              AND p.is_active = 1
            ORDER BY pc.idx ASC;
  ");

        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $pluginName = strtolower(trim($row['plugin_key'] ??  ''));
            $uuid = $row['plugin_content_uuid'] ?? null;
            $idx = (int)($row['idx'] ?? 0);

            if (!$uuid) {
                error_log("Skipping plugin block due to missing UUID (plugin: '{$pluginName}')");
                continue;
            }

            // Dynamisch Plugin-Klasse ermitteln (Fallback)   

            $module  = ucfirst(strtolower(trim($row['module'] ?? '')));
            $class = trim($row['handler_class'] ?? '');

            $className = "\\CMS\\Plugin\\{$module}\\{$class}";

            if (!$class || !class_exists($className)) {
                error_log("Unknown plugin class '{$className}' for plugin '{$pluginName}', UUID '{$uuid}'");
                continue;
            }



            $block = null;
            if (method_exists($className, 'loadContentByLanguage')) {
                $block = $className::loadContentByLanguage($this->db, $uuid, $this->language);
            } elseif (method_exists($className, 'loadByUuid')) {
                $block = $className::loadByUuid($this->db, $uuid, $this->language);
            }

            // ------------------------------
            // FORM BUTTON RESOLVER
            // ------------------------------
            if (!empty($block) && $pluginName === 'forms') {

                $formId = $block['config']['form_id'] ?? null;

                error_log('FORM BUTTON RESOLVER FORM_ID: ' . ($formId ?? 'NULL'));

                if ($formId) {

                    $buttons = ButtonService::getByFormId(
                        $this->db,
                        $formId
                    );

                    error_log('FORM BUTTONS FOUND: ' . count($buttons));

                    $block['buttons'] = $buttons;
                }
            }

            if (!empty($block)) {
                $blocks[] = [
                    'plugin_key' => $pluginName,
                    'idx'        => $idx,
                    'data'       => $block
                ];
            } else {
                error_log("Plugin '{$pluginName}' content not found for UUID '{$uuid}'");
            }
        }

        usort($blocks, function ($a, $b) {
            return ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0);
        });

        return $blocks;
    }
}
