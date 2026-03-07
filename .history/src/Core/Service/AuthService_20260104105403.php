<?php
declare(strict_types=1);

namespace CMS\Core\Service;

use RuntimeException;

class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_ROLE_ID = 'auth_role_id';

    /**
     * Prüft ob ein User eingeloggt ist
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_USER_ID]);
    }

    /**
     * Liefert die User-ID oder NULL
     */
    public function getUserId(): ?string
    {
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    /**
     * Liefert die Role-ID oder NULL
     */
    public function getRoleId(): ?string
    {
        return $_SESSION[self::SESSION_ROLE_ID] ?? null;
    }

    /**
     * Erzwingt Login
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            throw new RuntimeException('Login erforderlich');
        }
    }

    /**
     * Erzwingt bestimmte Rolle
     */
    public function requireRole(string $requiredRoleId): void
    {
        $this->requireLogin();

        if ($this->getRoleId() !== $requiredRoleId) {
            throw new RuntimeException('Keine Berechtigung');
        }
    }

    /**
     * Setzt Login-Daten (z.B. nach erfolgreichem Login)
     */
    public function login(string $userId, string $roleId): void
    {
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_ROLE_ID] = $roleId;
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_ROLE_ID]
        );
    }
}
