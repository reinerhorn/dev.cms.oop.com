<?php

declare(strict_types=1);

namespace CMS\Application\FormData;

use mysqli;

final class AuthFormDataLoader implements FormDataLoaderInterface
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function supports(string $formAction): bool
    {
        return $formAction === 'auth_login';
    }

    public function hydrate(array $block, array $request): array
    {
        error_log('BLOCK BEFORE HYDRATE: ' . print_r($block, true));
        error_log('FORM ACTION: ' . ($block['config']['form_action'] ?? 'NULL'));
        $data = $request ?? [];

        if (!isset($block['config']) || !is_array($block['config'])) {
            $block['config'] = [];
        }

        if (empty($block['config']['fields'])) {
            $block['config']['fields'] = [
                [
                    'type' => 'email',
                    'name' => 'email',
                    'label' => 'E-Mail',
                    'value' => $data['email'] ?? '',
                    'required' => true,
                ],
                [
                    'type' => 'password',
                    'name' => 'password',
                    'label' => 'Passwort',
                    'value' => '',
                    'required' => true,
                ],
            ];
        }

        $block['config']['meta'] = [
            'method' => 'POST',
            'action' => $block['config']['action'] ?? '',
        ];

        $formId = $block['config']['form_id'] ?? ($block['form_id'] ?? null);
        $buttons = [];
        error_log('FORM ID: ' . print_r($formId, true));

        if ($formId) {
            $block['config']['form_id'] = $formId;
            $block['form_id'] = $formId;

            $stmt = $this->db->prepare("
                SELECT b.button_id, b.label_key, b.button_action, b.button_type, b.variant
                FROM form_button fb
                JOIN ui_button b ON b.button_id = fb.button_id
                WHERE fb.form_id = ? AND b.enabled = 1
                ORDER BY fb.sort_order ASC
            ");
            if (!$stmt) {
                error_log("PREPARE ERROR: " . $this->db->error);
                return [];
            }
            if ($stmt) {
                $stmt->bind_param('s', $formId);
                $stmt->execute();
                $res = $stmt->get_result();

                $buttons = [];

                while ($res && ($row = $res->fetch_assoc())) {
                    $buttons[] = $row;
                }

                $stmt->close();

                $block['buttons'] = $buttons;
            }
        }

        error_log('BUTTONS RESULT: ' . print_r($buttons, true));

        return $block;
    }
}
