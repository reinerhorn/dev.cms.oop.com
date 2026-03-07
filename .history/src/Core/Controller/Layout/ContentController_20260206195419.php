<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;
use CMS\Plugin\PluginPlaintext;
use CMS\Plugin\PluginFormular;
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
     * Die Methode, die CMSAppFrontend erwartet.
     * Sie ist nur ein Wrapper für getContent()
     */
    public function getPageContent(?string $pageId): array
    {
        return $this->getContent($pageId);
    }

    /**
     * Öffentliche API – wird intern vom Wrapper aufgerufen
     */
    public function getContent(?string $pageId): array
    {
        if (!$pageId) {
            return [
                'content_data' => [
                    [
                        'type' => 'plaintext',
                        'headline' => 'Fehler: Seite unbekannt',
                        'text' => '',
                        'lang' => $this->language
                    ]
                ]
            ];
        }

        $blocks = $this->loadPluginsForPage($pageId);

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
                pc.fk_plugin_uuid,
                pc.plugin_content_uuid,
                p.name AS plugin_name
            FROM page_config pc
            LEFT JOIN plugin p ON pc.fk_plugin_uuid = p.plugin_uuid
            WHERE pc.fk_page_uuid = ?
            ORDER BY pc.idx ASC
        ");
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {

            $pluginName = strtolower(trim($row['plugin_name'] ?? ''));
            $uuid       = $row['plugin_content_uuid'] ?? null;
            $idx        = (int)($row['idx'] ?? 0);

            if (!$pluginName || !$uuid) {
                continue;
            }

            /**
             * 1) PLAINTEXT → eigener Block
             */
            if ($pluginName === 'plaintext') {

                // 1) Label zur UUID ermitteln (sprachneutral)
                $stmtLabel = $this->db->prepare("
                    SELECT label
                    FROM p_content_plaintext
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmtLabel->bind_param('s', $uuid);
                $stmtLabel->execute();
                $labelRow = $stmtLabel->get_result()->fetch_assoc();
                $stmtLabel->close();

                if (!$labelRow || empty($labelRow['label'])) {
                    continue;
                }

                $label = $labelRow['label'];

                // 2) Content für aktuelle Sprache laden
                $stmtContent = $this->db->prepare("
                    SELECT headline, text, fk_language_id
                    FROM p_content_plaintext
                    WHERE label = ?
                      AND fk_language_id = ?
                    LIMIT 1
                ");
                $stmtContent->bind_param('ss', $label, $this->language);
                $stmtContent->execute();
                $p = $stmtContent->get_result()->fetch_assoc();
                $stmtContent->close();

                if ($p) {
                    $blocks[] = [
                        'type'     => 'plaintext',
                        'idx'      => $idx,
                        'headline' => $p['headline'] ?? '',
                        'text'     => $p['text'] ?? '',
                        'lang'     => $p['fk_language_id'] ?? $this->language
                    ];
                }

                continue;
            }

            /**
             * 2) FORMS → braucht eigenen Loader, sonst fehlen Daten
             */
            if ($pluginName === 'forms') {

                $stmtForm = $this->db->prepare("
                    SELECT form_type, form_style, headline, text, config_json, fk_language_id
                    FROM p_content_formular
                    WHERE id = ?
                      AND fk_language_id = ?
                    LIMIT 1
                ");
                $stmtForm->bind_param('ss', $uuid, $this->language);
                $stmtForm->execute();
                $f = $stmtForm->get_result()->fetch_assoc();
                $stmtForm->close();

                if (!$f) {
                    continue;
                }

                // intent MUSS hier aus form_type abgeleitet werden (CMS → Auth-Übersetzung)
                $formType = strtolower(trim((string)($f['form_type'] ?? '')));

                $intent = match ($formType) {
                    'login_form'    => 'login',
                    'register_form' => 'register',
                    default         => null,
                };

                if ($intent === null) {
                    throw new \RuntimeException('Unbekannter Formulartyp: ' . $formType);
                }

                // 🔒 config_json robust & garantiert als Array laden
                $config = [];
                if (!empty($f['config_json'])) {
                    $decoded = json_decode($f['config_json'], true);
                    if (is_array($decoded)) {
                        $config = $decoded;
                    }
                }

                // Buttons für dieses Formular laden (DB-getrieben)
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
                $stmtBtn->bind_param('s', $formType);
                $stmtBtn->execute();
                $buttons = $stmtBtn->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmtBtn->close();

                $blocks[] = [
                    'type'          => 'forms',
                    'idx'           => $idx,
                    'intent'        => $intent,
                    'headline'      => $f['headline'] ?? '',
                    'text'          => $f['text'] ?? '',
                    'config'        => $config, // ✅ garantiert Array → Twig sieht fields & submit_label
                    'buttons'       => $buttons,
                    'style'         => $f['form_style'] ?? 'default',
                    'wrapper_class' => 'page-form-box',
                    'lang'          => $f['fk_language_id'] ?? $this->language
                ];

                continue;
            }

            /**
             * 3) ALLE ANDEREN PLUGINS → dynamisch
             */
            $blocks[] = [
                'type'     => $pluginName,
                'idx'      => $idx,
                'uuid'     => $uuid,
                'template' => "pages/{$pluginName}.twig"
            ];
        }

        return $blocks;
    }
}
