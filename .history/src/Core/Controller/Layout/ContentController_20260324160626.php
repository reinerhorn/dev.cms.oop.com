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
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $block = null;
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $handlerClass = trim($row['handler_class'] ?? '');
            $table  = trim($row['table_name'] ?? '');
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);
            // --------------------------------------------
            // 0) Handler-Class Override (Höchste Priorität)
            // --------------------------------------------
            if (!empty($handlerClass) && !empty($uuid)) {

                $class = '\\CMS\\Core\\Controller\\Admin\\' . $handlerClass;

                if (class_exists($class) && method_exists($class, 'loadContentByLanguage')) {

                    error_log('HANDLER CLASS USED: ' . $class);

                    $block = $class::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );

                    if (!empty($block)) {
                        $block['idx'] = $idx;
                        $blocks[] = $block;
                        continue;
                    }
                }
            }
            // --- END INSERTED CODE ---

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

                    if (isset($block['config'])) {
                        error_log('CONFIG RAW (from block): ' . print_r($block['config'], true));
                    } else {
                        error_log('CONFIG NOT SET for plugin: ' . $plugin);
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

        return $blocks;
    }
}
