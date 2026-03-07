<?php

namespace CMS\Plugin;

use mysqli;

class PluginPlaintext
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Ermittelt eine fallback-sichere Sprache, falls der gewünschte Sprachdatensatz nicht vorhanden ist.
     * Prüft Verfügbarkeit von 'en' und 'de' in dieser Reihenfolge.
     *
     * @param mysqli $db
     * @param string $pageId
     * @param string $desiredLanguage
     * @return string
     */
    public static function getFallbackLanguage(mysqli $db, string $pageId, string $desiredLanguage): string
    {
        $fallbacks = ['en', 'de'];
        // Falls gewünschte Sprache schon fallback ist, entferne sie aus der Liste
        $fallbacks = array_filter($fallbacks, fn($lang) => $lang !== $desiredLanguage);

        foreach ($fallbacks as $lang) {
            $sql = "
                SELECT 1
                FROM page_config pc
                JOIN p_content_plaintext pp
                  ON pp.id = pc.plugin_content_uuid
                 AND pp.fk_language_id = ?
                WHERE pc.fk_page_uuid = ?
                LIMIT 1
            ";
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('ss', $lang, $pageId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $stmt->close();
                return $lang;
            }
            $stmt->close();
        }
        // Wenn keine Fallback-Sprache gefunden, nutze gewünschte Sprache als Default
        return $desiredLanguage;
    }

    /**
     * Holt alle Plaintext-Inhalte, die einer bestimmten Seite zugeordnet sind.
     *
     * @param mysqli $db
     * @param string $pageId UUID der Seite
     * @param string $language Sprachcode (z. B. 'de')
     * @return array
     */
    public static function getContent(mysqli $db, string $pageId, string $language): array
    {
        $content = [];

        if (defined('CMS_DEBUG') && CMS_DEBUG) {
            echo "<!-- PluginPlaintext::getContent() → pageId={$pageId}, requested language={$language} -->\n";
        }

        try {
            $sql = "
                SELECT 
                    pp.id AS content_id,
                    pp.label,
                    pp.headline,
                    pp.text,
                    pp.fk_language_id,
                    pp.idx
                FROM page_config pc
                JOIN p_content_plaintext pp
                  ON pp.id = pc.plugin_content_uuid
                 AND pp.fk_language_id = ?
                WHERE pc.fk_page_uuid = ?
                ORDER BY pc.idx ASC
            ";

            $stmt = $db->prepare($sql);
            if (!$stmt) {
                throw new \Exception('SQL-Fehler: ' . $db->error);
            }

            $stmt->bind_param('ss', $language, $pageId);
            $stmt->execute();
            $result = $stmt->get_result();

            // Fallback prüfen, falls keine Einträge für gewünschte Sprache
            if ($result->num_rows === 0) {
                $stmt->close();
                $fallbackLanguage = self::getFallbackLanguage($db, $pageId, $language);
                if ($fallbackLanguage !== $language) {
                    if (defined('CMS_DEBUG') && CMS_DEBUG) {
                        echo "<!-- PluginPlaintext::getContent() → Kein Inhalt für Sprache '{$language}' gefunden, Fallback auf '{$fallbackLanguage}' -->\n";
                    }
                    $stmt = $db->prepare($sql);
                    if (!$stmt) {
                        throw new \Exception('SQL-Fehler: ' . $db->error);
                    }
                    $stmt->bind_param('ss', $fallbackLanguage, $pageId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $language = $fallbackLanguage;
                } else {
                    if (defined('CMS_DEBUG') && CMS_DEBUG) {
                        echo "<!-- PluginPlaintext::getContent() → Kein Inhalt für Sprache '{$language}' gefunden, kein Fallback verfügbar -->\n";
                    }
                }
            }

            while ($row = $result->fetch_assoc()) {
                // 🔹 Leere Einträge überspringen
                $headline = trim($row['headline'] ?? '');
                $text     = trim($row['text'] ?? '');
                if ($headline === '' && $text === '') {
                    continue;
                }

                $label = $row['label'] ?? 'unlabeled';
                if (!isset($content[$label])) {
                    $content[$label] = [];
                }

                // 🔹 Doppelte Inhalte vermeiden (nach Headline+Text)
                $hash = md5($headline . $text);
                if (!isset($content[$label][$hash])) {
                    $content[$label][$hash] = [
                        'headline' => $headline,
                        'text'     => $text,
                        'lang'     => $row['fk_language_id'] ?? '',
                        'idx'      => (int)($row['idx'] ?? 0),
                    ];
                }
            }

            $stmt->close();

            // 🔹 Nach Index sortieren und Werte normalisieren
            foreach ($content as $label => $blocks) {
                usort($blocks, fn($a, $b) => $a['idx'] <=> $b['idx']);
                $content[$label] = array_values($blocks);
            }

            if (defined('CMS_DEBUG') && CMS_DEBUG) {
                echo "<pre style='color:lime;background:#111;padding:6px;'>🧩 Gefilterte Datensätze (Sprache: {$language}):\n";
                print_r($content);
                echo "</pre>";
            }

        } catch (\Throwable $e) {
            if (defined('CMS_DEBUG') && CMS_DEBUG) {
                echo "<pre style='color:red;background:#111;padding:6px;'>PluginPlaintext Fehler: " . htmlspecialchars($e->getMessage()) . "</pre>";
            }
        }

        return $content;
    }

    /**
     * Lädt einen einzelnen Plaintext-Content-Eintrag anhand der Plugin-Content-UUID.
     *
     * @param string $pluginContentUuid
     * @return array
     */
    public function loadContent(string $pluginContentUuid): array
    {
        $stmt = $this->db->prepare("
            SELECT headline, text, fk_language_id AS lang
            FROM p_content_plaintext
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $pluginContentUuid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();

        if (!$row || (trim($row['headline']) === '' && trim($row['text']) === '')) {
            return [
                'headline' => '',
                'text'     => '',
                'lang'     => 'de',
            ];
        }

        return $row;
    }
}