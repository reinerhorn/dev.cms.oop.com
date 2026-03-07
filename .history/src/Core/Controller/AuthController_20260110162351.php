<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\CMSLoginSession;
use RuntimeException;

final class AuthController
{
    /**
     * JSON Auth Endpoint
     */
    public static function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Content-Type: application/json; charset=utf-8');

        // Datenquelle bestimmen (Formular oder JSON)
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

        if (empty($data['action'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Keine Action übergeben'
            ]);
            return;
        }

        try {
            switch ($data['action']) {
                case 'login':
                    if (empty($data['email']) || empty($data['password'])) {
                        throw new RuntimeException('E-Mail oder Passwort fehlt');
                    }

                    CMSLoginSession::login(
                        (string)$data['email'],
                        (string)$data['password']
                    );

                    echo json_encode([
                        'success'  => true,
                        'redirect' => $data['redirect'] ?? '/de/admin'
                    ]);
                    return;

                case 'logout':
                    CMSLoginSession::logout();

                    echo json_encode([
                        'success'  => true,
                        'redirect' => '/de'
                    ]);
                    return;

                default:
                    throw new RuntimeException('Unbekannte Action');
            }
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
            return;
        }
    }
}