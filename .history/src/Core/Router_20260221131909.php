<?php
declare(strict_types=1);

namespace CMS\Core;


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

        // ---------------------------------
        // LOGOUT (GET) → niemals erlaubt
        // ---------------------------------
        if ($uri === '/auth/logout' && $method === 'GET') {
            // Freundlicher Fallback bei direktem Aufruf im Browser
            $language = $_SESSION['language'] ?? 'de';
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Location: /' . $language . '/startseite');
            exit;
        }

        // ---------------------------------
        // ACCOUNT VERIFIKATION (GET)
        // ---------------------------------
        if ($uri === '/auth/verify' && $method === 'GET') {

            $token = $_GET['token'] ?? '';

            // Kein Token → zurück zur Login-Seite
            if ($token === '') {
                $language = $_SESSION['language'] ?? 'de';
                header('Location: /' . $language . '/login-area');
                exit;
            }

            $db = CMSApp::getDb();

            // Token prüfen
            $stmt = $db->prepare(
                "SELECT id, role_id
                 FROM login_users
                 WHERE verify_token = ?
                   AND is_verified = 0
                 LIMIT 1"
            );
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Ungültiger oder bereits genutzter Token
            if (!$user) {
                $language = $_SESSION['language'] ?? 'de';
                header('Location: /' . $language . '/login-area');
                exit;
            }

            // Benutzer verifizieren
            $update = $db->prepare(
                "UPDATE login_users
                 SET is_verified = 1, verify_token = NULL
                 WHERE id = ?"
            );
            $update->bind_param('s', $user['id']);
            $update->execute();
            $update->close();

            // 🔐 AUTO-LOGIN nach Verifikation (Privilege-Upgrade sauber behandeln)
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];

            // Neuer CSRF-Token für neue Session
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));

            // Redirect nach Rolle
            if (str_starts_with($user['role_id'], 'admin-')) {
                
                header('Location: /de/adminbereich');
            } else {
                header('Location: /de/memberbereich');
            }
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
                $language = $_SESSION['language'] ?? 'de';
                header('Location: /' . $language . '/startseite');
                exit;
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

            // Neue Session für nächsten Request starten
            session_start();
            // CSRF wird zentral im Bootstrap / CMSApp initialisiert
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
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
