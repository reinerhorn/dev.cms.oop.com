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
            "SELECT id, password, role_id, is_verified
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
        // 2.1) Account noch nicht verifiziert
        // -------------------------------------------------
        if ((int)($user['is_verified'] ?? 0) !== 1) {
            return [
                'status'   => 'error',
                'message'  => 'Bitte bestätige zuerst deine E-Mail-Adresse',
                'redirect' => null,
                'errors'   => [
                    'form' => 'Account ist noch nicht verifiziert'
                ],
            ];
        }

        // -------------------------------------------------
        // 2.2) Prüfen, ob neue AGB / DSGVO akzeptiert werden müssen
        // -------------------------------------------------
        $stmt = $db->prepare(
            "SELECT ld.id, ld.type
             FROM legal_documents ld
             LEFT JOIN user_legal_acceptance ula
               ON ula.document_id = ld.id
              AND ula.user_id = ?
             WHERE ld.is_active = 1
               AND ula.id IS NULL"
        );
        $stmt->bind_param('s', $user['id']);
        $stmt->execute();
        $missingAcceptances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($missingAcceptances)) {
            // Checkbox wurde noch nicht bestätigt
            if (empty($data['agree'])) {
                return [
                    'status'   => 'error',
                    'message'  => 'Bitte akzeptiere die aktualisierten AGB und Datenschutzbestimmungen',
                    'redirect' => null,
                    'errors'   => [
                        'agree' => 'Zustimmung erforderlich'
                    ],
                    'require_legal_acceptance' => true
                ];
            }

            // Zustimmung speichern
            $stmt = $db->prepare(
                "INSERT INTO user_legal_acceptance
                 (id, user_id, document_id, accepted_at, ip_address)
                 VALUES (?, ?, ?, NOW(), ?)"
            );

            foreach ($missingAcceptances as $doc) {
                $uuid = bin2hex(random_bytes(16));
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

                $stmt->bind_param(
                    'ssss',
                    $uuid,
                    $user['id'],
                    $doc['id'],
                    $ip
                );
                $stmt->execute();
            }
            $stmt->close();
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