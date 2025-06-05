<?php
// Datei: plugin_plaintext.php (oder entsprechende Datei, in der PluginPlaintext ausgegeben wird)

class PluginPlaintext {
    public static function render(mysqli $db, $pluginContentId, $pageId, string $language, bool $printAll): void {
        echo "<!-- PluginPlaintext Debug: ContentId={$pluginContentId}, PageId={$pageId}, Sprache={$language}, PrintAll=" . ($printAll ? '1' : '0') . " -->";

        if ($printAll) {
            $stmt = $db->prepare("
                SELECT p_content_plaintext.headline, p_content_plaintext.text 
                FROM page_config 
                JOIN p_content_plaintext ON page_config.plugin_content_id = p_content_plaintext.id 
                WHERE UNIX_TIMESTAMP(page_config.fk_page_id) = ? 
                  AND p_content_plaintext.fk_language_id = ? 
                ORDER BY p_content_plaintext.idx ASC
            ");
            $stmt->bind_param("is", $pageId, $language);
        } else {
            $stmt = $db->prepare("
                SELECT headline, text 
                FROM p_content_plaintext 
                WHERE id = ? 
                  AND fk_language_id = ?
            ");
            $stmt->bind_param("is", $pluginContentId, $language);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        #echo "<!-- PluginPlaintext Debug: Gefundene Datensätze = " . $result->num_rows . " -->";

        while ($rec = $result->fetch_assoc()) {
            if (empty(trim($rec['text'])) && empty(trim($rec['headline']))) {
               # echo '<div class="warning">⚠️ Kein Inhalt eingetragen.</div>';
                continue;
            }
            echo '<h1>' . htmlspecialchars($rec['headline']) . '</h1>';
            echo '<div class="holder">';
            echo $rec['text'];
            echo '</div>';
        }
    }
}
?> 