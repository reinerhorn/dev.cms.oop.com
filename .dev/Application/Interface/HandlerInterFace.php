<?php
namespace CMS\Application\Interface;

use mysqli;

interface HandlerInterface
{
    /**
     * Lädt einen einzelnen Plugin-Inhalt
     */
    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array;

    /**
     * Lädt alle Inhalte dieses Plugin-Typs für eine Seite
     */
    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array;
}
