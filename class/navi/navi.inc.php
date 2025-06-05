<?php
 
class Navigation {
    private $db;
    private $language;
    private $role;
    private $pageId;

    public function __construct(mysqli $db, string $language, ?int $role = null, ?int $pageId = null) {
        $this->db = $db;
        $this->language = $language;
        $this->role = $role;
        $this->pageId = $pageId;
    }

    public function render(): string {
        #echo "<!-- Navigation Debug: Sprache = {$this->language}, Rolle = " . var_export($this->role, true) . ", Seite = {$this->pageId} -->";
    
        $mainPages = $this->getMainPages();
        #echo "<!-- Debug MainPages: " . json_encode($mainPages) . " -->";
        if (empty($mainPages)) {
            #echo "<!-- ⚠️ Keine Navigationseinträge gefunden für Sprache {$this->language}, Rolle {$this->role}, Seite {$this->pageId} -->";
        }
    
        $navHtml = '';
        $navId = $this->getNavId();
    
        $navHtml .= '<!-- Navigation -->';
        $navHtml .= '<nav><div id="' . $navId . '" class="nav-wrapper">';
    
        $languageParam = isset($_SESSION['language']) ? '&language=' . $_SESSION['language'] : '';
    
        foreach ($mainPages as $mainPage) {
            $css = ($this->pageId === $mainPage['tsid']) ? ' class="marked"' : '';
            $title = htmlspecialchars($mainPage['name']);
            $navHtml .= '<div class="nav-item">';
            $navHtml .= '<a' . $css . ' title="' . $title . '" href="?page=' . $mainPage['tsid'] . $languageParam . '">' . $title . '</a>';
    
            $dropdown = $this->getSubPages($mainPage['tsid']);
            #echo "<!-- Subpages für '{$mainPage['name']}' (ID {$mainPage['tsid']}): " . count($dropdown) . " -->";
            if (!empty($dropdown)) {
                $navHtml .= '<div class="navigationDropDown">';
                foreach ($dropdown as $subPage) {
                    $cssDrop = ($this->pageId === $subPage['tsid']) ? ' class="marked"' : '';
                    $titleDrop = htmlspecialchars($subPage['name']);

                    if (strtoupper($titleDrop) === 'LOGOUT') {
                        $navHtml .= '<a href="?action=logout&amp;role=' . $this->role . '" title="Logout">Logout</a>';
                        continue;
                    }

                    $navHtml .= '<a' . $cssDrop . ' title="' . $titleDrop . '" href="?page=' . $subPage['tsid'] . $languageParam . '">' . $titleDrop . '</a>';
                }
                $navHtml .= '</div>';
            }
    
            $navHtml .= '</div>'; // nav-item
        }
    
        $navHtml .= '</div></nav>';
        return $navHtml;
    }

    private function getSubPages(int $parentTsId): array {
        $role = $this->role;
    
        $stmt = $this->db->prepare("
            SELECT UNIX_TIMESTAMP(page.id) AS tsid, translation.label AS name 
            FROM page 
            JOIN translation ON page.fk_translation_placeholder = translation.fk_translation_placeholder 
            WHERE UNIX_TIMESTAMP(page.parent_id) = ? 
              AND translation.fk_language_id = ? 
              AND translation.label IS NOT NULL
              AND page.fk_translation_placeholder = translation.fk_translation_placeholder
              AND (
                  (page.role IS NULL AND (? IS NULL OR ? = 0))
                  OR (page.role = ?)
              )
            ORDER BY page.idx ASC
        ");
        $stmt->bind_param('isiii', $parentTsId, $this->language, $role, $role, $role);
    
        $stmt->execute();
        echo "<!-- Debug Sprache (SubPages): {$this->language} -->";
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getNavId(): string {
        return match ($this->role) {
            1 => 'adminNav',
            2 => 'memberNav',
            default => 'generalNav',
        };
    }

    private function getMainPages(): array {
        $stmt = $this->db->prepare("
            SELECT UNIX_TIMESTAMP(page.id) AS tsid, translation.label AS name
            FROM page
            JOIN translation ON page.fk_translation_placeholder = translation.fk_translation_placeholder
            WHERE page.type = 'main'
              AND translation.fk_language_id = ?
              AND page.fk_translation_placeholder = translation.fk_translation_placeholder
              AND (
                  (page.role IS NULL AND (? IS NULL OR ? = 0))
                  OR (page.role = ?)
              )
              AND page.parent_id IS NULL
            ORDER BY page.idx ASC
        ");
        $role = $this->role;
        $stmt->bind_param('siii', $this->language, $role, $role, $role);
        $stmt->execute();
        echo "<!-- Debug Sprache (MainPages): {$this->language} -->";
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}