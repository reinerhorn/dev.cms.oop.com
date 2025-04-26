<div class="language-dropdown">
    <button class="language-button" onclick="document.querySelector('.language-list').classList.toggle('show'); return false;">
        <?php
        $lang = $_GET['language'] ?? $language; // nehme aktuelle Sprache aus URL oder Session
        $stmt = $main_db_connection->prepare('SELECT label FROM translation WHERE fk_translation_placeholder="LANG_SELECTOR_LABEL" AND fk_language_id=?');
        $stmt->bind_param('s', $lang);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($rec = $result->fetch_assoc()) {
            echo htmlspecialchars($rec['label']);
        } else {
            echo 'Wählen Sie Ihre Sprache';
        }
        ?>
        <span class="arrow">▼</span>
    </button>

    <div class="language-list">
        <?php
        $result = $main_db_connection->query('SELECT * FROM trans_language ORDER BY label ASC');
        $page = isset($_REQUEST['page']) ? '&page=' . $_REQUEST['page'] : '';
        while ($rec = $result->fetch_assoc()) {
            echo '<a href="?language=' . $rec['id'] . $page . '">' . htmlspecialchars($rec['label']) . '</a>';
        }
        ?>
    </div>
</div>