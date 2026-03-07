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
        $result = $this->db->query('SELECT id, label, flag_path FROM trans_language ORDER BY label ASC');
        $languages = [];
        while ($row = $result->fetch_assoc()) {
            $languages[] = $row;
        }
        ?>
        <div id="LanguageSelector" class="language-selector">
            <button class="language-button" id="langBtn">
                <?php
                foreach ($languages as $lang) {
                    if ($lang['id'] === $this->currentLanguage) {
                        echo '<img src="' . htmlspecialchars($lang['flag_path']) . '" alt="' . htmlspecialchars($lang['label']) . '" class="flag-icon"> ' . htmlspecialchars($lang['label']);
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
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('langBtn');
            const list = document.getElementById('langList');

            if (!btn || !list) return;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                list.classList.toggle('show');
            });

            list.querySelectorAll('li').forEach(item => {
                item.addEventListener('click', () => {
                    const lang = item.dataset.lang;
                    list.classList.remove('show');

                    // Aktuelle URL analysieren
                    const currentUrl = window.location.href;
                    const baseUrl = window.location.origin;
                    const pathParts = window.location.pathname.split('/').filter(Boolean);

                    // Unterstützte Sprachen
                    const supportedLangs = ['de', 'en', 'fr', 'us'];
                    let newPath;

                    if (supportedLangs.includes(pathParts[0])) {
                        // Sprache ersetzen
                        pathParts[0] = lang;
                        newPath = '/' + pathParts.join('/');
                    } else {
                        // Sprache hinzufügen
                        newPath = '/' + lang + '/' + pathParts.join('/');
                    }

                    // Sprache in der Session speichern und weiterleiten
                    fetch(`/ajax/set_language.php?lang=${lang}`)
                        .then(() => { window.location.href = baseUrl + newPath; })
                        .catch(err => console.error(err));
                });
            });
        });
        </script>

 
        <?php
        return ob_get_clean();
    }
}
?>
