<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\CMSApp;
use CMS\Core\CMSAppFrontend;
use CMS\Plugin\PluginPlaintext;
use Twig\Environment;
use mysqli;
use Throwable;

class PageController
{
    private Environment $twig;
    private mysqli $db;
    private string $frontendClass;
    private bool $isDebug;

    public function __construct(Environment $twig, mysqli $db, string $frontendClass, bool $isDebug = false)
    {
        $this->twig = $twig;
        $this->db = $db;
        $this->frontendClass = $frontendClass;
        $this->isDebug = $isDebug;
    }

    /**
     * Hauptlogik: Ermittelt die Seite anhand der Sprache + Slug und rendert sie.
     */
    public function handleRequest(string $language, string $slug): void
    {
        $pageId = null;
        $pageConfigs = [];

        try {
            // 1. Page-UUID anhand des Slugs holen
            $stmt = $this->db->prepare("SELECT page_uuid FROM page WHERE slug = ? AND enabled = 1 LIMIT 1");
            $stmt->bind_param("s", $slug);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                $pageId = $row['page_uuid'];
            }
            $stmt->close();

            // 2. page_config-Einträge laden
            if ($pageId !== null) {
                $stmt2 = $this->db->prepare("
                    SELECT 
                        pc.page_config_uuid,
                        pc.idx,
                        pc.content_type,
                        pc.fk_page_uuid,
                        pc.fk_plugin_uuid,
                        pc.plugin_content_uuid,
                        pl.name AS plugin_label
                    FROM page_config pc
                    LEFT JOIN plugin pl ON pc.fk_plugin_uuid = pl.plugin_uuid
                    WHERE pc.fk_page_uuid = ?
                    ORDER BY pc.idx ASC
                ");
                $stmt2->bind_param("s", $pageId);
                $stmt2->execute();
                $pageConfigs = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt2->close();
            }
        } catch (Throwable $e) {
            if ($this->isDebug) {
                echo '<pre>DB-Fehler: ' . htmlspecialchars($e->getMessage()) . '</pre>';
            }
        }

        // Inhalte dynamisch laden
        $contents = [];
        foreach ($pageConfigs as $config) {
            $pluginLabel = $config['plugin_label'] ?? '';
            $pluginContentUuid = $config['plugin_content_uuid'] ?? null;
            $idx = $config['idx'] ?? null;
            $content = null;

            try {
                switch ($pluginLabel) {
                    case 'Plaintext':
                        $content = PluginPlaintext::getContent(
                            $this->db,
                            $pluginContentUuid,
                            $pageId,
                            $language
                        );
                        break;

                    // Erweiterungspunkt: Weitere Plugins hier ergänzen
                    default:
                        if ($this->isDebug) {
                            echo "<!-- Unbekanntes Plugin: " . htmlspecialchars($pluginLabel) . " -->\n";
                        }
                        break;
                }
            } catch (Throwable $e) {
                if ($this->isDebug) {
                    echo '<pre>Plugin-Fehler (' . htmlspecialchars($pluginLabel) . '): ' . htmlspecialchars($e->getMessage()) . '</pre>';
                }
            }

            if (!empty($content)) {
                foreach ($content as $label => $blocks) {
                    $contents[$label] = $blocks;
                }
            } else {
                $contents[$idx ?? count($contents)] = [];
            }
        }

        // --- Debug-Ausgabe optional ---
        if ($this->isDebug) {
            echo "<!-- Debug PageID: {$pageId} -->\n";
            echo "<!-- Debug Language: {$language} -->\n";
            echo "<pre style='background:#111;color:#0f0;padding:6px;'>"; 
            print_r($contents);
            echo "</pre>";
        }

        // --- Seite rendern ---
        $frontend = $this->frontendClass;
        echo $frontend::render($this->twig, $pageId, null, [
            'contents' => $contents,
            'page_config' => $pageConfigs,
            'slug' => $slug,
            'language' => $language
        ]);
    }
}