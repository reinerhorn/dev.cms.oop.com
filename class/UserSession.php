<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/class/repository/ToggleFlagRepository.php';

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
        $date = date('d.m.Y l H:i:s');
        if (self::isAdmin()) {
            return '✅ Du bist als Admin angemeldet – ' . $date . '<br>Einen schönen, guten Tag: ' . htmlspecialchars($user);
        } else {
            return '👤 Du bist als Mitglied angemeldet – ' . $date . '<br>Einen schönen, guten Tag: ' . htmlspecialchars($user);
        }
    }

    /**
     * Prüft, ob der Debug-Modus für den aktuellen Benutzer oder global aktiviert ist.
     * @return bool
     */
    public static function isDebugMode(): bool
    {
        $db = \CMSApp::getDb();
        $userId = self::getUserId();
        $toggleRepo = new \ToggleFlagRepository($db);
        // Erst Benutzertoggle prüfen
        if ($userId !== null) {
            $userDebug = $toggleRepo->getStatus($userId, 'debug_toggle');
            if ($userDebug !== null) {
                return (bool)$userDebug;
            }
        }
        // Dann globalen Toggle prüfen (user_id = 0)
        $globalDebug = $toggleRepo->getStatus(0, 'debug_toggle');
        return (bool)$globalDebug;
    }
}