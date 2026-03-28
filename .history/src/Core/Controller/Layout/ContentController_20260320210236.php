<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;

class ContentController
{
    private mysqli $db;
    private string $language;

    public function __construct(mysqli $db, string $language)
    {
        $this->db = $db;
        $this->language = strtolower(substr($language, 0, 2));
    }

    public function getPageContent(?string $pageId): array
    {
        return $this->getContent($pageId);
    }

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

            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $table  = trim($row['table_name'] ?? '');
            $class  = $row['handler_class'] ?? null;
            if ($class && !str_contains($class, '\\')) {

                $module = strtolower(trim($row['plugin_name'] ?? ''));

                // Default namespace
                $baseNamespace = '\\CMS\\Plugin\\';

                // Optional: different namespace per module
                if ($module === 'admin') {
                    $baseNamespace = '\\CMS\\Controller\\';
                }

                $class = $baseNamespace . $class;
            }
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);

            if (!$uuid) {
                continue;
            }

            // --------------------------------------------
            // Plaintext direkt laden (Sonderfall bleibt ok)
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
            // NEU: handler_class entscheidet alles
            // --------------------------------------------
            if ($class && class_exists($class)) {

                $block = [];

                if (method_exists($class, 'loadContentByLanguage')) {
                    $block = $class::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                } elseif (method_exists($class, 'loadByUuid')) {
                    $block = $class::loadByUuid(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                }

                if (!empty($block)) {
                    $block['type'] = $plugin;
                    $block['idx']  = $idx;
                    $blocks[] = $block;
                    continue;
                }
            }

            // --------------------------------------------
            // Fallback
            // --------------------------------------------
            error_log(
                'UNKNOWN PLUGIN HANDLER: name=' . $plugin .
                ' | table=' . $table .
                ' | uuid=' . ($uuid ?? 'NULL')
            );

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

