<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\CMSLoginSession;

/**
 * AuthController
 *
 * EINZIGER HTTP-Endpunkt für Login / Register / Logout
 * Gibt IMMER JSON zurück
 *
 * REGELN:
 * - session_start() NUR hier
 * - KEINE Redirects
 * - KEIN HTML
 * - KEINE Business-Logik
 */
final class AuthController
{
    public static function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Content-Type: application/json; charset=utf-8');

        $data = [];

        if (!empty($_POST)) {
            $data = $_POST;
        } else {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $intent = $data['intent'] ?? null;

        if (!is_string($intent) || $intent === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Keine Aktion (intent) übergeben',
            ]);
            return;
        }

        try {
            $result = match ($intent) {
                'login'    => CMSLoginSession::login($data),
                'register' => CMSLoginSession::register($data),
                'logout'   => self::logout(),
                default    => [
                    'success' => false,
                    'error'   => 'Unbekannte Aktion',
                ],
            };

            if (!empty($result['error'])) {
                http_response_code(422);
            }

            echo json_encode($result);
            return;

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Auth-Fehler: ' . $e->getMessage(),
            ]);
            return;
        }
    }

    private static function logout(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        unset($_SESSION['user_id'], $_SESSION['role_id']);
        session_regenerate_id(true);

        return [
            'success'  => true,
            'redirect' => '/de/startseite',
        ];
    }
}