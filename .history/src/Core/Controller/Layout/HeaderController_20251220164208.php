<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;
use mysqli;

class HeaderController
{
    /**
     * Liefert Header-Daten inkl. übersetzter Headline
     */
    public static function getHeaderData(string $language, string $roleId): array
    {
        $db = CMSApp::getDb();

        // Rolle → numerisch (Header-Layout)
        $roleNumeric = self::mapRoleIdToNumeric($roleId);

        // -------------------------------------------------
        // 1) Header + Übersetzung laden
        // -------------------------------------------------
        $stmt = $db->prepare("
           SELECT
    h.id,
    h.css,
    h.link,
    h.role,
    t.label AS headline
FROM header h
LEFT JOIN translation t
  ON t.fk_translation_placeholder COLLATE utf8mb4_general_ci
   = h.fk_translation_placeholder COLLATE utf8mb4_general_ci
 AND t.fk_language_id = ?
WHERE h.role = ?
LIMIT 1
        ");

        if (!$stmt) {
            return self::fallbackHeader();
        }

        $stmt->bind_param('si', $language, $roleNumeric);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // -------------------------------------------------
        // 2) Fallback → Guest
        // -------------------------------------------------
        if (!$header && $roleNumeric !== 0) {
            $stmt = $db->prepare("
                SELECT id, css, link, role
                FROM header
                WHERE role = 0
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
        // 3) Header-Images
        // -------------------------------------------------
        $images = [];
        $imgStmt = $db->prepare("
            SELECT image_url, link_url, alt_text
            FROM header_images
            WHERE header_id = ?
            ORDER BY sort_order ASC
        ");
        $imgStmt->bind_param('i', $header['id']);
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
            'link'     => $header['link'] ?: '/',
            'role'     => (int)$header['role'],
            'headline' => $header['headline'] ?? null, // ← WICHTIG
            'images'   => $images,
        ];
    }

    /**
     * RoleId → Numeric
     */
    private static function mapRoleIdToNumeric(string $roleId): int
    {
        $roleId = strtolower($roleId);

        return match (true) {
            str_starts_with($roleId, 'admin')  => 1,
            str_starts_with($roleId, 'member') => 2,
            default                            => 0,
        };
    }

    /**
     * Hard-Fallback
     */
    private static function fallbackHeader(): array
    {
        return [
            'id'       => null,
            'css'      => 'header',
            'link'     => '/',
            'role'     => 0,
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