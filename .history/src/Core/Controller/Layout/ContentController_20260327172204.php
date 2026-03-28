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
                p.table_name
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
            $table = trim($row['table_name'] ?? '');
            $uuid = $row['plugin_content_uuid'] ?? null;
            $idx = (int)($row['idx'] ?? 0);

            if (!$table || !$uuid) {
                error_log("Skipping plugin block due to missing table or UUID (table: '{$table}', uuid: '{$uuid}')");
                continue;
            }

            switch ($table) {
                case 'p_content_plaintext':
                    $blocks[] = $this->loadPlaintextBlock($uuid, $idx);
                    break;

                case 'p_content_formular':
                    $formBlock = $this->loadFormularBlock($uuid, $idx, $pluginName);
                    if ($formBlock !== null) {
                        $blocks[] = $formBlock;
                    }
                    break;

                default:
                    $dynamicBlock = $this->loadDynamicPluginBlock($pluginName, $table, $uuid, $idx);
                    if ($dynamicBlock !== null) {
                        $blocks[] = $dynamicBlock;
                    }
                    break;
            }
        }

        return $blocks;
    }

    /**
     * Lädt einen Plaintext-Content-Block
     */
    private function loadPlaintextBlock(string $uuid, int $idx): array
    {
        $stmt = $this->db->prepare("
            SELECT headline, text, fk_language_id
            FROM p_content_plaintext
            WHERE id = ?
              AND fk_language_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $uuid, $this->language);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$data) {
            error_log("Plaintext content not found for UUID '{$uuid}' and language '{$this->language}'");
            return [
                'type'     => 'plaintext',
                'idx'      => $idx,
                'headline' => '',
                'text'     => '',
                'lang'     => $this->language,
            ];
        }

        return [
            'type'     => 'plaintext',
            'idx'      => $idx,
            'headline' => $data['headline'] ?? '',
            'text'     => $data['text'] ?? '',
            'lang'     => $data['fk_language_id'] ?? $this->language,
        ];
    }

    /**
     * Lädt einen Formular-Content-Block über PluginFormular
     */
    private function loadFormularBlock(string $uuid, int $idx, string $pluginName): ?array
    {
        if (!class_exists(PluginFormular::class)) {
            error_log("PluginFormular class not found while loading form block UUID '{$uuid}'");
            return null;
        }

        $block = PluginFormular::loadContentByLanguage($this->db, $uuid, $this->language);

        if (empty($block)) {
            error_log("Formular content not found for UUID '{$uuid}' and language '{$this->language}'");
            return null;
        }

        $formType = $block['type'] ?? null;
        $block['type'] = $pluginName;
        $block['form_type'] = $formType;
        $block['idx'] = $idx;

        $buttons = [];
        if (!empty($formType)) {
            $stmtBtn = $this->db->prepare("
                SELECT 
                    b.button_id,
                    b.label_key,
                    b.action,
                    b.button_type,
                    b.variant,
                    b.confirm_required
                FROM form_button fb
                JOIN ui_button b ON b.button_id = fb.button_id
                WHERE fb.form_id = ?
                  AND b.enabled = 1
                ORDER BY fb.sort_order ASC
            ");
            $stmtBtn->bind_param('s', $formType);
            $stmtBtn->execute();
            $buttons = $stmtBtn->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtBtn->close();
        }
        $block['buttons'] = $buttons;
        $block['form_id'] = $formType;

        return $block;
    }

    /**
     * Lädt einen dynamischen Plugin-Content-Block
     */
    private function loadDynamicPluginBlock(string $pluginName, string $table, string $uuid, int $idx): ?array
    {
        $className = '\\CMS\\Plugin\\Plugin' . str_replace(' ', '', ucwords(str_replace('_', ' ', $pluginName)));

        if (!class_exists($className)) {
            error_log("Unknown plugin class '{$className}' for plugin '{$pluginName}', table '{$table}', UUID '{$uuid}'");
            return [
                'type'     => $pluginName,
                'idx'      => $idx,
                'uuid'     => $uuid,
                'template' => "pages/{$pluginName}.twig",
            ];
        }

        $block = null;
        if (method_exists($className, 'loadContentByLanguage')) {
            $block = $className::loadContentByLanguage($this->db, $uuid, $this->language);
        } elseif (method_exists($className, 'loadByUuid')) {
            $block = $className::loadByUuid($this->db, $uuid, $this->language);
        }

        if (empty($block)) {
            error_log("Plugin '{$pluginName}' content not found for UUID '{$uuid}'");
            return [
                'type'     => $pluginName,
                'idx'      => $idx,
                'uuid'     => $uuid,
                'template' => "pages/{$pluginName}.twig",
            ];
        }

        if (isset($block['config'])) {
            error_log("Plugin '{$pluginName}' config loaded: " . print_r($block['config'], true));
        } else {
            error_log("Plugin '{$pluginName}' config not set");
        }

        $block['idx'] = $idx;

        return $block;
    }
}
