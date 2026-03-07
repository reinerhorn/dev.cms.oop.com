<?php
namespace CMS\Plugin;

use mysqli;

interface PluginInterface
{
    /**
     * Lädt einen einzelnen Plugin-Inhalt anhand der plugin_content_uuid (z.B. p_content_plaintext.id).
     * Rückgabe: array (leeres array wenn nichts)
     */
    public static function loadByUuid(mysqli $db, string $pluginContentUuid, string $language): array;

    /**
     * Lädt alle Inhalte dieses Plugin-Typs für eine Seite (page.uuid).
     * Rückgabe: array in Form [ idx => [ block1, block2, ... ] ]
     */
    public static function loadByPage(mysqli $db, string $pageUuid, string $language): array;
}
