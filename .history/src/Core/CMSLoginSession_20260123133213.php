<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class CMSLoginSession
{
    /* =====================================================
     * ZENTRALER ENTRYPOINT
     * ===================================================== */
   public static function handleUserAction(array $data): array
    {
        self::ensureSession();

        try {
            return match ($data['intent'] ?? '') {
                'login'    => self::login($data),
                'register' => self::register($data),
                'resend_verify' => self::resendVerify($data),
                'logout'   => self::logout(),
                default    => [
                    'success' => false,
                    'error'   => 'Unbekannte Aktion',
                ],
            };
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /* =====================================================
     * LOGIN
     * ===================================================== */
    public static function login(array $data): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'error'   => 'E-Mail oder Passwort fehlt',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        $db = CMSApp::getDb();

        $stmt = $db->prepare(
            "SELECT id, password, role_id, is_verified
             FROM login_users
             WHERE email = ?
             LIMIT 1"
        );
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Benutzer nicht gefunden',
            ];
        }

        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'error'   => 'Falsches Passwort',
            ];
        }

        // Nicht verifizierte Nicht-Admins → kein Redirect, nur Fehler
        if (
            (int)$user['is_verified'] !== 1
            && !str_starts_with((string)$user['role_id'], 'admin-')
        ) {
            return [
                'success' => false,
                'error'   => 'Bitte bestätige zuerst deine E-Mail-Adresse',
                'code'    => 'not_verified',
            ];
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];

        session_regenerate_id(true);

        $redirect = $_SESSION['login_redirect'] ?? null;
        unset($_SESSION['login_redirect']);

        return [
            'success'  => true,
            'redirect' => $redirect
                ?? match (true) {
                    str_starts_with((string)$user['role_id'], 'admin-')
                        => '/de/adminbereich',

                    str_starts_with((string)$user['role_id'], 'member-')
                        => '/de/memberbereich',

                    default
                        => '/de/startseite',
                },
        ];
    }

    /* =====================================================
     * REGISTER
     * ===================================================== */
    private static function register(array $data): array
    {
        $email    = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $username = trim((string)($data['username'] ?? ''));

        if ($email === '' || $password === '' || $username === '') {
            return [
                'success' => false,
                'error'   => 'Pflichtfelder fehlen',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        if (strlen($password) < 8) {
            return [
                'success' => false,
                'error'   => 'Passwort zu kurz',
            ];
        }

        $db = CMSApp::getDb();

        $check = $db->prepare(
            "SELECT 1 FROM login_users WHERE email = ? LIMIT 1"
        );
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->close();
            return [
                'success' => false,
                'error'   => 'E-Mail bereits registriert',
            ];
        }
        $check->close();

        $userId      = 'user-' . bin2hex(random_bytes(8));
        $verifyToken = bin2hex(random_bytes(32));

        $stmt = $db->prepare(
            "INSERT INTO login_users
             (id, email, password, username, role_id, verify_token, is_verified)
             VALUES (?, ?, ?, ?, 'member-role-002', ?, 0)"
        );
        if (!$stmt) {
            throw new RuntimeException('DB-Fehler beim Anlegen');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bind_param(
            'sssss',
            $userId,
            $email,
            $hashedPassword,
            $username,
            $verifyToken
        );
        $stmt->execute();
        $stmt->close();

        // Verifizierungs-Mail senden
        $mail = new \CMS\Service\MailService();
        $mail->send(
            $email,
            'E-Mail-Adresse bestätigen',
            '<p>Bitte bestätige deine E-Mail-Adresse:</p>
             <p><a href="https://dev.cms-oop.com/verify?token=' . $verifyToken . '">
             E-Mail jetzt bestätigen</a></p>'
        );

        return [
            'success'  => true,
            'redirect' => '/de/login',
        ];
    }

    /* =====================================================
     * RESEND VERIFY MAIL
     * ===================================================== */
    private static function resendVerify(array $data): array
    {
        $email = trim((string)($data['email'] ?? ''));

        if ($email === '') {
            return [
                'success' => false,
                'error'   => 'E-Mail fehlt',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Ungültige E-Mail-Adresse',
            ];
        }

        $db = CMSApp::getDb();

        $stmt = $db->prepare(
            "SELECT id, verify_token, is_verified, role_id
             FROM login_users
             WHERE email = ?
             LIMIT 1"
        );
        if (!$stmt) {
            throw new \RuntimeException('DB-Fehler');
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return [
                'success' => false,
                'error'   => 'Benutzer nicht gefunden',
            ];
        }

        // Admins brauchen keine Verifizierung
        if (str_starts_with((string)$user['role_id'], 'admin-')) {
            return [
                'success' => true,
                'message' => 'Admin benötigt keine Verifizierung',
            ];
        }

        if ((int)$user['is_verified'] === 1) {
            return [
                'success' => true,
                'message' => 'E-Mail bereits bestätigt',
            ];
        }

        // Token neu erzeugen, falls leer
        $verifyToken = $user['verify_token'] ?: bin2hex(random_bytes(32));

        $update = $db->prepare(
            "UPDATE login_users
             SET verify_token = ?
             WHERE id = ?"
        );
        $update->bind_param('ss', $verifyToken, $user['id']);
        $update->execute();
        $update->close();

        // Mailversand erfolgt bewusst hier (Service)
        $mail = new \CMS\Service\MailService();
        $mail->send(
            $email,
            'E-Mail-Adresse bestätigen',
            '<p>Bitte bestätige deine E-Mail-Adresse:</p>
             <p><a href="https://dev.cms-oop.com/verify?token=' . $verifyToken . '">
             E-Mail jetzt bestätigen</a></p>'
        );

        return [
            'success' => true,
            'message' => 'Bestätigungs-Mail wurde erneut gesendet',
        ];
    }

    /* =====================================================
     * LOGOUT
     * ===================================================== */
    private static function logout(): array
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }

    /* =====================================================
     * SESSION
     * ===================================================== */
    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}