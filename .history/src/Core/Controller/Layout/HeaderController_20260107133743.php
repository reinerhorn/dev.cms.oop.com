<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class HeaderController
{
    /**
     * Header ist:
     * - NICHT sprachabhängig
     * - NICHT rollenabhängig
     * - NUR context-abhängig (frontend, admin, member, shop)
     */
    public static function getHeaderData(string $context): array
    {
        $db = CMSApp::getDb();

        // -------------------------------------------------
        // 1) Header anhand Context laden
        // -------------------------------------------------
        $stmt = $db->prepare("
            SELECT
                id,
                css,
                headline
            FROM header
            WHERE context_id = ?
              AND enabled = 1
              AND position = 'main'
            ORDER BY sort_order ASC
            LIMIT 1
        ");

        if (!$stmt) {
            return self::fallbackHeader();
        }

        $stmt->bind_param('s', $context);
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
                    headline
                FROM header
                WHERE context_id = 'frontend'
                  AND enabled = 1
                  AND position = 'main'
                ORDER BY sort_order ASC
                LIMIT 1
            ");
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$header) {
            return self::fallbackHeader();
        }

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

        // Default-Logo, wenn keine Images existieren
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
            'headline' => $header['headline'] ?: null,
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