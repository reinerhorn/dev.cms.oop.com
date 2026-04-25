<?php
namespace CMS\Application\FormAction\Plaintext;

use CMS\Application\Interface\HandlerInterface;
use mysqli;
use Throwable;

class PluginPlaintext implements HandlerIntergace
{

    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array
    {
        $stmt = $db->prepare("
            SELECT headline, text, fk_language_id AS lang, idx, label
            FROM p_content_plaintext
            WHERE id = ?
              AND fk_language_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $pluginContentUuid, $language);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return [];
        }

        return [
            'headline' => $row['headline'] ?? '',
            'text'     => $row['text'] ?? '',
            'lang'     => $row['lang'] ?? $language,
            'idx'      => (int)($row['idx'] ?? 0),
            'label'    => $row['label'] ?? null,
        ];
    }

    /**
     * Lädt alle Plaintext-Blöcke für eine Seite (page_uuid).
     * Liest page_config (plugin_content_uuid) und ruft loadByUuid für jedes Plugin-Content auf.
     * Ergebnis: [ idx => [ {headline,text,lang,label,idx}, ... ] ]
     */
    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array
    {
        $out = [];

        $stmt = $db->prepare("
            SELECT pc.idx, pc.plugin_content_uuid
            FROM page_config pc
            LEFT JOIN plugin p ON p.plugin_uuid = pc.fk_plugin_uuid
            WHERE pc.fk_page_uuid = ?
              AND LOWER(p.name) = 'plaintext'
            ORDER BY pc.idx ASC
        ");
        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {
            $idx = (int)$r['idx'];
            $uuid = $r['plugin_content_uuid'] ?? null;
            if (!$uuid) continue;

            // erst versuch gewünschte Sprache (wenn vorhanden)
            $block = self::loadByUuid($db, $uuid, $language);
            if (empty($block)) {
                // fallback: versuche 'de' dann 'en'
                foreach (['de','en'] as $fb) {
                    if ($fb === $language) continue;
                    $block = self::loadByUuid($db, $uuid, $fb);
                    if (!empty($block)) break;
                }
            }

            if (!empty($block)) {
                $out[$idx][] = $block;
            }
        }

        return $out;
    }

    /**
     * Lädt einen einzelnen Content-Block für eine bestimmte Sprache mit Fallback auf 'de' oder 'en'.
     */
    public static function loadContentByLanguage(mysqli $db, string $uuid, string $language): array
    {
        $block = self::loadByUuid($db, $uuid, $language);
        if (empty($block)) {
            foreach (['de', 'en'] as $fallback) {
                if ($fallback === $language) {
                    continue;
                }
                $block = self::loadByUuid($db, $uuid, $fallback);
                if (!empty($block)) {
                    break;
                }
            }
        }
        return $block;
    }

    /**
     * Lädt alle Plaintext-Blöcke für eine Seite ohne Twig-Template-Zuordnung.
     */
    public static function getContent(mysqli $db, string $pageId, string $language): array
    {
        return self::loadByPage($db, $pageId, $language);
    }
}