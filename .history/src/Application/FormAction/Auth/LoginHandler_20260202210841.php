<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;

final class LoginHandler
{
    public function handle(array $data): array
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'E-Mail oder Passwort fehlt'
            ];
        }

        $db = CMSApp::getDb();

        $stmt = $db->prepare(
            "SELECT id, password, admin
             FROM login_users
             WHERE email = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Login fehlgeschlagen'
            ];
        }

        // 🔥 DAS HAT GEFEHLT
        $auth = new AuthService();
        $auth->login(
            (string)$user['id'],
            $user['admin'] ? 'admin-role-001' : 'member-role-001'
        );

        return [
            'success'  => true,
            'redirect' => '/de/startseite'
        ];
    }
}