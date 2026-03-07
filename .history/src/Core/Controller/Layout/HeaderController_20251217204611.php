<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;
use mysqli;

class HeaderController
{
    /**
     * Liefert Header-Daten abhängig von Sprache + Layout-Rolle
     *
     * Rollen-Logik (rein Layout, NICHT Login):
     * 0 = Gast / Global
     * 1 = Admin
     * 2 = Member
     */
    public static function getHeaderData(string $language, ?int $role = 0): array
    {
        $db = CMSApp::getDb();
        $language = strtolower(substr($language, 0, 2));
        $role = $role ?? 0;

        // 1️⃣ Exakt: language + role
        $stmt = $db->prepare("
            SELECT id, headline, label, link, css, role, language
            FROM header
            WHERE language = ? AND role = ?
            LIMIT 1
        ");
        $stmt->bind_param('si', $language, $role);
        $stmt->execute();
        $header = $stmt->get_result()->fetch_assoc();

        // 2️⃣ Fallback: de + role
        if (!$header && $language !== 'de') {
            $stmt = $db->prepare("
                SELECT id, headline, label, link, css, role, language
                FROM header
                WHERE language = 'de' AND role = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $role);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
        }

        // 3️⃣ Fallback: language + role = 0
        if (!$header && $role !== 0) {
            $stmt = $db->prepare("
                SELECT id, headline, label, link, css, role, language
                FROM header
                WHERE language = ? AND role = 0
                LIMIT 1
            ");
            $stmt->bind_param('s', $language);
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
        }

        // 4️⃣ Fallback: de + role = 0
        if (!$header) {
            $stmt = $db->prepare("
                SELECT id, headline, label, link, css, role, language
                FROM header
                WHERE language = 'de' AND role = 0
                LIMIT 1
            ");
            $stmt->execute();
            $header = $stmt->get_result()->fetch_assoc();
        }

        if (!$header) {
            return self::fallbackHeader($language);
        }

        // 🔹 Header-Images laden
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
            'id'       => $header['id'],
            'headline' => $header['headline'],
            'label'    => $header['label'],
            'link'     => $header['link'],
            'css'      => $header['css'],
            'role'     => (int)$header['role'],
            'language' => $header['language'],
            'images'   => $images
        ];
    }

    /**
     * Hard-Fallback Header
     */
    private static function fallbackHeader(string $language): array
    {
        return [
            'id'       => null,
            'headline' => 'HD Staffing Services',
            'label'    => 'Home',
            'link'     => '/',
            'css'      => 'header',
            'role'     => 0,
            'language' => $language,
            'images'   => [
                [
                    'image_url' => '/assets/images/hd-logo.webp',
                    'link_url'  => '/',
                    'alt_text'  => 'Logo'
                ]
            ]
        ];
    }
}