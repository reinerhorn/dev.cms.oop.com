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

        $block['fields'] = [
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
        $block['meta'] = [
            'method' => 'POST',
            'action' => $block['config']['action'] ?? '',
        ];

        return $block;
    }
}
