<?php
declare(strict_types=1);

namespace CMS\Core\Controller\Layout;

use mysqli;

class LanguageSelectorController
{
    private mysqli $db;
    private string $currentLanguage;

    public function __construct(mysqli $db, ?string $currentLanguage = null)
    {
        $this->db = $db;
        $this->currentLanguage =
            $currentLanguage
            ?? $_GET['language']
            ?? $_SESSION['language']
            ?? 'de';

        $_SESSION['language'] = $this->currentLanguage;
    }

    /**
     * Liefert alle Daten für den Language-Selector (KEIN HTML)
     */
    public function getData(): array
    {
        // --- Übersetztes Label ---
        $label = '';
        $stmt = $this->db->prepare(
            "SELECT label
             FROM translation
             WHERE fk_translation_holder = 'LANG_SELECTOR_LABEL'
               AND fk_language_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $this->currentLanguage);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $label = $row['label'];
        }
        $stmt->close();

        // --- Sprachen ---
        $languages = [];
        $result = $this->db->query(
            "SELECT id, label, flag_path
             FROM trans_language
             ORDER BY label ASC"
        );

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $languages[] = $row;
            }
        }

        return [
            'current_lang' => $this->currentLanguage,
            'label'        => $label,
            'languages'    => $languages,
        ];
    }
}