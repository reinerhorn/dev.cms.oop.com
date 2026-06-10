<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class FooterController
{
    /**
     * Footer ist:
     * - nicht sprachabhängig im Datensatz
     * - nur context-abhängig
     * - Übersetzung erfolgt über fk_translation_placeholder
     */
    public static function getFooterData(string $language, string $context): array
    {
        $db = CMSApp::getDb();

        // ---------------------------------------------
        // 1) Footer + optionale Übersetzung laden
        // ---------------------------------------------
        $stmt = $db->prepare("
            SELECT
                f.id,
                f.headline,
                f.label,
                f.link,
                f.version,
                f.css,
                t.label AS translated_label
            FROM footer f
            LEFT JOIN translation t
              ON t.fk_translation_holder = f.fk_translation_placeholder
             AND t.fk_language_id = ?
            WHERE f.context_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return self::fallbackFooter();
        }

        $stmt->bind_param('ss', $language, $context);
        $stmt->execute();
        $footer = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // ---------------------------------------------
        // 2) Fallback → frontend
        // ---------------------------------------------
        if (!$footer && $context !== 'frontend') {
            $stmt = $db->prepare("
                SELECT
                    f.id,
                    f.headline,
                    f.label,
                    f.link,
                    f.version,
                    f.css,
                    t.label AS translated_label
                FROM footer f
                LEFT JOIN translation t
                  ON t.fk_translation_holder = f.fk_translation_placeholder
                 AND t.fk_language_id = ?
                WHERE f.context_id = 'frontend'
                LIMIT 1
            ");

            $stmt->bind_param('s', $language);
            $stmt->execute();
            $footer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$footer) {
            return self::fallbackFooter();
        }

        // ---------------------------------------------
        // 3) Footer-Images
        // ---------------------------------------------
        $images = [];

        $imgStmt = $db->prepare("
            SELECT image_url, alt_text
            FROM footer_images
            WHERE footer_id = ?
            ORDER BY sort_order ASC
        ");

        if ($imgStmt) {
            $imgStmt->bind_param('s', $footer['id']);
            $imgStmt->execute();
            $res = $imgStmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $images[] = $row;
            }

            $imgStmt->close();
        }

        // ---------------------------------------------
        // 4) Rückgabe (analog Header)
        // ---------------------------------------------
        return [
            'headline' => $footer['headline'] ?? '',
            'label'    => $footer['translated_label'] ?? $footer['label'] ?? '',
            'link'     => $footer['link'] ?? '/',
            'version'  => $footer['version'] ?? '',
            'css'      => $footer['css'] ?? 'footer',
            'images'   => $images,
        ];
    }

    /**
     * Hard-Fallback
     */
    private static function fallbackFooter(): array
    {
        return [
            'headline' => '',
            'label'    => '',
            'link'     => '/',
            'version'  => '',
            'css'      => 'footer',
            'images'   => [],
        ];
    }
}