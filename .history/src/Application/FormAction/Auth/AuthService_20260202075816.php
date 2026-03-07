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
            throw new \RuntimeException('DB prepare failed');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($id, $hash, $roleId, $verified);

        if (!$stmt->fetch()) {
            return ['ok' => false, 'error' => 'Login fehlgeschlagen'];
        }

        $stmt->close();

        if ((int)$verified !== 1) {
            return ['ok' => false, 'error' => 'Account nicht verifiziert'];
        }

        if (!password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'Login fehlgeschlagen'];
        }

        return [
            'ok'     => true,
            'userId' => $id,
            'roleId' => $roleId,
        ];
    }
}
