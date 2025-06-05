<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/inc/session.php";

class CMSApp {
    private static $db = null;
    private static $language = 'de';

    public static function getLanguage(): string {
        return self::$language ?? 'de';
    }

    public static function getUserRole(): ?int {
        return $_SESSION['admin_a'] ?? null;
    }

    public static function checkAccess(array $allowedRoles): bool {
        $currentRole = self::getUserRole();
        return in_array($currentRole, $allowedRoles, true);
    }

    public static function getDb() {
        if (self::$db === null) {
            self::$db = getDbConnection();
        }
        return self::$db;
    }
}
