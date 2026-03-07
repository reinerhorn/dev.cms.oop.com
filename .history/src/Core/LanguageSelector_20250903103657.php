<?php
declare(strict_types=1);

namespace CMS\Core;

use mysqli;
use Exception;

/**
 * Class LanguageSelector
 *
 * Handles language selection and retrieval of language labels.
 */
class LanguageSelector {
    private mysqli $db;
    private string $currentLanguage;

    /**
     * LanguageSelector constructor.
     *
     * @param mysqli $db Database connection
     * @param string|null $currentLanguage Optional current language code
     */
    public function __construct(mysqli $db, ?string $currentLanguage = null) {
        $this->db = $db;
        $this->currentLanguage = $currentLanguage ?? $_GET['language'] ?? $_SESSION['language'] ?? 'de';
        $_SESSION['language'] = $this->currentLanguage;
    }

    /**
     * Get the label for the language selector.
     *
     * @return string The language selector label
     */
    public function getLabel(): string {
        $label = 'Wählen Sie Ihre Sprache'; // Fallback
        try {
            $stmt = $this->db->prepare('SELECT label FROM translation WHERE fk_translation_placeholder="LANG_SELECTOR_LABEL" AND fk_language_id=?');
            $stmt->bind_param('s', $this->currentLanguage);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($rec = $result->fetch_assoc()) {
                    $label = $rec['label'];
                }
            }
        } catch (Exception $e) {
            $label = '⚠ Sprache lädt nicht';
        }
        return htmlspecialchars($label);
    }

    /**
     * Get the list of available languages.
     *
     * @return array List of languages with id, label, active status, and url
     */
    public function getLanguages(): array {
        $languages = [];
        try {
            $result = $this->db->query('SELECT id, label FROM trans_language ORDER BY label ASC');
            $slug = $_GET['slug'] ?? 'startseite';
            while ($rec = $result->fetch_assoc()) {
                $languages[] = [
                    'id' => $rec['id'],
                    'label' => htmlspecialchars($rec['label']),
                    'active' => ($rec['id'] === $this->currentLanguage),
                    'url' => '/' . urlencode($rec['id']) . '/' . $slug
                ];
            }
        } catch (Exception $e) {
            $languages[] = [
                'id' => 'error',
                'label' => 'Fehler beim Laden',
                'active' => false,
                'url' => '#'
            ];
        }
        return $languages;
    }
}