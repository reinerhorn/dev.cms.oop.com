<?php

declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Application\FormAction\FormActionInterface;
use CMS\Repository\User\UserRepository;

final class RegisterAction implements FormActionInterface
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function handle(array $data): array
    {
        // Pflichtfelder
        if (empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'error'   => 'Pflichtfelder fehlen',
            ];
        }

        // E-Mail validieren
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        // Existiert E-Mail bereits?
        if ($this->userRepository->emailExists($data['email'])) {
            return [
                'success' => false,
                'error'   => 'E-Mail bereits registriert',
            ];
        }

        // User anlegen
        $this->userRepository->create([
            'id'           => self::uuidV4(),
            'email'        => $data['email'],
            'password'     => password_hash($data['password'], PASSWORD_DEFAULT),
            'role_id'      => 'member-role-001',
            'verify_token' => bin2hex(random_bytes(32)),
        ]);

        return [
            'success'  => true,
            'message'  => 'Registrierung erfolgreich',
            'redirect' => '/login',
        ];
    }

    private static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}