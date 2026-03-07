<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use RuntimeException;

final class LoginHandler
{
    public function handle(array $data): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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
            "SELECT id, email, password, username, admin
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

        // ✅ SESSION SETZEN (DAS HAT GEFEHlt)
        $_SESSION['user_id']  = (int)$user['id'];
        $_SESSION['email']    = $user['email'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id']  = $user['admin'] ? 'admin-role-001' : 'member-role-001';

        // optional: Redirect-Ziel aus Guard
        $redirect = $_SESSION['login_redirect'] ?? '/de';
        unset($_SESSION['login_redirect']);

        return [
            'success'  => true,
            'redirect' => $redirect
        ];
    }
}