<?php
namespace CMS\Service;

use mysqli;

class ButtonService
{
    public static function getByContentUuid(mysqli $db, string $uuid): array
    {
        $stmt = $db->prepare("
            SELECT label_key, button_type, variant, action, confirm_required
            FROM p_form_buttons
            WHERE plugin_content_uuid = ?
            ORDER BY sort_order ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $uuid);
        $stmt->execute();
        $res = $stmt->get_result();

        $buttons = [];

        while ($row = $res->fetch_assoc()) {
            $buttons[] = [
                'label_key'       => $row['label_key'] ?? '',
                'button_type'     => $row['button_type'] ?? 'submit',
                'variant'         => $row['variant'] ?? 'primary',
                'action'          => $row['action'] ?? null,
                'confirm_required'=> (bool)($row['confirm_required'] ?? false),
            ];
        }

        $stmt->close();

        return $buttons;
    }
}
