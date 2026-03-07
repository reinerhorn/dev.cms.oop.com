<?php
declare(strict_types=1);

namespace CMS\Application\FormAction\Auth;

use CMS\Core\CMSApp;
use CMS\Application\FormAction\Auth\AuthService;
final class LoginHandler
{
    public function handle(array $data, array $pageMeta): array
    {
        error_log('LOGIN HANDLER HIT');
        error_log('LOGIN DATA = ' . json_encode($data));
        error_log('LOGIN PAGE META = ' . json_encode($pageMeta));

        $email    = strtolower(trim((string)($data['email'] ?? '')));
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
            "SELECT id, password, role_id
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
        // Session-Fixation verhindern
        session_regenerate_id(true);
        $auth = new AuthService();
        $auth->login(
        (string)$user['id'],
        (string)$user['role_id']
    );

        error_log('LOGIN SUCCESS USER_ID=' . $user['id']);
        error_log('SESSION AFTER LOGIN = ' . json_encode($_SESSION));

       

        // -------------------------------------------------
        // 4) Redirect-Ziel bestimmen (zentral & DB-basiert)
        // -------------------------------------------------

        // 1) Wurde der User vor dem Login abgefangen?
        if (!empty($_SESSION['login_redirect'])) {
            $redirect = (string)$_SESSION['login_redirect'];
            unset($_SESSION['login_redirect']);
        } else {
            // 2) Fallback: Startseite über AccessResolver (Rolle + Sprache)
            $accessResolver = CMSApp::getAccessResolver();

            $redirect = $accessResolver->resolveStartPage(
                (string)$_SESSION['role_id'],
                (string)($_SESSION['language'] ?? 'de')
            );
        }

        error_log('LOGIN REDIRECT TO = ' . ($redirect ?: 'null'));

        return [
            'status'   => 'ok',
            'message'  => 'Login erfolgreich',
            'redirect' => $redirect,
            'errors'   => [],
        ];
    }
}