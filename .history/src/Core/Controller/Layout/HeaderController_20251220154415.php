<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class HeaderController
{
    /**
     * Liefert Header-Layout abhängig von Rolle
     *
     * Rolle:
     * 0 = Gast
     * 1 = Admin
     * 2 = Member
     */
    public static function getHeaderData(?int $role = 0): array
    {
        $db   = CMSApp::getDb();
        $role = $role ?? 0;

        // 1️⃣ Header für Rolle
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
        $stmt->bind_param('i', $role);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();

        // 2️⃣ Fallback → Gast-Header
        if (!$header && $role !== 0) {
            $stmt = $db->prepare("
                SELECT id, css, link, role
                FROM header
                WHERE role = 0
                LIMIT 1
            ");
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
        }

        // 3️⃣ Hard-Fallback
        if (!$header) {
            return self::fallbackHeader();
        }

        // 🔹 Header-Images
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

        if (empty($images)) {
            $images[] = [
                'image_url' => '/assets/images/hd-logo.webp',
                'link_url'  => '/',
                'alt_text'  => 'Logo'
            ];
        }

        return [
            'id'     => $header['id'],
            'css'    => $header['css'] ?: 'header',
            'link'   => $header['link'] ?: '/',
            'role'   => (int)$header['role'],
            'images' => $images
        ];
    }

    /**
     * Absoluter Fallback
     */
    private static function fallbackHeader(): array
    {
        return [
            'id'     => null,
            'css'    => 'header',
            'link'   => '/',
            'role'   => 0,
            'images' => [
                [
                    'image_url' => '/assets/images/hd-logo.webp',
                    'link_url'  => '/',
                    'alt_text'  => 'Logo'
                ]
            ]
        ];
    }
}