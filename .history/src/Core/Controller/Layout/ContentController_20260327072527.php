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
     * Wrapper für CMSAppFrontend
     */
    public function getPageContent(?string $pageId): array
    {
        return $this->getContent($pageId);
    }

    /**
     * Liefert strukturierte Content-Daten (Blocks)
     */
    public function getContent(?string $pageId): array
    {
        error_log('>>> CONTENT CONTROLLER HIT: ' . $pageId);
        error_log('>>> LANGUAGE: ' . $this->language);
        if (!$pageId) {
            return [
                'content_data' => [[
                    'type'     => 'plaintext',
                    'headline' => 'Fehler: Seite unbekannt',
                    'text'     => '',
                    'lang'     => $this->language,
                    'uuid'     => null,
                ]]
            ];
        }

        error_log('>>> CONTENT DATA COUNT (PRE RETURN): ' . count($this->loadPluginsForPage($pageId)));

        return [
            'content_data' => $this->loadPluginsForPage($pageId)
        ];
    }

    /**
     * Lädt Plugins aus page_config
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
                p.module,
                pc.config
            FROM page_config pc
            JOIN plugin p 
                ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ?
              AND p.is_active = 1
            ORDER BY pc.idx ASC
        ");
        if (!$stmt) {
            error_log("PREPARE ERROR: " . $this->db->error);
            return [];
        }
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        error_log('PAGE CONFIG ROWS: ' . print_r($rows, true));
        error_log('PAGE CONFIG ROW COUNT: ' . count($rows));
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $handlerClass = trim($row['handler_class'] ?? '');
            $table  = trim($row['table_name'] ?? '');
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);
            $module = strtolower(trim($row['module'] ?? ''));

            $configRaw = $row['config'] ?? null;
            $config = [];
            if (!empty($configRaw)) {
                $decoded = json_decode($configRaw, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }

            // --------------------------------------------
            // SQL Content Plugins (zuerst behandeln!)
            // --------------------------------------------
            if ($table === 'p_content_plaintext') {

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

                if ($data) {
                    $blocks[] = [
                        'type'     => 'plaintext',
                        'idx'      => $idx,
                        'headline' => $data['headline'] ?? '',
                        'text'     => $data['text'] ?? '',
                        'lang'     => $data['fk_language_id'] ?? $this->language,
                        'uuid'     => $uuid,
                    ];
                }

                continue;
            }

            // --------------------------------------------
            // Dynamische Plugin-Klassen-Auflösung (sauber mit module!)
            // --------------------------------------------

            $className = null;

            if (!empty($handlerClass)) {
                $paths  = [];

                // 🔥 1. PRIORITÄT: Application mit Module (z. B. Auth, Admin, Shop)
                if (!empty($module)) {
                    $paths[] = '\\CMS\\Application\\FormAction\\' . ucfirst($module) . '\\' . $handlerClass;
                }

                // 🔥 2. Allgemeine Fallbacks
                $paths[] = '\\CMS\\Plugin\\' . $handlerClass;
                $paths[] = '\\CMS\\Core\\Controller\\Admin\\' . $handlerClass;

                foreach ($paths as $tryClass) {
                    if (class_exists($tryClass)) {
                        $className = $tryClass;
                        error_log('HANDLER CLASS RESOLVED: ' . $className);
                        break;
                    }
                }
            }

            // 🔥 FINALER FALLBACK (nur wenn wirklich nichts gefunden)
            if (!$className) {
                $className = '\\CMS\\Plugin\\Plugin' . str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin)));
                error_log('FALLBACK PLUGIN USED: ' . $className);
            }

            $block = [];
            if (class_exists($className)) {

                if (method_exists($className, 'loadContentByLanguage')) {
                    $block = $className::loadContentByLanguage($this->db, $uuid, $this->language);
                } elseif (method_exists($className, 'loadByUuid')) {
                    $block = $className::loadByUuid($this->db, $uuid, $this->language);
                }

                // Sicherstellen: Basisdaten immer vorhanden
                if (!empty($block)) {
                    $block['type'] = $plugin;
                    $block['uuid'] = $uuid;
                    $block['config'] = array_merge(
                        $block['config'] ?? [],
                        $config
                    );

                    // Für Admin/Backend (module admin) oder andere Module außer auth keine Buttons laden
                    if (in_array($module, ['admin']) || ($module !== '' && $module !== 'auth')) {
                        $block['buttons'] = [];
                    }
                }

                $block['idx'] = $idx;
                $blocks[] = $block;
                continue;
            }

            // Fallback: generisches Template-Rendering, falls kein Handler gefunden
            $blocks[] = [
                'type'     => $plugin,
                'idx'      => $idx,
                'uuid'     => $uuid,
                'template' => "pages/{$plugin}.twig",
                'config'   => $config,
                'buttons'  => [],
            ];
        }

        error_log('BLOCKS COUNT FINAL: ' . count($blocks));

        return $blocks;
    }
}
