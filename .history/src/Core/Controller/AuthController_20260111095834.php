<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\CMSLoginSession;

/**
 * AuthController
 *
 * EINZIGER HTTP-Endpunkt für Login / Register / Logout
 * Gibt IMMER JSON zurück
 */
final class AuthController
{
    public static function handle(): void
    {
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
        if (empty($data['action'])) {
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
            $result = CMSLoginSession::handleUserAction($data);

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