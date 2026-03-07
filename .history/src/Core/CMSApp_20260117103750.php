<?php
declare(strict_types=1);

namespace CMS\Core;

use mysqli;
use CMS\Config\DatabaseConnection;

class CMSApp
{
    private static ?mysqli $db = null;
    private static string $language = 'de';

    /**
     * String-Rolle aus DB (admin-role-001, member-role-002, guest-role-000)
     */
    private static string $roleId = 'guest-role-000';

    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }


        // -------------------------
        // Sprache
        // -------------------------
        self::$language = $_SESSION['language'] ?? 'de';

        // -------------------------
        // Rolle (NUR LESEN – KEIN Schreiben!)
        // -------------------------
        self::$roleId = $_SESSION['role_id'] ?? 'guest-role-000';

        // DB initialisieren
        self::getDb();
    }

    // =========================
    // Getter
    // =========================

    public static function getLanguage(): string
    {
        return self::$language;
    }

    public static function setLanguage(string $lang): void
    {
        self::$language = $lang;
        $_SESSION['language'] = $lang;
    }

    /**
     * String-Rolle (für Debug, Rechte, Logs)
     */
    public static function getRoleId(): string
    {
        return self::$roleId;
    }

    public static function getDb(): mysqli
    {
        if (self::$db === null) {
            self::$db = DatabaseConnection::getConnection();
        }
        return self::$db;
    }
}