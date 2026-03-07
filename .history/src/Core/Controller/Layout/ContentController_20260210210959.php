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
                p.table_name
            FROM page_config pc
            JOIN plugin p ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ?
            ORDER BY pc.idx ASC
        ");
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $table  = trim($row['table_name'] ?? '');
            $uuid   = $row['plugin_content_uuid'] ?? null;
            $idx    = (int)($row['idx'] ?? 0);

            if (!$table || !$uuid) {
                continue;
            }

            switch ($table) {

                // ---------- PLAINTEXT ----------
                case 'p_content_plaintext':
                    $stmt = $this->db->prepare("
                        SELECT headline, text, fk_language_id
                        FROM p_content_plaintext
                        WHERE id = ?
                          AND fk_language_id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param('ss', $uuid, $this->language);
                    $stmt->execute();
                    $p = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($p) {
                        $blocks[] = [
                            'type'     => 'plaintext',
                            'idx'      => $idx,
                            'headline' => $p['headline'] ?? '',
                            'text'     => $p['text'] ?? '',
                            'lang'     => $p['fk_language_id'] ?? $this->language,
                        ];
                    }
                    break;

                // ---------- FORMS ----------
                case 'p_content_formular':
                    $stmt = $this->db->prepare("
                        SELECT form_type, form_style, headline, text, config_json, fk_language_id
                        FROM p_content_formular
                        WHERE id = ?
                          AND fk_language_id = ?
                        LIMIT 1
                    ");
                    $stmt->bind_param('ss', $uuid, $this->language);
                    $stmt->execute();
                    $f = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$f || empty($f['form_type'])) {
                        break;
                    }

                    $formId = (string)$f['form_type'];

                    $config = [];
                    if (!empty($f['config_json'])) {
                        $decoded = json_decode($f['config_json'], true);
                        if (is_array($decoded)) {
                            $config = $decoded;
                        }
                    }

                    $stmtBtn = $this->db->prepare("
                        SELECT 
                            b.button_id,
                            b.label_key,
                            b.action,
                            b.button_type,
                            b.variant,
                            b.confirm_required
                        FROM form_button fb
                        JOIN ui_button b ON b.button_id = fb.button_id
                        WHERE fb.form_id = ?
                          AND b.enabled = 1
                        ORDER BY fb.sort_order ASC
                    ");
                    $stmtBtn->bind_param('s', $formId);
                    $stmtBtn->execute();
                    $buttons = $stmtBtn->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtBtn->close();

                    $blocks[] = [
                        'type'          => 'forms',
                        'idx'           => $idx,
                        'headline'      => $f['headline'] ?? '',
                        'text'          => $f['text'] ?? '',
                        'config'        => $config,
                        'buttons'       => $buttons,
                        'style'         => $f['form_style'] ?? 'default',
                        'wrapper_class' => 'page-form-box',
                        'lang'          => $f['fk_language_id'] ?? $this->language,
                    ];
                    break;

                // ---------- OTHER PLUGINS ----------
                default:
                    $blocks[] = [
                        'type'     => $plugin,
                        'idx'      => $idx,
                        'uuid'     => $uuid,
                        'template' => "pages/{$plugin}.twig",
                    ];
                    break;
            }
        }

        return $blocks;
    }
}
