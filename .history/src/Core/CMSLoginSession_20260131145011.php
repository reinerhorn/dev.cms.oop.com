<?php
declare(strict_types=1);

namespace CMS\Core;

/**
 * Schlanker Session-Wrapper.
 *
 * Verantwortlich NUR für:
 * - Session-State (user_id, role_id)
 * - Session-Sicherheit
 * - Start-Redirect nach Login
 *
 * KEINE Auth-Logik
 * KEINE DB-Zugriffe
 * KEINE Request-Daten
 */
final class CMSLoginSession
{
    /* =====================================================
     * SESSION BOOTSTRAP
     * ===================================================== */
    public static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /* =====================================================
     * LOGIN
     * ===================================================== */
    public static function login(string $userId, string $roleId): array
    {
        self::ensureSession();

        // Schutz vor Session Fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = $userId;
        $_SESSION['role_id']   = $roleId;
        $_SESSION['logged_in'] = true;

        // ZENTRALER Redirect-Entscheid
        $redirect = self::resolveStartPageForRole($roleId);
        $_SESSION['login_redirect'] = $redirect;

        error_log('LOGIN SESSION OK: ' . json_encode($_SESSION));

        return [
            'success'  => true,
            'redirect' => $redirect,
        ];
    }

    /* =====================================================
     * LOGOUT
     * ===================================================== */
    public static function logout(): void
    {
        self::ensureSession();

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
    }

    /* =====================================================
     * STATUS / HELPER
     * ===================================================== */
    public static function isLoggedIn(): bool
    {
        self::ensureSession();
        return isset($_SESSION['user_id']);
    }

    public static function getUserId(): ?string
    {
        self::ensureSession();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getRoleId(): ?string
    {
        self::ensureSession();
        return $_SESSION['role_id'] ?? null;
    }

    /* =====================================================
     * REDIRECT-LOGIK (datengetrieben, zentral)
     * ===================================================== */
    private static function resolveStartPageForRole(string $roleId): string
    {
        $db = CMSApp::getDb();

        // 1) Default-Page der Rolle laden
        $stmt = $db->prepare(
            "SELECT default_page_id
             FROM roles
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $roleId);
        $stmt->execute();
        $role = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        if (!$role || empty($role['default_page_id'])) {
            return '/de/startseite';
        }

        $pageId = $role['default_page_id'];

        // 2) Slug der Zielseite auflösen
        $stmt = $db->prepare(
            "SELECT slug
             FROM page
             WHERE page_uuid = ?
               AND enabled = 1
             LIMIT 1"
        );
        $stmt->bind_param('s', $pageId);
        $stmt->execute();
        $slug = $stmt->get_result()?->fetch_column();
        $stmt->close();

        return is_string($slug) && $slug !== ''
            ? '/de/' . $slug
            : '/de/startseite';
    }

    /* =====================================================
     * LEGACY / GUARD-KOMPATIBILITÄT
     * ===================================================== */
    public static function consumeLoginRedirect(): ?string
    {
        self::ensureSession();

        $redirect = $_SESSION['login_redirect'] ?? null;
        unset($_SESSION['login_redirect']);

        return is_string($redirect) ? $redirect : null;
    }
}