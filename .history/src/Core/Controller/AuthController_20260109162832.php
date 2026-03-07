<?php
declare(strict_types=1);

namespace CMS\Core\Controller;

use CMS\Core\CMSLoginSession;

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

        // 1) Datenquelle bestimmen
        $data = [];

        // Klassischer Formular-POST
        if (!empty($_POST)) {
            $data = $_POST;
        } else {
            // JSON-Fallback (z. B. fetch)
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (empty($data) || !isset($data['action'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'Keine gültigen Auth-Daten empfangen'
            ]);
            exit;
        }

        // 2) Zentrale Login-/Register-Logik
        $result = CMSLoginSession::handleUserAction($data);

        // 3) HTTP-Status setzen
        if (!empty($result['error'])) {
            http_response_code(422);
        } else {
            http_response_code(200);
        }

        echo json_encode($result);
        exit;
    }
}