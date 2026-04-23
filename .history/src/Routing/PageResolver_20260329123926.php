<?php

declare(strict_types=1);

namespace CMS\Routing;

use mysqli;
use RuntimeException;

final class PageResolver
{
    public static function resolve(mysqli $db, string $uri): array
    {
        // URL zerlegen
        $parts = explode('/', trim($uri, '/'));

        // Sprache erkennen (z.B. "de")
        $language = $parts[0] ?? null;

        if (!$language) {
            throw new RuntimeException('Keine Sprache in URL');
        }

        // Slug bauen (z.B. "login")
        $slug = $parts[1] ?? 'home';

        // 🔎 Page + Meta laden
        $stmt = $db->prepare("
            SELECT 
                p.page_uuid,
                p.context,
                p.enabled,
                p.auth_visibility,
                p.required_permission_id,
                p.nav_id,
                p.page_css_id,
                p.form_action
            FROM page p
            INNER JOIN page ps 
                ON ps.page_uuid = p.page_uuid
            WHERE ps.slug = ?
              AND ps.language = ?
              AND p.enabled = 1
            LIMIT 1
        ");

        $stmt->bind_param('ss', $slug, $language);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new RuntimeException('Seite nicht gefunden');
        }

        return [
            'page_uuid' => $result['page_uuid'],
            'meta'      => $result,
            'language'  => $language
        ];
    }
}
