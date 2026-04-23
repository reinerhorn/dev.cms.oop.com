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

    /**
     * Lädt alle Plugins für eine Seite und gibt die Content-Daten zurück
     */
    private function loadPluginsForPage(string $pageId): array
    {
        $rows = $this->fetchPluginRows($pageId);
        $blocks = [];

        foreach ($rows as $row) {
            $data = $this->mapRow($row);

            if (!$this->isValidPlugin($data)) {
                continue;
            }

            $handlerClass = $this->buildHandlerClass($data['module'], $data['handlerRaw']);

            $block = $this->executeHandler($handlerClass, $data['uuid']);

            // 🔥 REQUEST holen (GET + POST kombinieren)
            $request = array_merge($_GET, $_POST);

            $block = $this->processBlock($block, $request);
            $block = $this->attachButtons($block);

            if (!empty($block)) {
                $block = $this->finalizeBlock(
                    $block,
                    $data['idx'],
                    $data['pluginName'],
                    $data['module']
                );

                $blocks[] = $block;
            } else {
                error_log("Plugin '{$data['pluginName']}' returned empty content for UUID '{$data['uuid']}'");
            }
        }

        return $this->sortBlocks($blocks);
    }

    private function fetchPluginRows(string $pageId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                pc.idx,
                pc.plugin_content_uuid,
                p.name AS plugin_name,
                p.table_name,
                p.handler_class,
                p.module,
                p.type 
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

        return $rows;
    }

    private function mapRow(array $row): array
    {
        return [
            'pluginName' => strtolower(trim((string)($row['plugin_name'] ?? ''))),
            'uuid'       => $row['plugin_content_uuid'] ?? null,
            'idx'        => (int)($row['idx'] ?? 0),
            'handlerRaw' => trim((string)($row['handler_class'] ?? '')),
            'module'     => strtolower(trim((string)($row['module'] ?? ''))),
            'type'       => $row['type'] ?? 'FormData' 
        ];
    }

    private function isValidPlugin(array $data): bool
    {
        if (!$data['handlerRaw']) return false;
        if (str_contains($data['handlerRaw'], '\\')) return false;
        if ($data['module'] === '') return false;

        return true;
    }

   private function buildHandlerClass(array $row): string
{
    $base = 'CMS\\Application\\';

    $type    = $row['type'];     // z.B. FormAction
    $module  = $row['module'];   // z.B. auth
    $handler = $row['handler'];  // z.B. login

    return $base
        . $type . '\\'
        . ucfirst($module) . '\\'
        . ucfirst($handler) . 'Handler';
}

    private function executeHandler(string $handlerClass, ?string $uuid): ?array
    {
        if (!$uuid || !class_exists($handlerClass)) {
            error_log("Handler NOT FOUND: {$handlerClass}");
            return null;
        }

        try {
            $instance = new $handlerClass();
        } catch (\Throwable $e) {
            error_log("Handler instantiation failed: {$handlerClass} - " . $e->getMessage());
            return null;
        }

        if (method_exists($instance, 'loadContentByLanguage')) {
            return $instance->loadContentByLanguage($this->db, $uuid, $this->language);
        }

        if (method_exists($instance, 'loadByUuid')) {
            return $instance->loadByUuid($this->db, $uuid, $this->language);
        }

        error_log("No valid loader method in handler: {$handlerClass}");
        return null;
    }

    private function attachButtons(array $block): array
    {
        $formId = $block['form_id'] ?? null;

        if (!empty($block) && $formId) {
            $block['buttons'] = ButtonService::getByFormId($this->db, $formId);
        }

        return $block;
    }

    private function finalizeBlock(array $block, int $idx, string $pluginName, string $module): array
    {
        $block['idx'] = $idx;

        if (!isset($block['type']) || $block['type'] === '') {
            $block['type'] = preg_replace('/^p_content_/', '', $pluginName ?: $module);
        }

        return $block;
    }

    private function sortBlocks(array $blocks): array
    {
        usort($blocks, fn($a, $b) => ($a['idx'] ?? 0) <=> ($b['idx'] ?? 0));
        return $blocks;
    }

    /**
     * Verarbeitet einen Block (Hydration + FormId-Ermittlung)
     */
    private function processBlock(array $block, array $request): array
    {
        if (empty($block)) {
            return $block;
        }

        // Hydration
        $block = $this->hydrateBlock($block, $request);

        // FormId robust extrahieren (vor UND nach Hydration absichern)
        $formId = null;

        if (isset($block['config']['form_id'])) {
            $formId = $block['config']['form_id'];
        } elseif (isset($block['config']['form']['id'])) {
            $formId = $block['config']['form']['id'];
        } elseif (isset($block['form_id'])) {
            $formId = $block['form_id'];
        }

        if ($formId) {
            $block['form_id'] = $formId;
        } else {
            error_log('⚠️ NO FORM_ID FOUND AFTER HYDRATION');
        }

        return $block;
    }

    /**
     * Führt die Hydration eines Blocks über den passenden FormDataLoader aus
     */
    private function hydrateBlock(array $block, array $request): array
    {
        if (empty($block) || !isset($block['config'])) {
            return $block;
        }

        $formAction = $block['config']['form_action'] ?? 'entity';

        $loader = FormDataLoaderResolver::resolve($formAction);

        if ($loader) {
            try {
                return $loader->hydrate($block, $request);
            } catch (\Throwable $e) {
                error_log('HYDRATE ERROR: ' . $e->getMessage());
            }
        }

        return $block;
    }
}
