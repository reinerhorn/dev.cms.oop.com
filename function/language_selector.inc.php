<?php
class LanguageSelector {
    private mysqli $db;
    private ?string $currentLanguage;

    public function __construct(mysqli $db, ?string $currentLanguage = null) {
        $this->db = $db;
        $this->currentLanguage = $currentLanguage ?? $_GET['language'] ?? $_SESSION['language'] ?? 'de';
        $_SESSION['language'] = $this->currentLanguage;
    }

    public function render(): string {
        ob_start();
        ?>
        <div id="LanguageSelector">
            <div class="language-dropdown">
                <button class="language-button" onclick="document.querySelector('.language-list').classList.toggle('show'); return false;">
                    <?php
                    $label = 'Wählen Sie Ihre Sprache'; // Fallback
                    try {
                        $stmt = $this->db->prepare('SELECT label FROM translation WHERE fk_translation_placeholder="LANG_SELECTOR_LABEL" AND fk_language_id=?');
                        $stmt->bind_param('s', $this->currentLanguage);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($rec = $result->fetch_assoc()) {
                            $label = $rec['label'];
                        }
                    } catch (Exception $e) {
                        $label = '⚠ Sprache lädt nicht';
                    }
                    echo htmlspecialchars($label);
                    ?>
                    <span class="arrow">▼</span>
                </button>

                <div class="language-list">
                    <?php
                    try {
                        $result = $this->db->query('SELECT * FROM trans_language ORDER BY label ASC');
                        $page = isset($_GET['page']) ? '&page=' . $_GET['page'] : '';
                        while ($rec = $result->fetch_assoc()) {
                            echo '<a href="?language=' . urlencode($rec['id']) . $page . '">' . htmlspecialchars($rec['label']) . '</a>';
                        }
                    } catch (Exception $e) {
                        echo '<span>Fehler beim Laden der Sprachen: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>