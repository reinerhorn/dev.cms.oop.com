<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use CMS\Core\Service\AuthService;

final class LoginHandler
{
    public function handle(array $data, array $pageMeta): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        // -------------------------------------------------
        // 1) Validierung
        // -------------------------------------------------
        if ($email === '' || $password === '') {
            return [
                'status'   => 'error',
                'message'  => 'E-Mail oder Passwort fehlt',
                'redirect' => null,
                'errors'   => [
                    'email'    => $email === '' ? 'E-Mail fehlt' : null,
                    'password' => $password === '' ? 'Passwort fehlt' : null,
                ],
            ];
        }

        $db = CMSApp::getDb();

        // -------------------------------------------------
        // 2) User laden
        // -------------------------------------------------
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

        if (
            !$user
            || !isset($user['password'])
            || !password_verify($password, $user['password'])
        ) {
            return [
                'status'   => 'error',
                'message'  => 'E-Mail oder Passwort ist falsch',
                'redirect' => null,
                'errors'   => [],
            ];
        }

        // -------------------------------------------------
        // 3) Login durchführen
        // -------------------------------------------------
        $auth = new AuthService();
        $auth->login(
            (string)$user['id'],
            $user['admin']
                ? 'admin-role-001'
                : 'member-role-001'
        );

        // -------------------------------------------------
        // 4) Redirect-Ziel bestimmen
        // (optional aus DB / PageMeta erweiterbar)
        // -------------------------------------------------
        $redirect = $pageMeta['login_redirect']
            ?? '/de/startseite';

        return [
            'status'   => 'ok',
            'message'  => 'Login erfolgreich',
            'redirect' => $redirect,
            'errors'   => [],
        ];
    }
}