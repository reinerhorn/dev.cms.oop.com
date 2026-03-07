<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class HeaderController
{
    /**
     * Liefert Header-Daten anhand des Contexts (frontend, admin, member, shop)
     */
    public static function getHeaderData(string $language, string $context): array
    {
        $db = CMSApp::getDb();

        // -------------------------------------------------
        // 1) Header + Übersetzung anhand Context laden
        // -------------------------------------------------
        $stmt = $db->prepare("
            SELECT
                h.id,
                h.css,
                h.headline AS default_headline,
                t.label AS translated_headline
            FROM header h
            LEFT JOIN translation t
                ON t.fk_translation_holder COLLATE utf8mb4_general_ci
                 = h.fk_translation_placeholder COLLATE utf8mb4_general_ci
               AND t.fk_language_id = ?
            WHERE h.context_id = ?
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
                    id,
                    css,
                    headline AS default_headline
                FROM header
                WHERE context_id = 'frontend'
                LIMIT 1
            ");
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$header) {
            return self::fallbackHeader();
        }

        $headline = $header['translated_headline']
            ?: $header['default_headline']
            ?: null;

        // -------------------------------------------------
        // 3) Header-Images laden
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

        if (empty($images)) {
            $images[] = [
                'image_url' => '/assets/images/hd-logo.webp',
                'link_url'  => '/',
                'alt_text'  => 'Logo',
            ];
        }

        // -------------------------------------------------
        // 4) Rückgabe
        // -------------------------------------------------
        return [
            'id'       => $header['id'],
            'css'      => $header['css'] ?: 'header',
            'headline' => $headline,
            'images'   => $images,
        ];
    }

    /**
     * Hard-Fallback (wenn gar kein Header existiert)
     */
    private static function fallbackHeader(): array
    {
        return [
            'id'       => null,
            'css'      => 'header',
            'headline' => null,
            'images'   => [
                [
                    'image_url' => '/assets/images/hd-logo.webp',
                    'link_url'  => '/',
                    'alt_text'  => 'Logo',
                ],
            ],
        ];
    }
}