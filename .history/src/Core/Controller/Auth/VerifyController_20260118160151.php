<?php
declare(strict_types=1);

namespace CMS\Controller\Auth;

use mysqli;

final class VerifyController
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function verify(string $token): void
    {
        // ---------------------------------------------
        // Token prüfen
        // ---------------------------------------------
        $stmt = $this->db->prepare(
            "SELECT id, is_verified
             FROM login_users
             WHERE verify_token = ?
             LIMIT 1"
        );

        if (!$stmt) {
            http_response_code(500);
            echo 'DB-Fehler';
            exit;
        }

        $stmt->bind_param('s', $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            http_response_code(400);
            echo 'Ungültiger oder abgelaufener Verifizierungslink.';
            exit;
        }

        if ((int)$user['is_verified'] === 1) {
            header('Location: /de/login');
            exit;
        }

        // ---------------------------------------------
        // User verifizieren
        // ---------------------------------------------
        $update = $this->db->prepare(
            "UPDATE login_users
             SET is_verified = 1,
                 verify_token = NULL
             WHERE id = ?"
        );

        $update->bind_param('s', $user['id']);
        $update->execute();
        $update->close();

        // ---------------------------------------------
        // Weiterleitung
        // ---------------------------------------------
        header('Location: /de/login');
        exit;
    }
}
