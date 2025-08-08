<?php

class UserSession
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['email']) && isset($_SESSION['name']);
    }

    public static function getUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function getUserName(): string
    {
        return $_SESSION['name'] ?? '';
    }

    public static function getRoleId(): ?string
    {
        return $_SESSION['role_id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        // Prüfen auf Superadmin oder Admin
        $roleId = self::getRoleId();
        return in_array($roleId, ['admin-role-001', 'admin-role-002']);
    }

    public static function hasRole(string $roleId): bool
    {
        return self::getRoleId() === $roleId;
    }

    public static function showGreeting(): string
    {
        $user = self::getUserName();
        $datum = date('d.m.Y l H:i:s') . '<br>';
        $datum .= 'Einen schönen, guten Tag: ' . htmlspecialchars($user);
        if (self::isAdmin()) {
            $datum .= '<br><strong>Du bist als Admin angemeldet</strong>';
        } else {
            $datum .= '<br><strong>Du bist als Mitglied angemeldet</strong>';
        }
        return $datum;
    }
}