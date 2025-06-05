<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('CONFIG_INC_LOADED')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . "/config/config.inc.php";
}
class FooterManager {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function handlePost(array $post): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($post['action'])) {
            return;
        }

        if ($post['action'] === 'save') {
            $stmt = $this->db->prepare('REPLACE INTO footer (id, headline, link, language, label, version, css, images, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param(
                'isssssssi',
                $post['id'],
                $post['headline'],
                $post['link'],
                $post['language'],
                $post['label'],
                $post['version'],
                $post['css'],
                $post['images'],
                $post['role']
            );
            $stmt->execute();
        } elseif ($post['action'] === 'delete') {
            $stmt = $this->db->prepare('DELETE FROM footer WHERE id = ?');
            $stmt->bind_param('i', $post['id']);
            $stmt->execute();
        }
    }

    public function getAll(): array {
        $stmt = $this->db->prepare('SELECT * FROM footer');
        $stmt->execute();
        $result = $stmt->get_result();
        $entries = [];
        while ($rec = $result->fetch_assoc()) {
            $entries[$rec['id']] = $rec;
        }
        return $entries;
    }

    public function getSelected(array $entries, $selectedId = null): array {
        $selectedId = $selectedId ?? key($entries) ?? 0;
        return $entries[$selectedId] ?? ['id' => 0, 'headline' => '', 'link' => '', 'language' => '', 'label' => '', 'version' => '', 'css' => '', 'images' => '', 'role' => NULL, 'text' => ''];
    }

    public function renderForm(array $entries, array $selected): void {
        $selectedId = $selected['id'] ?? 0;
        echo '<form method="post" enctype="multipart/form-data">
        <div>
            <label for="footer_select">Footer auswählen:</label>
            <select name="id" id="footer_select" onchange="this.form.submit()">
                <option value="0">Neuer Footer</option>';
                foreach ($entries as $id => $entry) {
                    $selectedAttr = ($id == $selectedId) ? 'selected' : '';
                    echo '<option value="' . $id . '" ' . $selectedAttr . '>' . htmlspecialchars($entry['label'] ?? '', ENT_QUOTES, 'UTF-8') . '</option>';
                }
        echo '</select>
        </div>

        <input type="text" name="text" value="' . htmlspecialchars($selected['text'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Text">
        <input type="text" name="link" value="' . htmlspecialchars($selected['link'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Link">
        <input type="text" name="images" value="' . htmlspecialchars($selected['images'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Bild URL">
        <input type="text" name="language" value="' . htmlspecialchars($selected['language'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Sprache">
        <input type="text" name="version" value="' . htmlspecialchars($selected['version'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Version">
        <input type="number" name="role" value="' . htmlspecialchars($selected['role'] ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="Rolle">

        <div class="buttons">
             <?= ButtonGenerator::render('action', 'save', 'Speichern', 'button-save') ?>
                <?= ButtonGenerator::render('action', 'delete', 'Löschen', 'button-delete') ?>
        </div>
        </form>';
    }
}

$db = getDbConnection();
if (!isset($db)) {
    die('<p>Fehler: Datenbankverbindung nicht gesetzt.</p>');
}

$manager = new FooterManager($db);
$manager->handlePost($_POST);
$all = $manager->getAll();
$selected = $manager->getSelected($all, $_POST['id'] ?? null);
$manager->renderForm($all, $selected);
?>