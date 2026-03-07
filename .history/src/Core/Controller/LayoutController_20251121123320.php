<?php

namespace CMS\Core\Controller\Layout;

use CMS\Plugin\PluginPlaintext;
use CMS\Plugin\PluginFormular;
use mysqli;

class LayoutController
{
    private mysqli $db;
    private string $language;
    private ?int $role;

    public function __construct(mysqli $db, string $language, ?int $role = null)
    {
        $this->db = $db;
        $this->language = $language;
        $this->role = $role;
    }

    public function getContent(?string $pageId): array
    {
        $this->language = strtolower(substr($this->language, 0, 2));
        error_log("[LayoutController] getContent() gestartet für: " . ($pageId ?? 'NULL'));

        if (!$pageId) {
            return [
                'template' => 'pages/home.twig',
                'data' => ['headline' => 'Startseite', 'text' => 'Willkommen im CMS!']
            ];
        }

        // Slug in UUID auflösen
        if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $pageId)) {
            $stmtSlug = $this->db->prepare("SELECT page_uuid FROM page WHERE slug = ? LIMIT 1");
            $stmtSlug->bind_param('s', $pageId);
            $stmtSlug->execute();
            $resSlug = $stmtSlug->get_result();
            if ($rowSlug = $resSlug->fetch_assoc()) {
                $pageId = $rowSlug['page_uuid'];
                error_log("[LayoutController] Slug in UUID aufgelöst: {$pageId}");
            } else {
                error_log("[LayoutController] Kein Eintrag in page gefunden für Slug '{$pageId}'");
            }
            $stmtSlug->close();
        }

        // page_config laden
        $stmt = $this->db->prepare("
            SELECT 
                pc.page_config_uuid,
                pc.idx,
                pc.fk_plugin_uuid,
                pc.plugin_content_uuid,
                p.name AS plugin_name
            FROM page_config pc
            LEFT JOIN plugin p ON pc.fk_plugin_uuid = p.plugin_uuid
            LEFT JOIN page pg ON pg.page_uuid = pc.fk_page_uuid
            WHERE BINARY pc.fk_page_uuid = BINARY ? OR BINARY pg.slug = BINARY ?
            ORDER BY pc.idx ASC
        ");

        if (!$stmt) {
            error_log('[LayoutController] SQL-Fehler bei prepare(): ' . $this->db->error);
            return [
                'template' => 'pages/fallback.twig',
                'data' => [
                    'headline' => 'Datenbankfehler',
                    'text' => 'Die Anfrage konnte nicht vorbereitet werden: ' . htmlspecialchars($this->db->error)
                ]
            ];
        }

        $stmt->bind_param('ss', $pageId, $pageId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        error_log("[LayoutController] Gefundene page_config-Zeilen: " . count($rows));

        $contentBlocks = [];
        $pluginPlaintext = new PluginPlaintext($this->db);
        $pluginFormular = new PluginFormular($this->db);

        foreach ($rows as $row) {
            $pluginName = strtolower($row['plugin_name'] ?? '');
            $pluginContentUuid = $row['plugin_content_uuid'] ?? null;

            error_log("[LayoutController] Plugin gefunden: {$pluginName}");

            // 🔹 Plaintext
            if ($pluginName === 'plaintext') {
                $content = $pluginPlaintext->loadContentByLanguage($pluginContentUuid, $this->language);
                if ($content && (!empty($content['headline']) || !empty($content['text']))) {
                    $contentBlocks[] = [
                        'type' => 'plaintext',
                        'headline' => $content['headline'] ?? '',
                        'text' => $content['text'] ?? '',
                        'lang' => $content['lang'] ?? 'de'
                    ];
                }
            }

            // 🔹 Formulare (login/register)
            if ($pluginName === 'login-form' || $pluginName === 'register') {
                $form = $pluginFormular->loadContentByLanguage($pluginContentUuid, $this->language);
                error_log("[LayoutController] Geladene Formular-Daten: " . print_r($form, true));
                if ($form && (!empty($form['headline']) || !empty($form['text']))) {
                    $contentBlocks[] = [
                        'type' => $form['type'] ?? 'form',
                        'headline' => $form['headline'] ?? '',
                        'text' => $form['text'] ?? '',
                        'config' => $form['config'] ?? [],
                        'lang' => $form['lang'] ?? 'de'
                    ];
                }
            }
        }

        error_log("[DEBUG] contentBlocks: " . print_r($contentBlocks, true));

        if (!empty($contentBlocks)) {
            return [
                'template' => 'pages/page_content.twig',
                'data' => ['content_data' => $contentBlocks]
            ];
        }

        error_log("[LayoutController] Fallback 404 für {$pageId}");
        return [
            'template' => 'pages/fallback.twig',
            'data' => [
                'headline' => '404 – Keine Inhalte gefunden',
                'text' => 'Diese Seite enthält derzeit keine Inhalte oder ist deaktiviert.'
            ]
        ];
    }
}