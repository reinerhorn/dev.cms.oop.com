<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;
use CMS\Plugin\PluginFormular;
use CMS\Application\FormAction\FormDataLoaderResolver;
use CMS\Application\FormData\FormDataLoaderInterface;

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
        error_log('PAGE CONFIG ROWS: ' . print_r($rows, true));
        $stmt->close();

        $blocks = [];

        foreach ($rows as $row) {
            $block = null;
            $plugin = strtolower(trim($row['plugin_name'] ?? ''));
            $table  = trim($row['table_name'] ?? '');
            // --- DEBUG TABLE COMPARISON ---
            error_log('TABLE RAW: >' . $table . '<');
            error_log('TABLE LENGTH: ' . strlen($table));
            error_log('COMPARE RESULT: ' . ($table === 'p_content_formular' ? 'MATCH' : 'NO MATCH'));
            // --- END DEBUG ---
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
            // Formular Plugin über PluginFormular laden
            // --------------------------------------------
            if ($table === 'p_content_formular') {

                $block = null;
                // PluginFormular direkt verwenden (use-Statement ist vorhanden)
                if (class_exists(PluginFormular::class)) {

                    $block = PluginFormular::loadContentByLanguage(
                        $this->db,
                        $uuid,
                        $this->language
                    );

                    if (!empty($block)) {

                        // Technischer Form-Typ aus PluginFormular (z.B. entity_editor)
                        $formType = $block['type'] ?? null;

                        // Rendering-Typ bleibt das Formular-Plugin (z.B. "forms")
                        $block['type'] = $plugin;

                        // Saubere Trennung von Rendering und Logik
                        $block['form_type'] = $formType;

                        $buttons = [];

                        if (!empty($formType)) {
                            error_log('FORM ID FOR BUTTONS: ' . $formType);
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
                        }

                        $block['idx']       = $idx;
                        $block['buttons']   = $buttons;
                        $block['form_id']   = $formType;

                        // --------------------------------------------
                        // FormDataLoader (Daten hydratisieren bei GET und POST)
                        // --------------------------------------------
                        $formActionKey = $block['form_type'] ?? null;

                        if ($formActionKey) {

                            // GET + POST zusammenführen, damit load_id nicht verloren geht
                            $requestData = array_merge($_GET, $_POST);

                            $loader = FormDataLoaderResolver::resolve($formActionKey);

                            if ($loader instanceof FormDataLoaderInterface) {
                                $block = $loader->hydrate($block, $requestData);
                            }
                        }
                    }
                }

                // Block dem Seiten-Array hinzufügen
                if (!empty($block)) {
                    $blocks[] = $block;
                }

                continue;
            }

            // --------------------------------------------
            // Dynamische Plugin-Klassen-Auflösung
            // --------------------------------------------

            // Klassenname aus plugin.name ableiten
            // Beispiel: "plaintext" -> \CMS\Plugin\PluginPlaintext
            $className = '\\CMS\\Plugin\\Plugin' . str_replace(' ', '', ucwords(str_replace('_', ' ', $plugin)));

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
