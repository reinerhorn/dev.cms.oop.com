<?php
declare(strict_types=1);
namespace CMS\Repository\User;

use mysqli;

final class UserRepository
{
    public function __construct(private mysqli $db) {}

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM login_users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_users (
                id, email, password, role_id, is_verified, verify_token
            ) VALUES (?, ?, ?, ?, 0, ?)
        ");

        if ($stmt === false) {
            throw new \RuntimeException(
                'Prepare failed: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssss',
            $data['id'],
            $data['email'],
            $data['password'],
            $data['role_id'],
            $data['verify_token']
        );

        if (!$stmt->execute()) {
            throw new \RuntimeException(
                'Execute failed: ' . $stmt->error
            );
        }
    }
}