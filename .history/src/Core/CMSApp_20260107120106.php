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

    /**
     * Numeric-Rolle fürs Layout
     * 0 = Gast, 1 = Admin, 2 = Member
     */
    private static int $roleNumeric = 0;

    public static function init(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // -------------------------
        // Sprache
        // -------------------------
        self::$language = $_SESSION['language'] ?? 'de';
        $_SESSION['language'] = self::$language;

        // -------------------------
        // Rolle (EINZIGE Quelle)
        // -------------------------
        if (!empty($_SESSION['role_id'])) {
            self::$roleId = (string)$_SESSION['role_id'];
        } else {
            self::$roleId = 'guest-role-000';
            $_SESSION['role_id'] = self::$roleId;
        }

        // Numeric ableiten
        self::$roleNumeric = self::mapRoleIdToNumeric(self::$roleId);

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

    /**
     * Numeric-Rolle (für Header / Navi / Layout)
     */
    public static function getRoleNumeric(): int
    {
        return self::$roleNumeric;
    }

    /**
     * String-Rolle für Navigation / CSS (guest, admin, member)
     */
    public static function getNavRole(): string
    {
        return match (self::$roleNumeric) {
            1 => 'admin',
            2 => 'member',
            default => 'guest',
        };
    }

    public static function getDb(): mysqli
    {
        if (self::$db === null) {
            self::$db = DatabaseConnection::getConnection();
        }
        return self::$db;
    }

    // =========================
    // Intern
    // =========================

    private static function mapRoleIdToNumeric(string $roleId): int
    {
        $roleId = strtolower($roleId);

        return match (true) {
            str_starts_with($roleId, 'admin-role')  => 1,
            str_starts_with($roleId, 'member-role') => 2,
            default                                 => 0,
        };
    }
}