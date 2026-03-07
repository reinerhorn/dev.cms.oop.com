<?php
 
namespace CMS\Core;

use CMS\Core\CMSApp;

class CMSLoginSession
{
    
    public static function handleUserAction(array $data): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $action   = strtolower($data['action'] ?? 'login');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $username = trim($data['username'] ?? '');

        if (!in_array($action, ['login', 'register'], true)) {
            return ['error' => 'Ungültige Aktion'];
        }

        if ($email === '' || $password === '') {
            return ['error' => 'E-Mail und Passwort sind erforderlich'];
        }

        $db = CMSApp::getDb();

        /* =========================
           REGISTRIERUNG
        ========================= */
        if ($action === 'register') {

            if ($username === '') {
                return ['error' => 'Benutzername fehlt'];
            }

            $stmt = $db->prepare("SELECT id FROM login_users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                return ['error' => 'E-Mail bereits registriert'];
            }
            $stmt->close();

            $id = self::uuid();
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $now = date('Y-m-d H:i:s');

            $role        = 2;
            $roleId      = 'member-role-002';
            $isVerified  = 0;
            $verifyToken = bin2hex(random_bytes(32));

            $stmt = $db->prepare("
                INSERT INTO login_users (
                    id, email, password, username,
                    role, token, created_at, role_id,
                    is_verified, verify_token
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $token = null;
            $stmt->bind_param(
                'ssssisssis',
                $id,
                $email,
                $hashedPassword,
                $username,
                $role,
                $token,
                $now,
                $roleId,
                $isVerified,
                $verifyToken
            );

            $stmt->execute();

            if ($stmt->errno) {
                error_log('REGISTER SQL ERROR: ' . $stmt->error);
                return ['error' => 'Registrierung fehlgeschlagen (SQL-Fehler)'];
            }

            if ($stmt->affected_rows !== 1) {
                error_log('REGISTER ERROR: ' . $stmt->error);
                return ['error' => 'Registrierung fehlgeschlagen'];
            }

            return [
                'success' => 'Registrierung erfolgreich',
                'user' => [
                    'id' => $id,
                    'email' => $email,
                    'username' => $username,
                    'role_id' => $roleId
                ]
            ];
        }

        /* =========================
           LOGIN
        ========================= */
        $stmt = $db->prepare("
            SELECT id, email, password, username, role_id
            FROM login_users
            WHERE email = ?
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            return ['error' => 'E-Mail oder Passwort falsch'];
        }

        $userType = match ($user['role_id']) {
            'admin-role-001', 'admin-role-002' => 'admin',
            'member-role-002'                  => 'member',
            'shop-role-001'                    => 'shop',
            default                            => 'forbidden',
        };

        if ($userType === 'forbidden') {
            return ['error' => 'Keine Berechtigung'];
        }

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role_id']   = $user['role_id'];
        $_SESSION['user_type'] = $userType;

        // ---------------------------------------------
        // Zielseite über CMS-SLUG bestimmen (UUID bleibt intern)
        // ---------------------------------------------
        $language = $_SESSION['language'] ?? 'de';

        // Zielseite dynamisch bestimmen (KEIN hardcodiertes "admin")
        $targetSlug = match ($userType) {
            'admin'  => 'adminbereich',
            'member' => 'member',
            'shop'   => 'shop',
            default  => 'startseite',
        };

        $redirectUrl = '/' . $language . '/' . $targetSlug;

        return [
            'success' => 'Login erfolgreich',
            'user' => [
                'id'        => $user['id'],
                'email'     => $user['email'],
                'username'  => $user['username'],
                'role_id'   => $user['role_id'],
                'user_type' => $userType
            ],
            'redirect' => $redirectUrl
        ];
    }

    private static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}