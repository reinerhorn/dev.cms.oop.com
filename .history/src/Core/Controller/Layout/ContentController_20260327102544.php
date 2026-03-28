<?php

declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use Throwable;

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

        $blocks = $this->loadPluginsForPage($pageId);

        // Startseite immer Grundgerüst-Block liefern
        if ($pageId === '73a6be54-7bf6-11f0-be30-c26cf33f6ee6') {
            $grundgeruestBlock = [
                'type' => 'grundgeruest',
                'uuid' => null,
                'idx' => 0,
                'headline' => 'Willkommen auf der Startseite!',
                'text' => 'Dies ist das statische Grundgerüst der Startseite.',
                'config' => [],
            ];
            // Insert at beginning if not already present
            $found = false;
            foreach ($blocks as $block) {
                if (($block['type'] ?? '') === 'grundgeruest') {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($blocks, $grundgeruestBlock);
            }
        }

        error_log('>>> CONTENT DATA COUNT (PRE RETURN): ' . count($blocks));

        return [
            'content_data' => $blocks
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
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);

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

            if (!empty($handlerClass) && class_exists($handlerClass)) {
                try {
                    $contentData = [];

                    if (method_exists($handlerClass, 'loadContentByLanguage')) {
                        $contentData = $handlerClass::loadContentByLanguage($this->db, $uuid, $this->language);
                    } elseif (method_exists($handlerClass, 'loadByUuid')) {
                        $contentData = $handlerClass::loadByUuid($this->db, $uuid, $this->language);
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
                    }
                } catch (Throwable $e) {
                    error_log('PLUGIN HANDLER ERROR for plugin ' . $plugin . ' (UUID: ' . $uuid . '): ' . $e->getMessage());
                    $block['type'] = 'plugin_error';
                    $block['error'] = 'Fehler beim Laden des Plugins: ' . $plugin;
                }
            } else {
                error_log('NO HANDLER CLASS FOUND FOR PLUGIN: ' . $plugin . ' (UUID: ' . $uuid . ')');
                // Fallback-Block für unbekannte Plugins
                $block['type'] = 'unknown_plugin';
            }

            $blocks[] = $block;
        }

        error_log('BLOCKS COUNT FINAL: ' . count($blocks));

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
