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

    // Wrapper für Seiteninhalt
    public function getPageContent(?string $pageId): array
    {
        return $this->getContent($pageId);
    }

    // Liefert Content-Blöcke der Seite
    public function getContent(?string $pageId): array
    {
        error_log('>>> CONTENT CONTROLLER HIT: ' . $pageId);
        error_log('>>> LANGUAGE: ' . $this->language);

        if (!$pageId) {
            return [
                'content_data' => [[
                    'type' => 'plaintext',
                    'headline' => 'Fehler: Seite unbekannt',
                    'text' => '',
                    'lang' => $this->language,
                    'uuid' => null,
                ]]
            ];
        }

        $blocks = $this->loadPluginsForPage($pageId);
        $blocks = $this->prependStartpageBaseBlock($pageId, $blocks);

        error_log('>>> CONTENT DATA COUNT (PRE RETURN): ' . count($blocks));

        return ['content_data' => $blocks];
    }

    // Fügt Startseiten-Grundgerüst hinzu, falls nötig
    private function prependStartpageBaseBlock(string $pageId, array $blocks): array
    {
        $startpageUuid = '73a6be54-7bf6-11f0-be30-c26cf33f6ee6';
        if ($pageId !== $startpageUuid) {
            return $blocks;
        }

        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'grundgeruest') {
                return $blocks;
            }
        }

        $baseBlock = [
            'type' => 'grundgeruest',
            'uuid' => null,
            'idx' => 0,
            'headline' => 'Willkommen auf der Startseite!',
            'text' => 'Dies ist das statische Grundgerüst der Startseite.',
            'config' => [],
        ];

        array_unshift($blocks, $baseBlock);
        return $blocks;
    }

    // Lädt Plugin-Daten aus DB und ruft Handler auf
    private function loadPluginsForPage(string $pageId): array
    {
        $stmt = $this->db->prepare("
            SELECT pc.idx, pc.plugin_content_uuid, p.name AS plugin_name, p.handler_class, pc.config
            FROM page_config pc
            JOIN plugin p ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ? AND p.is_active = 1
            ORDER BY pc.idx ASC
        ");

        if (!$stmt) {
            error_log("PREPARE ERROR: " . $this->db->error);
            return [];
        }

        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        error_log('PAGE CONFIG ROW COUNT: ' . count($rows));
        error_log('PAGE CONFIG ROWS: ' . print_r($rows, true));

        $blocks = [];
        foreach ($rows as $row) {
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $handlerClass = trim($row['handler_class'] ?? '');
            $uuid = $row['plugin_content_uuid'] ?? null;
            $idx = (int)($row['idx'] ?? 0);
            $config = json_decode($row['config'] ?? '[]', true) ?: [];

            $block = ['type' => $plugin, 'uuid' => $uuid, 'idx' => $idx, 'config' => $config];

            if ($handlerClass && class_exists($handlerClass)) {
                try {
                    $contentData = [];
                    if (method_exists($handlerClass, 'loadContentByLanguage')) {
                        $contentData = $handlerClass::loadContentByLanguage($this->db, $uuid, $this->language);
                    } elseif (method_exists($handlerClass, 'loadByUuid')) {
                        $contentData = $handlerClass::loadByUuid($this->db, $uuid, $this->language);
                    }

                    if (is_array($contentData) && $contentData) {
                        $block = array_merge($block, $contentData);
                        if (isset($block['config']) && is_array($block['config'])) {
                            $block['config'] = $this->arrayMergeRecursiveDistinct($block['config'], $config);
                        } else {
                            $block['config'] = $config;
                        }
                    }
                } catch (Throwable $e) {
                    error_log("PLUGIN ERROR [$plugin][$uuid]: " . $e->getMessage());
                    $block = ['type' => 'plugin_error', 'error' => 'Fehler beim Laden des Plugins: ' . $plugin];
                }
            } else {
                error_log("NO HANDLER FOR PLUGIN: $plugin ($uuid)");
                $block['type'] = 'unknown_plugin';
            }

            $blocks[] = $block;
        }

        error_log('BLOCKS COUNT FINAL: ' . count($blocks));
        return $blocks;
    }

    // Rekursive Zusammenführung, $array2 überschreibt $array1
    private function arrayMergeRecursiveDistinct(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursiveDistinct($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }
}
