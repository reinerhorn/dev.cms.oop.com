<?php
class PageIntegrityChecker {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function ensurePublicStartPageExists(string $language = 'de'): void {
        $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM page WHERE role IS NULL AND fk_translation_placeholder = 'PAGE_START_LABEL'");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['cnt'] == 0) {
            error_log("\u274c Keine öffentliche Startseite gefunden. Eine neue wird erstellt.");
            $this->createDefaultStartPage();
        } else {
            error_log("\u2705 Öffentliche Startseite vorhanden.");
        }
    }

    private function createDefaultStartPage(): void {
        $sql = "INSERT INTO page (parent_id, idx, name, type, css, fk_translation_placeholder, meta_keywords, meta_description, print_all, enabled, role)
                VALUES (NULL, 1, 'Startseite', 'start', '', 'PAGE_START_LABEL', '', '', 0, 1, NULL)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        error_log("\u2709 Neue öffentliche Startseite erstellt.");
    }
}

// Anwendung direkt beim Init:
// $checker = new PageIntegrityChecker(CMSAppFrontend::getDb());
// $checker->ensurePublicStartPageExists('de');