<?php
use CMS\Repository\User\UserRepository;

final class RegisterAction implements FormActionInterface
{
    namespace CMS\Repository\User;

use mysqli;

final class UserRepository
{
    public function __construct(private mysqli $db) {}

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                id, email, password, role_id, is_verified, verify_token
            ) VALUES (?, ?, ?, ?, 0, ?)
        ");

        $stmt->bind_param(
            'sssss',
            $data['id'],
            $data['email'],
            $data['password'],
            $data['role_id'],
            $data['verify_token']
        );

        $stmt->execute();
    }
}public function __construct(
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