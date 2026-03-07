<?php

declare(strict_types=1);

namespace CMS\Core\Service;

use mysqli;
use CMS\Core\CMSApp;

final class TranslationService
{
    private static ?self $instance = null;

    private mysqli $db;
    private string $language;
    private string $fallbackLanguage = 'de';

    /**
     * Request-interner Cache
     * [
     *   'PAGE_START_LABEL|de' => 'Startseite'
     * ]
     */
    private array $cache = [];

    private function __construct(mysqli $db, string $language)
    {
        $this->db = $db;
        $this->language = $language;
    }

    /**
     * Singleton pro Request
     */
    public static function getInstance(?string $language = null): self
    {
        if (self::$instance === null) {
            $lang = $language
                ?? $_SESSION['language']
                ?? 'de';

            $_SESSION['language'] = $lang;

            self::$instance = new self(
                CMSApp::getDb(),
                $lang
            );
        }

        return self::$instance;
    }

    /**
     * Zentrale Übersetzungsmethode
     */
    public function translate(string $placeholder): string
    {
        $cacheKey = $placeholder . '|' . $this->language;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // 1️⃣ gewünschte Sprache
        $label = $this->fetch($placeholder, $this->language);

        // 2️⃣ Fallback-Sprache
        if ($label === null && $this->language !== $this->fallbackLanguage) {
            $label = $this->fetch($placeholder, $this->fallbackLanguage);
        }

        // 3️⃣ Letzter Fallback: Placeholder anzeigen
        if ($label === null) {
            $label = $placeholder;
        }

        $this->cache[$cacheKey] = $label;

        return $label;
    }

    /**
     * DB-Zugriff
     */
    private function fetch(string $placeholder, string $language): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT label
             FROM translation
             WHERE fk_translation_holder = ?
               AND fk_language_id = ?
             LIMIT 1"
        );

        $stmt->bind_param('ss', $placeholder, $language);
        $stmt->execute();

        $result = $stmt->get_result()->fetch_assoc();

        return $result['label'] ?? null;
    }
}