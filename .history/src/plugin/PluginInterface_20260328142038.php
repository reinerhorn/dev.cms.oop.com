<?php
namespace CMS\Plugin;

use mysqli;

interface PluginInterface
{
    /**
     * Gibt den Plugin-Namen zurück (z. B. 'plaintext', 'forms')
     */
    public static function getName(): string;

    /**
     * Lädt einen einzelnen Plugin-Inhalt
     */
    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array;

    /**
     * Lädt alle Inhalte dieses Plugin-Typs für eine Seite
     */
    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array;
}
