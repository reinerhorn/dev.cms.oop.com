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

    /**
     * This loader handles all auth-related forms.
     */
    public function supports(string $formAction): bool
    {
        return str_starts_with($formAction, 'auth_');
    }

    /**
     * Hydrates the form based on its action (login, register, etc.)
     */
    public function hydrate(array $block, array $request): array
    {
        $formAction = $block['config']['form_action'] ?? null;

        if (!$formAction) {
            return $block;
        }

        switch ($formAction) {
            case 'auth_login':
                return $this->hydrateLogin($block, $request);

            case 'auth_register':
                return $this->hydrateRegister($block, $request);

            default:
                return $block;
        }
    }

    private function hydrateLogin(array $block, array $request): array
    {
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

        return $this->addMetaAndButtons($block);
    }

    private function hydrateRegister(array $block, array $request): array
    {
        $data = $request ?? [];

        if (!isset($block['config']) || !is_array($block['config'])) {
            $block['config'] = [];
        }

        if (empty($block['config']['fields'])) {
            $block['config']['fields'] = [
                [
                    'type' => 'text',
                    'name' => 'username',
                    'label' => 'Benutzername',
                    'value' => $data['username'] ?? '',
                    'required' => true,
                ],
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

        return $this->addMetaAndButtons($block);
    }

    private function addMetaAndButtons(array $block): array
    {
        $block['config']['meta'] = [
            'method' => 'POST',
            'action' => $block['config']['action'] ?? '',
        ];

        $formId = $block['config']['form_id']
            ?? ($block['form_id'] ?? null);

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
