<?php

namespace CMS\Plugin;

use mysqli;

class LoginFormPlugin
{
    public static function getContent(mysqli $db, string $uuid, string $language): array
    {
        $stmt = $db->prepare("
            SELECT id, form_type, headline, text, config_json
            FROM p_content_formular
            WHERE id = ? AND fk_language_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $uuid, $language);
        $stmt->execute();
        $res = $stmt->get_result();

        if (!$row = $res->fetch_assoc()) {
            return [];
        }

        return [
            'type' => $row['form_type'],       // → "login_form"
            'headline' => $row['headline'],
            'text' => $row['text'],
            'config' => json_decode($row['config_json'], true),
        ];
    }
}
