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
            // FORM BUTTON RESOLVER (Option B)
            // ------------------------------
            if (!empty($block) && $pluginName === 'forms') {

                $formId = $block['form_id'] ?? ($block['data']['form_id'] ?? null);

                if ($formId) {
                    $stmtBtn = $this->db->prepare("
                        SELECT ub.button_id, ub.label_key, ub.button_action, ub.button_type, ub.variant
                        FROM form_button fb
                        JOIN ui_button ub ON ub.button_id = fb.button_id
                        WHERE fb.form_id = ?
                        ORDER BY fb.sort_order ASC
                    ");

                    if ($stmtBtn) {
                        $stmtBtn->bind_param('s', $formId);
                        $stmtBtn->execute();
                        $btnRows = $stmtBtn->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmtBtn->close();

                        $resolvedButtons = [];

                        foreach ($btnRows as $btn) {
                            $resolvedButtons[] = [
                                'id'     => $btn['button_id'] ?? null,
                                'label'  => $btn['label_key'] ?? '',
                                'action' => $btn['button_action'] ?? '',
                                'type'   => $btn['button_type'] ?? 'button',
                                'variant'=> $btn['variant'] ?? 'primary',
                            ];
                        }

                        $block['buttons'] = $resolvedButtons;
                    }
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
