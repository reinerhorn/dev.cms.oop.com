<?php
declare(strict_types=1);

namespace CMS\Repository;

use mysqli;
use RuntimeException;

class UserRepository
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Prüft, ob ein Benutzer mit der E-Mail existiert.
     */
    public function userExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler: ' . $this->db->error);
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = (bool) $res->fetch_assoc();
        $stmt->close();
        return $exists;
    }

    /**
     * Legt einen neuen Benutzer an.
     * Rückgabe: Array mit Benutzerdaten (inkl. id) oder false bei Fehler.
     */
    public function createUser(string $email, string $password, ?string $username = null)
    {
        // Passwort hashen
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            return false;
        }

        $id = $this->generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            INSERT INTO users (id, email, password_hash, username, created_at, enabled)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler: ' . $this->db->error);
        }
        $stmt->bind_param('sssss', $id, $email, $hash, $username, $now);

        if (!$stmt->execute()) {
            $stmt->close();
            // Falls unique constraint verletzt wurde, handle caller-seitig
            return false;
        }

        $stmt->close();

        return [
            'id' => $id,
            'email' => $email,
            'username' => $username,
            'created_at' => $now
        ];
    }

    /**
     * Prüft E-Mail/Passwort und liefert Benutzerdaten bei Erfolg.
     * Rückgabe: Benutzer-Array oder false.
     */
    public function verifyLogin(string $email, string $password)
    {
        $stmt = $this->db->prepare("
            SELECT id, email, username, password_hash, enabled
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler: ' . $this->db->error);
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        if ((int)$row['enabled'] !== 1) {
            return false;
        }

        // Passwort prüfen
        if (!password_verify($password, $row['password_hash'])) {
            return false;
        }

        // Optional: rehash prüfen
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->rehashPassword($row['id'], $password);
        }

        // Entferne sensible Felder
        unset($row['password_hash']);

        return $row;
    }

    /**
     * Holt Nutzer per ID.
     */
    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, username, created_at, enabled FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler: ' . $this->db->error);
        }
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Intern: rehash Passwort bei Bedarf.
     */
    private function rehashPassword(string $id, string $password): void
    {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if ($newHash === false) {
            return;
        }
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $newHash, $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Generiert eine UUID v4 (string).
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        // set version to 0100
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // set bits 6-7 to 10
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}