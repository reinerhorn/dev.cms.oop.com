<?php
declare(strict_types=1);

namespace CMS\Application\Auth;

use CMS\Core\CMSApp;

final class AuthService
{
    public function login(string $email, string $password): array
    {
        $db = CMSApp::getDb();

        $stmt = $db->prepare(
            'SELECT id, password, role_id, is_verified
             FROM login_users
             WHERE email = ?
             LIMIT 1'
        );

        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'DB-Fehler'
            ];
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($id, $hash, $roleId, $isVerified);

        if (!$stmt->fetch()) {
            $stmt->close();
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        $stmt->close();

        if ((int)$isVerified !== 1) {
            return [
                'success' => false,
                'message' => 'Account nicht verifiziert'
            ];
        }

        if (!password_verify($password, $hash)) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        return [
            'success' => true,
            'user_id' => $id,
            'role_id' => $roleId
        ];
    }
}
