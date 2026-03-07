<?php
declare(strict_types=1);

namespace CMS\Core;

/**
 * Schlanker Session-Wrapper.
 *
 * Diese Klasse kapselt ausschließlich den Session-State
 * (User-ID, Role-ID, Redirect).
 * Keine Business-Logik für Login/Register – diese liegt in FormActions.
 *
 * Stabiler Core-Baustein des CMS.
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
     * LOGIN / LOGOUT
     * ===================================================== */
    public static function login(string $userId, string $roleId): array
    {
        self::ensureSession();

        // Schutz vor Session Fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $roleId;

        // Redirect datengetrieben auflösen
        $redirect = self::resolveStartPageForRole($roleId);

        $_SESSION['login_redirect'] = $redirect;

        session_write_close();

        error_log('SESSION ID: ' . session_id());
        error_log('SESSION DATA: ' . json_encode($_SESSION));

        return [
            'success'  => true,
            'redirect' => $redirect,
        ];
    }

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

    /**
     * Ermittelt die Startseite anhand der Rolle.
     * ZENTRALER Einstiegspunkt nach Login.
     */
    private static function resolveStartPageForRole(string $roleId): string
    {
        // Default-Fallback
        $map = [
            'admin-role-001'  => '/de/adminbereich',
            'admin-role-002'  => '/de/adminbereich',
            'member-role-001' => '/de/memberbereich',
            'guest-role-000'  => '/de/startseite',
        ];

        return $map[$roleId] ?? '/de/startseite';
    }

    /* =====================================================
     * REDIRECT HELPER (optional, legacy-kompatibel)
     * ===================================================== */
    public static function setLoginRedirect(string $path): void
    {
        self::ensureSession();
        $_SESSION['login_redirect'] = $path;
    }

    public static function consumeLoginRedirect(): ?string
    {
        self::ensureSession();

        $redirect = $_SESSION['login_redirect'] ?? null;
        unset($_SESSION['login_redirect']);

        return is_string($redirect) ? $redirect : null;
    }

    public static function getRedirectAfterLogin(): string
    {
        self::ensureSession();
        return $_SESSION['login_redirect'] ?? '/';
    }
}