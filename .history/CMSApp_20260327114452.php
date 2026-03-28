<?php
 use CMS\Config\DatabaseConnection;

class CMSApp {
    private static $db = null;
    private static $language = 'de';

    // Gibt die aktuelle Sprache zurück (Standard: 'de')
    public static function getLanguage(): string {
        error_log("🌐 CMSApp::getLanguage() aufgerufen – Sprache: " . (self::$language ?? 'de'));
        return self::$language ?? 'de';
    }

    // Setzt die Sprache explizit
    public static function setLanguage(string $lang): void {
        self::$language = $lang;
    }

    // Gibt die aktuelle User-Rolle aus der Session zurück (z. B. 1 = Admin)
    public static function getUserRole(): ?int {
        return $_SESSION['admin_a'] ?? null;
    }

    // Zugriffskontrolle für bestimmte Rollen
    public static function checkAccess(array $allowedRoles): bool {
        $currentRole = self::getUserRole();
        return in_array($currentRole, $allowedRoles, true);
    }

    // Gibt eine Singleton-Datenbankverbindung zurück
    public static function getDb() {
        if (self::$db === null) {
            self::$db = DatabaseConnection::getConnection();
        }
        return self::$db;
    }
}
