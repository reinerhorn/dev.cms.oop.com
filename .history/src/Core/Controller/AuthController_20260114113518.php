<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\Service\AuthService;
use CMS\Core\CMSLoginSession;

/**
 * AuthController
 *
 * EINZIGER HTTP-Endpunkt für Login / Register / Logout
 * Gibt IMMER JSON zurück
 *
 * WICHTIG:
 * - session_start() NUR hier
 * - KEINE Redirects
 * - KEIN HTML
 */
final class AuthController
{
    public static function handle(): void
    {
        // Session NUR hier starten
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        header('Content-Type: application/json; charset=utf-8');

        // -----------------------------
        // Datenquelle: FORM oder JSON
        // -----------------------------
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

        // -----------------------------
        // Guard: Action erforderlich
        // -----------------------------
        $action = $data['intent'] ?? null;

        if (!is_string($action) || $action === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Keine Action übergeben'
            ]);
            return;
        }

        // -----------------------------
        // ZENTRALER DISPATCH
        // -----------------------------
        try {
            /**
             * CMSLoginSession ist für:
             * - Login
             * - Register
             * - Logout
             * verantwortlich
             *
             * MUSS intern AuthService::login() / logout() verwenden
             */
            $result = CMSLoginSession::handleUserAction($data);

            if (!is_array($result)) {
                throw new \RuntimeException('Ungültige Auth-Antwort');
            }

            // Fehler → 422
            if (!empty($result['error'])) {
                http_response_code(422);
            }

            echo json_encode($result);
            return;

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Auth-Fehler: ' . $e->getMessage()
            ]);
            return;
        }
    }
}