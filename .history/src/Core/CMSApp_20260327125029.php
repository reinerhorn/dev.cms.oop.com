<?php

declare(strict_types=1);

namespace CMS\Core;

use CMS\Core\EnvLoader;
use CMS\Application\Service\RoleService;
use CMS\Application\FormAction\FormDataLoaderResolver;
use CMS\Security\AccessResolver;
use CMS\Application\Repository\PageRepository;
use CMS\Application\FormAction\Admin\EntityFormDataLoader;
use CMS\Application\FormData\AuthFormDataLoader;
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
        error_log('ENV PATH: ' . dirname(__DIR__, 2) . '/.env');
        error_log('ENV EXISTS: ' . (file_exists(dirname(__DIR__, 2) . '/.env') ? 'YES' : 'NO'));
        // -------------------------
        // .env laden (zentral & einmalig)
        // -------------------------
        EnvLoader::load(dirname(__DIR__, 2) . '/.env');
        error_log('ENV VALUE CMS_DB_CONFIG: ' . (getenv('CMS_DB_CONFIG') ?: 'NOT SET'));

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

        // -------------------------
        // -------------------------
        // Form Data Loader Bootstrapping (zentral)
        // -------------------------
        FormDataLoaderResolver::boot([
            new EntityFormDataLoader(self::getDb()),
            new AuthFormDataLoader(self::getDb()),
        ]);
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

    /**
     * Liefert die page_uuid anhand des Slugs
     */
    public static function getPageUuidBySlug(string $slug): ?string
    {
        $db = self::getDb();

        $stmt = $db->prepare("SELECT page_uuid FROM page WHERE slug = ? AND enabled = 1 LIMIT 1");
        if (!$stmt) {
            error_log('CMSApp: prepare failed for getPageUuidBySlug');
            return null;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();

        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        return $row['page_uuid'] ?? null;
    }
}
