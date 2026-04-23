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
        // 🔥 PRG Pattern: POST → Redirect → GET
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // ⚠️ Handler muss vorher im System gesetzt sein
            if (isset($GLOBALS['handler'], $GLOBALS['pageMeta'])) {

                $handler   = $GLOBALS['handler'];
                $pageMeta  = $GLOBALS['pageMeta'];

                /** @var array $formResult */
                $formResult = $handler->handle($_POST, $pageMeta) ?? [];

                $_SESSION['form_result'] = $formResult;

                session_write_close();
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }
        }
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
        error_log('PLUGIN ROWS: ' . print_r($rows, true));
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
 
            $formId = $block['config']['form_id']
                ?? ($block['config']['form']['id'] ?? null)
                ?? $block['form_id']
                ?? null;

            error_log('FORM_ID DEBUG: ' . print_r($formId, true));
            error_log('BLOCK DEBUG: ' . print_r($block, true));
            /* 🔥 Buttons IMMER danach laden (robust: config oder flat Struktur) */

            if (!empty($block) && $formId) {
                $block['buttons'] = ButtonService::getByFormId($this->db, $formId);
            }

            if (!empty($block)) {
                $block['idx'] = $idx;

                // 🔥 WICHTIG: Typ setzen, falls Plugin es nicht liefert
                if (!isset($block['type'])) {
                    // Entfernt Präfix wie "p_content_" für Twig
                    $block['type'] = preg_replace('/^p_content_/', '', $pluginName);
                }

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
