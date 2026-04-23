<?php
namespace CMS\Plugin\Plaintext;

use CMS\Application\Interface\HandlerInterface;
use mysqli;
use Throwable;

class PluginPlaintext implements HandlerInterface
{

    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array
    {
        error_log('ENTER PluginPlaintext::loadByUuid');
        error_log('PLAINTEXT UUID: ' . $pluginContentUuid);
        error_log('LANGUAGE: ' . $language);

        $stmt = $db->prepare(" 
            SELECT headline, text, fk_language_id AS lang, idx, label
            FROM p_content_plaintext
            WHERE id = ?
              AND fk_language_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            error_log('PLAINTEXT ERROR: prepare failed');
            return [];
        }

        $stmt->bind_param('ss', $pluginContentUuid, $language);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            error_log('PLAINTEXT: no row found for UUID ' . $pluginContentUuid);
            return [];
        }

        error_log('PLAINTEXT ROW: ' . print_r($row, true));

        $block = [
            'plugin_key' => 'plaintext',
            'headline'   => $row['headline'] ?? '',
            'text'       => $row['text'] ?? '',
            'lang'       => $row['lang'] ?? $language,
            'idx'        => (int)($row['idx'] ?? 0),
            'label'      => $row['label'] ?? null,
        ];

        error_log('PLAINTEXT BLOCK: ' . print_r($block, true));
        error_log('EXIT PluginPlaintext::loadByUuid');

        return $block;
    }

    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array
    {
        error_log('ENTER PluginPlaintext::loadByPage for page: ' . $pageUuid);

        $out = [];

        $stmt = $db->prepare(" 
            SELECT pc.idx, pc.plugin_content_uuid
            FROM page_config pc
            LEFT JOIN plugin p ON p.plugin_uuid = pc.fk_plugin_uuid
            WHERE pc.fk_page_uuid = ?
              AND LOWER(p.plugin_key) = 'plaintext'
            ORDER BY pc.idx ASC
        ");

        if (!$stmt) {
            error_log('PLAINTEXT ERROR: prepare loadByPage failed');
            return [];
        }

        $stmt->bind_param('s', $pageUuid);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($res && ($r = $res->fetch_assoc())) {
            error_log('PLAINTEXT PAGE ROW: ' . print_r($r, true));

            $idx  = (int)$r['idx'];
            $uuid = $r['plugin_content_uuid'] ?? null;

            if (!$uuid) {
                error_log('PLAINTEXT: missing UUID at idx ' . $idx);
                continue;
            }

            $block = self::loadByUuid($db, $uuid, $language);

            if (empty($block)) {
                error_log('PLAINTEXT: fallback triggered for UUID ' . $uuid);

                foreach (['de','en'] as $fb) {
                    if ($fb === $language) continue;

                    $block = self::loadByUuid($db, $uuid, $fb);
                    if (!empty($block)) break;
                }
            }

            if (!empty($block)) {
                $out[$idx][] = $block;
            } else {
                error_log('PLAINTEXT: block still empty after fallback');
            }
        }

        $stmt->close();

        error_log('EXIT PluginPlaintext::loadByPage');
        error_log('PLAINTEXT RESULT: ' . print_r($out, true));

        return $out;
    }

    public static function loadContentByLanguage(mysqli $db, string $uuid, string $language): array
    {
        error_log('ENTER PluginPlaintext::loadContentByLanguage');

        $block = self::loadByUuid($db, $uuid, $language);

        if (empty($block)) {
            foreach (['de', 'en'] as $fallback) {
                if ($fallback === $language) continue;

                $block = self::loadByUuid($db, $uuid, $fallback);
                if (!empty($block)) break;
            }
        }

        error_log('EXIT PluginPlaintext::loadContentByLanguage');
        return $block;
    }

    public static function getContent(mysqli $db, string $pageId, string $language): array
    {
        error_log('ENTER PluginPlaintext::getContent');
        return self::loadByPage($db, $pageId, $language);
    }
}
