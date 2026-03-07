<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class HeaderController
{
    /**
     * Header:
     * - context-abhängig (frontend, admin, member, shop)
     * - optional sprachabhängig (über translation)
     * - KEINE Rollenlogik
     */
    public static function getHeaderData(string $language, string $context): array
    {
        $db = CMSApp::getDb();

        // -------------------------------------------------
        // 1) Header + optionale Übersetzung laden
        // -------------------------------------------------
        $stmt = $db->prepare("
            SELECT
                h.id,
                h.css,
                h.headline AS default_headline,
                t.label    AS translated_headline
            FROM header h
            LEFT JOIN translation t
              ON t.fk_translation_holder = h.fk_translation_placeholder
             AND t.fk_language_id = ?
            WHERE h.context_id = ?
              AND h.enabled = 1
              AND h.position = 'main'
            ORDER BY h.sort_order ASC
            LIMIT 1
        ");

        if (!$stmt) {
            return self::fallbackHeader();
        }

        $stmt->bind_param('ss', $language, $context);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // -------------------------------------------------
        // 2) Fallback → frontend
        // -------------------------------------------------
        if (!$header && $context !== 'frontend') {
            $stmt = $db->prepare("
                SELECT
                    h.id,
                    h.css,
                    h.headline AS default_headline,
                    t.label    AS translated_headline
                FROM header h
                LEFT JOIN translation t
                  ON t.fk_translation_holder = h.fk_translation_placeholder
                 AND t.fk_language_id = ?
                WHERE h.context_id = 'frontend'
                  AND h.enabled = 1
                  AND h.position = 'main'
                ORDER BY h.sort_order ASC
                LIMIT 1
            ");

            $stmt->bind_param('s', $language);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$header) {
            return self::fallbackHeader();
        }

        // -------------------------------------------------
        // 3) Headline bestimmen (wie Footer!)
        // -------------------------------------------------
        $headline = $header['translated_headline']
            ?: $header['default_headline'];

        // -------------------------------------------------
        // 4) Header-Images laden
        // -------------------------------------------------
        $images = [];
        $imgStmt = $db->prepare("
            SELECT image_url, link_url, alt_text
            FROM header_images
            WHERE header_id = ?
            ORDER BY sort_order ASC
        ");

        $imgStmt->bind_param('s', $header['id']);
        $imgStmt->execute();
        $res = $imgStmt->get_result();

        while ($img = $res->fetch_assoc()) {
            $images[] = $img;
        }
        $imgStmt->close();

        // Default-Logo, wenn keine Images existieren
        if (empty($images)) {
            $images[] = [
                'image_url' => '/assets/images/hd-logo.webp',
                'link_url'  => '/',
                'alt_text'  => 'Logo',
            ];
        }

        // -------------------------------------------------
        // 5) Rückgabe
        // -------------------------------------------------
        return [
            'id'          => $header['id'],
            'css'         => $header['css'] ?: 'header',
            'companyname' => $headline,   // ← für dein <span class="companyname">
            'headline'    => $headline,   // optional, falls Twig es woanders nutzt
            'images'      => $images,
        ];
    }

    /**
     * Hard-Fallback
     */
    private static function fallbackHeader(): array
    {
        return [
            'id'          => null,
            'css'         => 'header',
            'companyname' => '',
            'headline'    => '',
            'images'      => [
                [
                    'image_url' => '/assets/images/hd-logo.webp',
                    'link_url'  => '/',
                    'alt_text'  => 'Logo',
                ],
            ],
        ];
    }
}