<?php
declare(strict_types=1);

namespace CMS\Core;

use mysqli;
use Exception;

/**
 * Class LanguageSelector
 *
 * - versucht zuerst die Tabelle `trans_language` (id,label) zu lesen (falls vorhanden)
 * - falls nicht vorhanden: ermittelt die vorhandenen Sprach-Codes aus `translation`
 * - erzeugt URLs im Format "/{lang}/{slug}" (Slug wird automatisch aus GET param/constructor ermittelt)
 */
class LanguageSelector
{
    private mysqli $db;
    private string $currentLanguage;
    private string $currentSlug;

    /**
     * @param mysqli $db
     * @param string|null $currentLanguage  z. B. 'de'
     * @param string|null $currentSlug      z. B. 'startseite' (falls nicht gesetzt, werden $_GET['slug'] oder $_GET['page'] geprüft)
     */
    public function __construct(mysqli $db, ?string $currentLanguage = null, ?string $currentSlug = null)
    {
        $this->db = $db;
        $this->currentLanguage = $currentLanguage
            ?? $_GET['language'] ?? $_SESSION['language'] ?? 'de';

        // Slug: aus param, aus GET.page (legacy) oder fallback auf 'startseite'
        $this->currentSlug = $currentSlug
            ?? ($_GET['slug'] ?? ($_GET['page'] ?? 'startseite'));

        // persistieren
        $_SESSION['language'] = $this->currentLanguage;
    }

    /**
     * Label für den Language-Selector selbst (z.B. "Wählen Sie Ihre Sprache")
     */
    public function getLabel(): string
    {
        $label = 'Wählen Sie Ihre Sprache';
        try {
            $stmt = $this->db->prepare(
                'SELECT label FROM translation WHERE fk_translation_placeholder = "LANG_SELECTOR_LABEL" AND fk_language_id = ? LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('s', $this->currentLanguage);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($rec = $result->fetch_assoc()) {
                    $label = (string)$rec['label'];
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // im Fehlerfall bleibt Fallback-Label; optionales Logging
            if (ini_get('display_errors')) {
                error_log('LanguageSelector::getLabel error: ' . $e->getMessage());
            }
            $label = '⚠ Sprache lädt nicht';
        }

        return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Liefert array von Sprachen:
     * [
     *   ['id'=>'de','label'=>'Deutsch','active'=>true,'url'=>'/de/startseite'],
     *   ...
     * ]
     */
    public function getLanguages(): array
    {
        $languages = [];
        try {
            // 1) Versuch: trans_language Tabelle (häufigste, saubere Variante)
            $res = @$this->db->query("SELECT id, label FROM trans_language ORDER BY label ASC");
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $id = (string)$row['id'];
                    $label = $row['label'] ?? $this->mapLanguageName($id);
                    $languages[] = $this->makeLanguageEntry($id, $label);
                }
                return $languages;
            }

            // 2) Fallback: distinct language-codes aus translation Tabelle ableiten
            $res = $this->db->query("SELECT DISTINCT fk_language_id AS id FROM translation ORDER BY fk_language_id ASC");
            $codes = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    if (!empty($r['id'])) $codes[] = (string)$r['id'];
                }
            }

            if (empty($codes)) {
                // letzter Fallback: bekannte Codes
                $codes = ['de', 'en', 'fr', 'us'];
            }

            foreach ($codes as $code) {
                // Versuch: lesbaren Namen aus translation (falls vorhanden)
                $label = $this->mapLanguageName($code);
                $stmt = $this->db->prepare(
                    "SELECT label FROM translation WHERE fk_translation_placeholder IN ('TRANSLATION_LANGUAGE_NAME','LANG_NAME','TRANS_LANGUAGE_LABEL') AND fk_language_id = ? LIMIT 1"
                );
                if ($stmt) {
                    $stmt->bind_param('s', $code);
                    $stmt->execute();
                    $r = $stmt->get_result();
                    if ($rec = $r->fetch_assoc()) {
                        $label = (string)$rec['label'];
                    }
                    $stmt->close();
                }
                $languages[] = $this->makeLanguageEntry($code, $label);
            }
        } catch (Exception $e) {
            if (ini_get('display_errors')) {
                error_log('LanguageSelector::getLanguages error: ' . $e->getMessage());
            }
            $languages[] = [
                'id' => 'error',
                'label' => 'Fehler beim Laden',
                'active' => false,
                'url' => '#'
            ];
        }

        return $languages;
    }

    /**
     * Hilfsfunktion: erstellt ein entries-Array
     */
    private function makeLanguageEntry(string $id, string $label): array
    {
        $slug = $this->currentSlug ?: 'startseite';
        // sichere URL: /de/startseite
        $url = '/' . rawurlencode($id) . '/' . rawurlencode($slug);

        return [
            'id' => $id,
            'label' => htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'active' => ($id === $this->currentLanguage),
            'url' => $url
        ];
    }

    /**
     * Mapping bekannter Kurznamen -> lesbarer Name als Default
     */
    private function mapLanguageName(string $code): string
    {
        $map = [
            'de' => 'Deutsch',
            'en' => 'English',
            'fr' => 'Français',
            'us' => 'US'
        ];
        return $map[$code] ?? strtoupper($code);
    }
}