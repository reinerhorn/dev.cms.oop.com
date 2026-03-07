<?php
use CMS\Repository\User\UserRepository;

final class RegisterAction implements FormActionInterface
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function handle(array $data): array
    {
        if (empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'error' => 'Pflichtfelder fehlen'];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Ungültige E-Mail-Adresse'];
        }

        if ($this->userRepository->emailExists($data['email'])) {
            return ['success' => false, 'error' => 'E-Mail bereits registriert'];
        }

        $userId = uuid_create(UUID_TYPE_RANDOM);

        $this->userRepository->create([
            'id'           => $userId,
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
}