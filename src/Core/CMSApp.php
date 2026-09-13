<?php
declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\EnvLoader;
use CMS\Application\Service\RoleService;
use CMS\Security\AccessResolver;
use CMS\Application\Repository\PageRepository;
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

    public static function init(): void
    {
        $__cmsapp_start = microtime(true);
        error_log('>>> CMSAPP [0.000] START');

        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] BEFORE ENVLOADER');
        EnvLoader::load(dirname(__DIR__, 2) . '/.env');
        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] AFTER ENVLOADER');

        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] BEFORE SESSION_START');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] AFTER SESSION_START');

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            error_log('CMSApp CSRF CREATED');
        } else {
            error_log('CMSApp CSRF EXISTS');
        }

        self::$language = $_SESSION['language'] ?? 'de';
        error_log('CMSApp LANGUAGE: ' . self::$language);

        self::$roleId = $_SESSION['role_id'] ?? 'guest-role-000';
        error_log('CMSApp ROLE: ' . self::$roleId);

        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] BEFORE DB');
        self::getDb();
        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] AFTER DB');

        error_log('>>> CMSAPP [' . number_format(microtime(true) - $__cmsapp_start, 6) . '] END');
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
    /**
     * Liefert die zentrale Frontend-Renderer-Klasse
     */
   
} 