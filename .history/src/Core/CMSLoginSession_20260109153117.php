<?php
declare(strict_types=1);

namespace CMS\Core\Session;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;
use RuntimeException;

class CMSLoginSession
{
    /**
     * Login mit E-Mail + Passwort
     */
    public static function login(string $email, string $password): void
    {
        $db = CMSApp::getDb();

        $sql = "
            SELECT 
                id,
                password,
                role_id,
                is_verified
            FROM login_users
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB Fehler (prepare)');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        $stmt->close();

        // -------------------------
        // User existiert?
        // -------------------------
        if (!$user) {
            throw new RuntimeException('Ungültige Zugangsdaten');
        }

        // -------------------------
        // Passwort korrekt?
        // -------------------------
        if (!password_verify($password, $user['password'])) {
            throw new RuntimeException('Ungültige Zugangsdaten');
        }

        // -------------------------
        // Account verifiziert?
        // -------------------------
        if ((int)$user['is_verified'] !== 1) {
            throw new RuntimeException('Account nicht verifiziert');
        }

        // -------------------------
        // Rolle vorhanden?
        // -------------------------
        if (empty($user['role_id'])) {
            throw new RuntimeException('User hat keine Rolle zugewiesen');
        }

        // -------------------------
        // SESSION SETZEN (ZENTRAL)
        // -------------------------
        $authService = new AuthService();
        $authService->login(
            $user['id'],
            $user['role_id']
        );
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        $authService = new AuthService();
        $authService->logout();
    }
}