<?php
declare(strict_types=1);

namespace CMS\Application\Service;

use mysqli;

final class ButtonService
{
    public static function getByFormId(mysqli $db, string $formId): array
    {
        $stmt = $db->prepare("
            SELECT 
                b.label_key,
                b.button_action,
                b.button_type,
                b.variant,
                b.confirm_required
            FROM form_button fb
            JOIN ui_button b ON b.button_id = fb.button_id
            WHERE fb.form_id = ?
              AND b.enabled = 1
            ORDER BY fb.sort_order ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $formId);
        $stmt->execute();

        $res = $stmt->get_result();

        $buttons = [];

        while ($row = $res->fetch_assoc()) {
            $buttons[] = [
                'label_key'        => $row['label_key'],
                'button_type'      => $row['button_type'],
                'variant'          => $row['variant'],
                'action'           => $row['button_action'],
                'confirm_required' => (bool)$row['confirm_required'],
            ];
        }

        $stmt->close();

        return $buttons;
    }
}
