<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\EnvLoader;
use CMS\Application\Service\RoleService;
use CMS\Application\Repository\PageRepository;
use CMS\Security\AccessResolver;
use CMS\Application\FormAction\FormActionDispatcher ;
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

    private static ?RoleService $roleService = null;
    private static ?PageRepository $pageRepository = null;
    private static ?AccessResolver $accessResolver = null;
   private static ?FormActionDispatcher $formDispatcher = null;

    public static function init(): void
    {
        // -------------------------
        // .env laden (zentral & einmalig)
        // -------------------------
        EnvLoader::load(dirname(__DIR__, 2) . '/.env');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
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

    // =========================
    // Service Factory / Locator
    // =========================

    public static function getRoleService(): RoleService
    {
        if (self::$roleService === null) {
            self::$roleService = new RoleService(self::getDb());
        }

        return self::$roleService;
    }

    public static function getPageRepository(): PageRepository
    {
        if (self::$pageRepository === null) {
            self::$pageRepository = new PageRepository(self::getDb());
        }

        return self::$pageRepository;
    }

    public static function getAccessResolver(): AccessResolver
    {
        if (self::$accessResolver === null) {
            self::$accessResolver = new AccessResolver(
                self::getRoleService(),
                self::getPageRepository()
            );
        }

        return self::$accessResolver;
    }

    public static function getFormDispatcher(): FormActionDispatcher
    {
        if (self::$formDispatcher === null) {
            self::$formDispatcher = new FormActionDispatcher(self::getDb(), self::getAccessResolver());
        }

        return self::$formDispatcher;
    }
    /**
     * Liefert die zentrale Frontend-Renderer-Klasse
     */
   
  }