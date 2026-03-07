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
            // SQL Formular Plugin (p_content_formular)
            // --------------------------------------------
            if ($table === 'p_content_formular') {

                $stmt = $this->db->prepare("
                    SELECT form_type, form_style, headline, text, config_json, fk_language_id
                    FROM p_content_formular
                    WHERE id = ?
                      AND fk_language_id = ?
                    LIMIT 1
                ");
                $stmt->bind_param('ss', $uuid, $this->language);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($data) {

                    $config = [];
                    if (!empty($data['config_json'])) {
                        $decoded = json_decode($data['config_json'], true);
                        if (is_array($decoded)) {
                            $config = $decoded;
                        }
                    }

                    // Buttons aus form_button + ui_button laden
                    $buttons = [];
                    $formId = $data['form_type'] ?? '';

                    if (!empty($formId)) {
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
                    }

                    $blocks[] = [
                        'type'     => $plugin, // z.B. "forms"
                        'idx'      => $idx,
                        'headline' => $data['headline'] ?? '',
                        'text'     => $data['text'] ?? '',
                        'config'   => $config,
                        'buttons'  => $buttons,
                        'style'    => $data['form_style'] ?? 'default',
                        'form_id'  => $data['form_type'] ?? '',
                        'lang'     => $data['fk_language_id'] ?? $this->language,
                    ];
                }

                continue;
            }

            // --------------------------------------------
            // Dynamische Plugin-Klassen-Auflösung
            // --------------------------------------------

            // Klassenname aus plugin.name ableiten
            // Beispiel: "plaintext" -> \CMS\Plugin\PluginPlaintext
            $className = '\\CMS\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin)));

            if (class_exists($className)) {

                $block = [];

                // Bevorzugte Methode (neues Plugin-Pattern)
                if (method_exists($className, 'loadContentByLanguage')) {
                    $block = $className::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                }

                // Fallback für ältere Plugins (z.B. loadByUuid)
                elseif (method_exists($className, 'loadByUuid')) {
                    $block = $className::loadByUuid(
                        $this->db,
                        $uuid,
                        $this->language
                    );
                }

                if (!empty($block)) {

                    // --- DEBUG FORM CONFIG ---
                    if (isset($block['config'])) {
                        error_log('CONFIG RAW (from block): ' . print_r($block['config'], true));
                    } else {
                        error_log('CONFIG NOT SET for plugin: ' . $plugin);
                    }
                    // --- END DEBUG ---

                    $block['idx'] = $idx;
                    $blocks[] = $block;
                    continue;
                }
            }

            // Fallback: generisches Template-Rendering
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
