<?php
declare(strict_types=1);

namespace CMS\Application\Handler\User;

use CMS\Application\Interface\CrudHandlerInterface;
use RuntimeException;

final class LoginUsersHandler implements CrudHandlerInterface
{
    public function __construct(
        private \mysqli $db
    ) {
    }

    public function load(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                email,
                username,
                created_at,
                role_id,
                is_verified,
                verify_token
            FROM login_users
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'LoginUsers load prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];

        $stmt->close();

        return $row ?: [];
    }

    public function save(array $data): string
    {
        $id = $this->uuid();

        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $username = $this->nullableString($data['username'] ?? null);
        $roleId = $this->nullableString($data['role_id'] ?? null);
        $isVerified = !empty($data['is_verified']) ? 1 : 0;
        $verifyToken = $this->nullableString($data['verify_token'] ?? null);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
        }

        if ($password === '') {
            throw new RuntimeException('Passwort darf nicht leer sein.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO login_users
            (
                id,
                email,
                password,
                username,
                role_id,
                is_verified,
                verify_token
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'LoginUsers save prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param(
            'sssssis',
            $id,
            $email,
            $passwordHash,
            $username,
            $roleId,
            $isVerified,
            $verifyToken
        );

        if (!$stmt->execute()) {
            throw new RuntimeException(
                'LoginUsers konnte nicht gespeichert werden: ' . $stmt->error
            );
        }

        $stmt->close();

        return $id;
    }

    public function update(string $id, array $data): bool
    {
        $email = trim((string)($data['email'] ?? ''));
        $username = $this->nullableString($data['username'] ?? null);
        $roleId = $this->nullableString($data['role_id'] ?? null);
        $isVerified = !empty($data['is_verified']) ? 1 : 0;
        $verifyToken = $this->nullableString($data['verify_token'] ?? null);
        $password = trim((string)($data['password'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
        }

        /*
         * Passwort nur ändern, wenn im Formular wirklich ein neues Passwort
         * eingegeben wurde. Ein leeres Passwort überschreibt den alten Hash nicht.
         */
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("
                UPDATE login_users
                SET
                    email = ?,
                    password = ?,
                    username = ?,
                    role_id = ?,
                    is_verified = ?,
                    verify_token = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'LoginUsers update prepare fehlgeschlagen: ' . $this->db->error
                );
            }

            $stmt->bind_param(
                'ssssiss',
                $email,
                $passwordHash,
                $username,
                $roleId,
                $isVerified,
                $verifyToken,
                $id
            );
        } else {
            $stmt = $this->db->prepare("
                UPDATE login_users
                SET
                    email = ?,
                    username = ?,
                    role_id = ?,
                    is_verified = ?,
                    verify_token = ?
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new RuntimeException(
                    'LoginUsers update prepare fehlgeschlagen: ' . $this->db->error
                );
            }

            $stmt->bind_param(
                'sssiss',
                $email,
                $username,
                $roleId,
                $isVerified,
                $verifyToken,
                $id
            );
        }

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    public function delete(string $id): bool
    {
        /*
         * Schutz: Der aktuell eingeloggte Benutzer darf sich nicht selbst löschen.
         */
        if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] === $id) {
            throw new RuntimeException(
                'Der aktuell eingeloggte Benutzer kann nicht gelöscht werden.'
            );
        }

        $stmt = $this->db->prepare("
            DELETE FROM login_users
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'LoginUsers delete prepare fehlgeschlagen: ' . $this->db->error
            );
        }

        $stmt->bind_param('s', $id);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
