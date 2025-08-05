<?php

class UserSession
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['email']) && isset($_SESSION['name']);
    }

    public static function getUserName(): string
    {
        return $_SESSION['name'] ?? '';
    }

    public static function showGreeting(): string
    {
        $user = self::getUserName();
        $datum = date('d.m.Y l H:i:s') . '<br>';
        $datum .= 'Einen schönen, guten Tag: ' . htmlspecialchars($user);
        return $datum;
    }

    public static function getUserId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function getUserRoleId(): ?string
    {
        return $_SESSION['role_id'] ?? null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}