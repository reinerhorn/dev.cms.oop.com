<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class HeaderController
{
    /**
     * Liefert Header-Daten abhängig von Sprache & Rolle
     */
    public static function getHeaderData(string $language, string $roleId): array
    {
        $db = CMSApp::getDb();

        // 1️⃣ Header für Rolle + Sprache
        $stmt = $db->prepare("
            SELECT
                h.id,
                h.css,
                h.link,
                h.role,
                t.label AS headline
            FROM header h
            LEFT JOIN translation t
              ON t.fk_translation_placeholder = h.fk_translation_placeholder
             AND t.fk_language_id = ?
            WHERE h.role = ?
            LIMIT 1
        ");

        $stmt->bind_param('ss', $language, $roleId);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 2️⃣ Fallback → Gast-Rolle
        if (!$header && $roleId !== 'guest-role-000') {
            $stmt = $db->prepare("
                SELECT
                    h.id,
                    h.css,
                    h.link,
                    h.role,
                    t.label AS headline
                FROM header h
                LEFT JOIN translation t
                  ON t.fk_translation_placeholder = h.fk_translation_placeholder
                 AND t.fk_language_id = ?
                WHERE h.role = 'guest-role-000'
                LIMIT 1
            ");
            $stmt->bind_param('s', $language);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        // 3️⃣ Hard-Fallback (leer, NICHT übersetzt)
        if (!$header) {
            return self::fallbackHeader();
        }

        // 4️⃣ Header-Images
        $images = [];
        $imgStmt = $db->prepare("
            SELECT image_url, link_url, alt_text
            FROM header_images
            WHERE header_id = ?
            ORDER BY sort_order ASC
        ");
        $imgStmt->bind_param('s', $header['id']);
        $imgStmt->execute();
        $imgRes = $imgStmt->get_result();

        while ($img = $imgRes->fetch_assoc()) {
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

        return [
            'id'       => $header['id'],
            'css'      => $header['css'] ?: 'header',
            'link'     => $header['link'] ?: '/',
            'role'     => $header['role'],
            'headline' => $header['headline'], // ← kann NULL sein → Twig zeigt nichts
            'images'   => $images,
        ];
    }

    /**
     * Absoluter Fallback (KEINE Übersetzung)
     */
    private static function fallbackHeader(): array
    {
        return [
            'id'       => null,
            'css'      => 'header',
            'link'     => '/',
            'role'     => 'guest-role-000',
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