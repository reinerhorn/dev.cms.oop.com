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
                pc.config,
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
        error_log('PAGE CONFIG ROWS: ' . print_r($rows, true));
        error_log('PAGE CONFIG ROW COUNT: ' . count($rows));
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $block = null;
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $handlerClass = trim($row['handler_class'] ?? '');
            $table  = trim($row['table_name'] ?? '');
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);

            $configRaw = $row['config'] ?? null;
            error_log('CONFIG RAW TYPE: ' . gettype($configRaw));
            error_log('CONFIG RAW VALUE: ' . print_r($configRaw, true));
            $config = [];

            if (!empty($configRaw)) {
                $decoded = json_decode($configRaw, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }

            // --------------------------------------------
            // 0) Handler-Class Resolver (Multi-Path)
            // --------------------------------------------
            if (!empty($handlerClass) && !empty($uuid)) {

                $possiblePaths = [
                    'CMS\Core\Controller\Admin',
                    'CMS\Core\Controller\layout',

                    'CMS\Plugin',
                    'CMS\Application\FormAction\Auth',
                    'CMS\Application\FormAction',
                    'CMS\Application',
                    'CMS\Repository',
                ];

                $resolvedClass = null;

                foreach ($possiblePaths as $path) {
                    $class = $path . $handlerClass;

                    if (class_exists($class) && method_exists($class, 'loadContentByLanguage')) {
                        $resolvedClass = $class;
                        break;
                    }
                }

                if ($resolvedClass) {

                    error_log('HANDLER CLASS USED (RESOLVED): ' . $resolvedClass);

                    $block = $resolvedClass::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );

                    if (!is_array($block)) {
                        error_log('BLOCK IS NOT ARRAY: ' . print_r($block, true));
                        continue;
                    }

                    if (!empty($block)) {

                        if (!empty($config)) {
                            $existingConfig = [];
                            if (isset($block['config']) && is_array($block['config'])) {
                                $existingConfig = $block['config'];
                            }
                            $block['config'] = array_merge($existingConfig, $config);
                        }

                        $block['idx'] = $idx;
                        $blocks[] = $block;
                        continue;
                    }
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
                    ];
                }

                continue;
            }


            // --------------------------------------------
            // Dynamische Plugin-Klassen-Auflösung
            // --------------------------------------------

            $className = null;

            // 1. Bevorzugt: handler_class aus DB verwenden
            if (!empty($handlerClass)) {

                // Versuche Plugin-Namespace
                $tryPlugin = '\\CMS\\Plugin\\' . $handlerClass;

                // Versuche Controller-Namespace

                $tryController = '\\CMS\\Core\\Controller\\Admin\\' . $handlerClass;

                if (class_exists($tryPlugin)) {
                    $className = $tryPlugin;
                } elseif (class_exists($tryController)) {
                    $className = $tryController;
                }
            }

            // 2. Fallback: alter Plugin-Name-Mechanismus
            if (!$className) {
                $className = '\\CMS\\Plugin\\Plugin' . str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin)));
            }

            if (class_exists($className)) {

                $block = [];

                if (method_exists($className, 'loadContentByLanguage')) {
                    $block = $className::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                } elseif (method_exists($className, 'loadByUuid')) {
                    $block = $className::loadByUuid(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                }

                if (!empty($block)) {

                    if (!empty($config)) {
                        $existingConfig = [];
                        if (isset($block['config']) && is_array($block['config'])) {
                            $existingConfig = $block['config'];
                        }
                        $block['config'] = array_merge($existingConfig, $config);
                    }

                    if (isset($block['config'])) {
                        error_log('CONFIG RAW (merged): ' . print_r($block['config'], true));
                    }

                    $block['idx'] = $idx;
                    $blocks[] = $block;
                    continue;
                }
            }

            // Fallback: generisches Template-Rendering
            // --- DEBUG: Unknown / Unhandled Plugin ---
            error_log(
                'UNKNOWN PLUGIN HANDLER: name=' . $plugin .
                    ' | table=' . $table .
                    ' | uuid=' . ($uuid ?? 'NULL')
            );
            // --- END DEBUG ---
            $blocks[] = [
                'type'     => $plugin,
                'idx'      => $idx,
                'uuid'     => $uuid,
                'template' => "pages/{$plugin}.twig",
            ];
        }

        error_log('BLOCKS COUNT FINAL: ' . count($blocks));

        return $blocks;
    }
}
