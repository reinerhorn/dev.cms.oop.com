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
}