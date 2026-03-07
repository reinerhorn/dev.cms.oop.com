<?php
declare(strict_types=1);

namespace CMS\Core;

//use CMS\Core\Controller\AuthController;

/**
 * Router
 *
 * ZENTRALER technischer Dispatcher
 *
 * Verantwortlich für:
 * - AJAX / API Endpunkte (/ajax/*)
 *
 * NICHT verantwortlich für:
 * - Frontend Rendering
 * - Twig
 * - Seitenlogik
 * - Auth-Logik
 */
final class Router
{
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

        // =============================
        // AJAX / API
        // =============================
        if (str_starts_with($uri, '/ajax/')) {
            self::dispatchAjax($uri, $method);
            exit;
        }

        // =============================
        // SYSTEM ACTIONS (z. B. Logout)
        // =============================
        if ($uri === '/auth/logout' && $method === 'POST') {         

            // CSRF prüfen
            if (
                empty($_POST['_csrf']) ||
                empty($_SESSION['_csrf']) ||
                !hash_equals($_SESSION['_csrf'], $_POST['_csrf'])
            ) {
                http_response_code(403);
                exit('Invalid CSRF token');
            }

            // Sprache merken
            $language = $_SESSION['language'] ?? 'de';

            // Session vollständig leeren
            $_SESSION = [];

            // Session-Cookie löschen
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

            // Session zerstören
            session_destroy();

            // Redirect zur Startseite
            header('Location: /' . $language . '/startseite');
            exit;
        }

        // =============================
        // Alles andere ist NICHT Aufgabe
        // des Routers → Request durchlassen
        // =============================
        // bewusst kein exit hier – Rendering darf weiterlaufen
        return;
    }

    /**
     * AJAX Dispatcher
     */
    private static function dispatchAjax(string $uri, string $method): void
    {
        // Nur POST erlaubt für API
        if ($method !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Method Not Allowed',
            ]);
            exit;
        }

        match ($uri) {
            //'/ajax/auth.php' => AuthController::handle(),
            default          => self::jsonNotFound(),
        };
    }

    private static function jsonNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Endpoint not found',
        ]);
        exit;
    }
}
