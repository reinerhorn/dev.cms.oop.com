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

            $block = [
                'type' => $plugin,
                'uuid' => $uuid,
                'idx' => $idx,
                'config' => $config,
            ];

            $className = null;

            if (!empty($handlerClass)) {
                $paths  = [];

                // 🔥 1. PRIORITÄT: Core Controller mit Module
                if (!empty($module)) {
                    $paths[] = '\\CMS\\Core\\Controller\\' . ucfirst($module) . '\\' . $handlerClass;
                }

                // 🔥 2. Application FormAction mit Module
                if (!empty($module)) {
                    $paths[] = '\\CMS\\Application\\FormAction\\' . ucfirst($module) . '\\' . $handlerClass;
                }

                foreach ($paths as $tryClass) {
                    if (class_exists($tryClass)) {
                        $className = $tryClass;
                        error_log('HANDLER CLASS RESOLVED: ' . $className);
                        break;
                    }
                }
            }

            if ($className && class_exists($className)) {
                $contentData = [];

                if (method_exists($className, 'loadContentByLanguage')) {
                    $contentData = $className::loadContentByLanguage($this->db, $uuid, $this->language);
                } elseif (method_exists($className, 'loadByUuid')) {
                    $contentData = $className::loadByUuid($this->db, $uuid, $this->language);
                }

                if (is_array($contentData) && !empty($contentData)) {
                    $block = array_merge($block, $contentData);

                    // Datengetriebene Konfigurationszusammenführung
                    if (!isset($block['config'])) {
                        $block['config'] = $config;
                    } elseif (is_array($block['config']) && is_array($config)) {
                        $block['config'] = $this->arrayMergeRecursiveDistinct($block['config'], $config);
                    } else {
                        $block['config'] = $config;
                    }

                    // Für Admin/Backend (module admin) oder andere Module außer auth keine Buttons laden
                    if (in_array($module, ['admin']) || ($module !== '' && $module !== 'auth')) {
                        $block['buttons'] = [];
                    }
                }
            } else {
                error_log('NO HANDLER CLASS FOUND FOR PLUGIN: ' . $plugin . ' (UUID: ' . $uuid . ')');
                // Fallback-Block für unbekannte Plugins
                $block['type'] = 'unknown_plugin';
            }

            $blocks[] = $block;
        }

        error_log('BLOCKS COUNT FINAL: ' . count($blocks));

        // 🔥 Fallback, falls keine Blöcke vorhanden
        if (empty($blocks)) {
            $blocks[] = [
                'type' => 'page_placeholder',
                'uuid' => null,
                'idx' => 0,
                'headline' => 'Startseite',
                'text' => '',
                'config' => [],
            ];
        }

        return $blocks;
    }

    /**
     * Rekursive Zusammenführung von Arrays mit Priorität für die Werte aus $array2
     */
    private function arrayMergeRecursiveDistinct(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
