<?php
declare(strict_types=1);

namespace CMS\Application\FormData;

use mysqli;

final class AuthLoginFormDataLoader implements FormDataLoaderInterface
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * This loader handles login forms.
     */
    public function supports(string $formAction): bool
    {
        return $formAction === 'auth_login';
    }

    /**
     * Hydrates the login form block with required fields.
     */
    public function hydrate(array $block, array $request): array
    {
        // Optional: prefill values after submit attempt
        $data = $request ?? [];

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

        // Optional hidden fields
        $block['config']['meta'] = [
            'method' => 'POST',
            'action' => $block['config']['action'] ?? '',
        ];

        // Load buttons for this form
        $formId = $block['config']['form_id']
            ?? ($block['form_id'] ?? null);

        error_log('FORM ID (RESOLVED): ' . ($formId ?? 'NULL'));

        if ($formId) {
            $stmt = $this->db->prepare("
                SELECT b.button_id, b.label_key, b.button_action, b.button_type, b.variant
                FROM form_button fb
                JOIN ui_button b ON b.button_id = fb.button_id
                WHERE fb.form_id = ? AND b.enabled = 1
                ORDER BY fb.sort_order ASC
            ");

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

        return $block;
    }
}
