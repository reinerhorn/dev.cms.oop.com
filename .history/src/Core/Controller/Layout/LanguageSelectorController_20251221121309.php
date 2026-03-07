<?php
namespace CMS\Core\Controller\Layout;

use CMS\Core\CMSApp;

class LanguageSelectorController
{
    private \mysqli $db;
    private string $currentLanguage;

    public function __construct(\mysqli $db, ?string $currentLanguage = null)
    {
        $this->db = $db;
        $this->currentLanguage = $currentLanguage 
            ?? $_GET['language'] 
            ?? $_SESSION['language'] 
            ?? 'de';
        $_SESSION['language'] = $this->currentLanguage;
    }

    public function render(): string
    {
        ob_start();
        $labelStmt = $this->db->prepare("
            SELECT label
            FROM translation
            WHERE fk_translation_placeholder = 'LANG_SELECTOR_LABEL'
              AND fk_language_id = ?
            LIMIT 1
        ");
        $labelStmt->bind_param('s', $this->currentLanguage);
        $labelStmt->execute();
        $labelRes = $labelStmt->get_result();
        $langSelectorLabel = $labelRes->fetch_assoc()['label'] ?? '';

        $result = $this->db->query('SELECT id, label, flag_path FROM trans_language ORDER BY label ASC');
        $languages = [];
        while ($row = $result->fetch_assoc()) {
            $languages[] = $row;
        }
        ?>
        <div id="LanguageSelector" class="language-dropdown">
            <button class="language-button" id="langBtn">
                <?php
                foreach ($languages as $lang) {
                    if ($lang['id'] === $this->currentLanguage) {
                        echo '<img src="' . htmlspecialchars($lang['flag_path']) . '" alt="' . htmlspecialchars($lang['label']) . '" class="flag-icon"> ';
                        echo '<span class="language-label">' . htmlspecialchars($langSelectorLabel) . '</span>';
                        break;
                    }
                }
                ?>
                <span class="arrow">▼</span>
            </button>

            <ul class="language-list" id="langList">
                <?php
                foreach ($languages as $lang) {
                    echo '<li data-lang="' . htmlspecialchars($lang['id']) . '"><img src="' . htmlspecialchars($lang['flag_path']) . '" alt="' . htmlspecialchars($lang['label']) . '" class="flag-icon"> ' . htmlspecialchars($lang['label']) . '</li>';
                }
                ?>
            </ul>
        </div>

<script>
(function () {
    const wrapper = document.getElementById('LanguageSelector');
    const btn = document.getElementById('langBtn');
    const list = document.getElementById('langList');

    if (!wrapper || !btn || !list) return;

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        wrapper.classList.toggle('open');
    });

    list.querySelectorAll('li').forEach(item => {
        item.addEventListener('click', () => {
            const lang = item.dataset.lang;
            wrapper.classList.remove('open');

            const pathParts = window.location.pathname.split('/').filter(Boolean);
            const supportedLangs = ['de', 'en', 'fr', 'us'];

            if (supportedLangs.includes(pathParts[0])) {
                pathParts[0] = lang;
            } else {
                pathParts.unshift(lang);
            }

            fetch(`/ajax/set_language.php?lang=${lang}`)
                .then(() => window.location.href = '/' + pathParts.join('/'));
        });
    });

    // Klick außerhalb → schließen
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            wrapper.classList.remove('open');
        }
    });
})();
</script>

 
        <?php
        return ob_get_clean();
    }
}
?>
