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

        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $roleId;

        // ZENTRALER Redirect-Entscheid
        $redirect = self::resolveStartPageForRole($roleId);
        $_SESSION['login_redirect'] = $redirect;

        session_write_close();

        // Debug (temporär, kannst du später entfernen)
        error_log('SESSION ID: ' . session_id());
        error_log('SESSION DATA: ' . json_encode($_SESSION));

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
        // TEMPORÄR hardcodiert
        // NÄCHSTER Schritt: aus DB (page.start_role_id o.ä.)
        return match ($roleId) {
            'admin-role-001',
            'admin-role-002'  => '/de/adminbereich',
            'member-role-001' => '/de/memberbereich',
            default           => '/de/startseite',
        };
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