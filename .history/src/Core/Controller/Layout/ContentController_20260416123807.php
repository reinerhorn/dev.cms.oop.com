<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Application\Service\ButtonService;
use CMS\Application\FormData\DataFormLoaderResolver;

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
        error_log('PLUGIN ROWS: ' . print_r($rows, true));
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $pluginName  = strtolower(trim((string)($row['plugin_name'] ?? '')));
            $uuid        = $row['plugin_content_uuid'] ?? null;
            $idx         = (int)($row['idx'] ?? 0);
            $handlerRaw  = trim((string)($row['handler_class'] ?? ''));
            $module      = strtolower(trim((string)($row['module'] ?? '')));

            if (!$handlerRaw) {
                error_log("❌ Kein handler_class gesetzt für Plugin '{$pluginName}'");
                continue;
            }

            // STRICT MODE: Kein FULL CLASS erlaubt für FormAction
            if (str_contains($handlerRaw, '\\')) {
                error_log("❌ FULL CLASS nicht erlaubt (plugin: {$pluginName}, handler: {$handlerRaw})");
                continue;
            }

            if ($module === '') {
                error_log("❌ Kein module gesetzt (plugin: {$pluginName}, handler: {$handlerRaw})");
                continue;
            }

            $handlerClass = 'CMS\\Application\\FormAction\\'
                . ucfirst($module)
                . '\\'
                . $handlerRaw;

            error_log("BUILT HANDLER: module={$module} handler={$handlerRaw} class={$handlerClass}");

            if (!$pluginName || !$uuid || !$handlerClass) {
                error_log("Skipping plugin block due to missing data (plugin: '{$pluginName}', uuid: '{$uuid}', handler: '{$handlerClass}')");
                continue;
            }

            if (!class_exists($handlerClass)) {
                error_log("Handler NOT FOUND: plugin={$pluginName} module={$module} class={$handlerClass}");
                continue;
            }

            $block = null;

            try {
                $instance = new $handlerClass();
            } catch (\Throwable $e) {
                error_log("❌ Handler instantiation failed: {$handlerClass} - " . $e->getMessage());
                continue;
            }

            if (method_exists($instance, 'loadContentByLanguage')) {
                $block = $instance->loadContentByLanguage($this->db, $uuid, $this->language);
            } elseif (method_exists($instance, 'loadByUuid')) {
                $block = $instance->loadByUuid($this->db, $uuid, $this->language);
            } else {
                error_log("❌ No valid loader method in handler: {$handlerClass}");
            }
            // 🔥 REQUEST holen (GET + POST kombinieren)
            $request = array_merge($_GET, $_POST);

            // 🔥 FormDataLoader anwenden (Hydration)
            if (!empty($block) && isset($block['config'])) {
                $formAction = $block['config']['form_action'] ?? 'entity';

                $loader = DataFromLoaderResolver::resolve($formAction);

                if ($loader) {
                    $block = $loader->hydrate($block, $request);
                }
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
                if (!isset($block['type']) || $block['type'] === '') {
                    // Entfernt Präfix wie "p_content_" für Twig
                    $block['type'] = preg_replace('/^p_content_/', '', $pluginName ?: $module);
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
